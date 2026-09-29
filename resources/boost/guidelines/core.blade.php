## Laravel Geolocator

Offline IP geolocation — country, city and ASN — from local MaxMind databases, provided by the
`sameoldnick/laravel-geolocator` package. Lookups never touch the network; the databases are `.mmdb`
files on disk. Only the update command below downloads anything.

### Resolving the geolocator

@verbatim
    <code-snippet name="Entry points" lang="php">
        use SameOldNick\Geolocator\Facades\Geolocator;

        Geolocator::lookup('8.8.8.8'); // country, city and ASN
        Geolocator::lookupCountry('8.8.8.8'); // country only
        Geolocator::lookupCity('8.8.8.8'); // city only
        Geolocator::lookupAsn('8.8.8.8'); // ASN only
    </code-snippet>
@endverbatim

`app('geolocator')` and `app(SameOldNick\Geolocator\Contracts\Geolocator::class)` resolve the same
manager instance. The facade and the contract share the short name `Geolocator`, so alias the contract
when importing both: `use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;`.

### Setup

Publish the configuration with `php artisan vendor:publish --tag=geolocator-config`, then download the
databases with `php artisan geolocator:update-iplocationdb`. Both are one-time steps. The update
command is the only part of the package that needs outbound HTTPS, and it replaces the database files
in `storage/app/geolocation`, so point `IPLOCATIONDB_*_PATH` at a writable mount where that path is
read-only.

### Failure modes

- A **missing database file throws `InvalidArgumentException`** rather than returning an empty
result, so run the update command before the first lookup or catch the exception.
- Private, reserved and unparseable addresses return an **empty result**, with no exception. Check
`$result->hasResults()` rather than wrapping the call in a try/catch.
- Editions you did not ask for are `null`, so read the result with the null-safe operator.

@verbatim
    <code-snippet name="Reading a result" lang="php">
        $location = Geolocator::lookup('8.8.8.8');

        $location->ipAddress; // always set
        $location->country?->countryCode; // 'US'
        $location->country?->countryName; // 'United States'
        $location->country?->getCoordinates(); // ['latitude' => ..., 'longitude' => ...]
        $location->city?->city; // 'Ashburn'
        $location->city?->state1; // 'Virginia'
        $location->city?->timezone; // 'America/New_York'
        $location->asn?->asn; // 15169
        $location->asn?->organization; // 'Google LLC'

        $location->hasResults();
        $location->toArray();
        (string)
        $location; // 'Ashburn, Virginia, United States'
    </code-snippet>
@endverbatim

`CountryResult::create()` also returns `null` for an unrecognised country code.

### Looking up the current request

Use the `geolocate` macro on `Illuminate\Http\Request` rather than calling `lookup()` with
`$request->ip()`. The macro resolves the client address with `$request->ip()` and falls back to
`'0.0.0.0'` when the request carries no address at all. Its two remaining arguments are optional: an
edition (`all`, `country`, `city` or `asn`) and a driver name.

@verbatim
    <code-snippet name="The geolocate request macro" lang="php">
        Route::get('/location', function (Illuminate\Http\Request $request) {
        return $request->geolocate()->toArray();
        });

        $request->geolocate('127.0.0.1'); // custom default
        $request->geolocate(edition: 'country'); // one edition only
    </code-snippet>
@endverbatim

`X-Forwarded-For` is only consulted for requests that arrive through a trusted proxy, configured with
Laravel's `TrustProxies` middleware. Do not parse that header by hand to work around this: any client
can send it, so hand-parsing trusts a spoofable value.

### Testing

`Geolocator::fake()` reaches no database, so it works in any test environment.

@verbatim
    <code-snippet name="Faking a lookup" lang="php">
        /** @var \SameOldNick\Geolocator\Drivers\Fake\FakeGeolocator $driver */
        $driver = Geolocator::fake();

        $driver->mock('8.8.8.8', new LocationResult(
        ipAddress: '8.8.8.8',
        country: CountryResult::create('US'),
        city: null,
        asn: null,
        ));

        $driver->mock('1.1.1.1'); // null: this address resolves to nothing
    </code-snippet>
@endverbatim

A mock key may be an exact address, a glob pattern or the `*` wildcard (an exact address beats a glob,
which beats the wildcard); a value may be a `LocationResult`, a closure, or `null` for an empty result.
Unmocked public addresses get generated data, and private addresses stay empty unless a mock matches
them.

`chanceOfEmpty` (default `0`) is the percentage chance of an empty result for an unmocked public
address, and is passed by name: `Geolocator::fake(chanceOfEmpty: 100)`.

### Custom drivers

Implement `SameOldNick\Geolocator\Contracts\Geolocator` and register it, then select it with
`GEOLOCATOR_DRIVER=my-driver`.

@verbatim
    <code-snippet name="Registering a driver" lang="php">
        Geolocator::extend('my-driver', fn ($app) => new MyGeolocator());
    </code-snippet>
@endverbatim

A driver name resolves through a registered creator or a `createXDriver()` method on the manager. A
container binding alone is **not** consulted, and throws `Driver [my-driver] not supported.`

### Deployment

Each edition's reader is cached by the scoped reader provider that built it, so queue workers discard
it before every job and Octane discards it between requests: both pick up a replaced database without
a restart. Only a process that keeps one open across an update (a long-running console command, or the
job that is mid-flight) keeps reading the old handle until it finishes.
