---
name: geolocation-lookup
description: Add and debug offline IP geolocation with the sameoldnick/laravel-geolocator package — install the MaxMind-format databases, look up country, city and ASN, resolve the current request's location, fake lookups in tests, and fix missing-database errors.
---

# Geolocation Lookup

Offline IP geolocation for Laravel. Country, city and ASN data come from local `.mmdb` files (GeoLite2
format, published by [ip-location-db](https://github.com/sapics/ip-location-db)), so a lookup is a file
read: no API key, no HTTP. Only `geolocator:update-iplocationdb` touches the network.

## When to use this skill

- Adding geolocation to a Laravel app, or resolving the current request's location.
- Looking up the country, city or ASN behind an IP address.
- Faking geolocation in tests.
- Registering a custom driver.
- Debugging a lookup that throws, or a database update that fails.

## Requirements

- PHP 8.4+ and Laravel 11, 12 or 13 (`illuminate/contracts`).
- `maxmind-db/reader`, installed for you.
- `ext-bcmath` or `ext-gmp` for the pure-PHP decoder, or `ext-maxminddb` for the faster C decoder.

## Setup

The service provider is auto-discovered, but the databases are not shipped:

```bash
php artisan vendor:publish --tag=geolocator-config   # optional, only to edit the defaults
php artisan geolocator:update-iplocationdb           # required, downloads the databases once
```

| Environment variable                   | Default                                                | Purpose                                                      |
| -------------------------------------- | ------------------------------------------------------ | ------------------------------------------------------------ |
| `GEOLOCATOR_DRIVER`                    | `iplocationdb`                                         | driver used by the manager                                   |
| `IPLOCATIONDB_COUNTRY_DB_PATH` / `_V6` | `storage/app/geolocation/GeoLite2-Country[-IPv6].mmdb` | country edition                                              |
| `IPLOCATIONDB_CITY_DB_PATH` / `_V6`    | `storage/app/geolocation/GeoLite2-City[-IPv6].mmdb`    | city edition                                                 |
| `IPLOCATIONDB_ASN_DB_PATH` / `_V6`     | `storage/app/geolocation/GeoLite2-ASN[-IPv6].mmdb`     | ASN edition                                                  |
| `MAXMIND_AUTO_UPDATE`                  | `true`                                                 | register the update command on the schedule                  |
| `MAXMIND_UPDATE_FREQUENCY`             | `weekly`                                               | `hourly`, `daily`, `weekly`, `monthly`, or a cron expression |

The `MAXMIND_*` names are deliberate even though the data comes from ip-location-db, not MaxMind.
**Until the files exist, lookups throw** — see the Failure modes section below.

## Resolving the geolocator

All of these resolve the same singleton manager:

```php
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\Facades\Geolocator;

Geolocator::lookup('8.8.8.8');                     // facade
app(GeolocatorContract::class)->lookup('8.8.8.8'); // contract
app('geolocator')->lookup('8.8.8.8');              // container alias
\Geolocator::lookup('8.8.8.8');                    // global alias, no import needed
```

The facade and the contract share the short name `Geolocator`, so alias the contract when importing
both. Prefer constructor injection of the contract in your own classes.

## Looking up an address

```php
$location = Geolocator::lookup('8.8.8.8');        // country + city + ASN
$country = Geolocator::lookupCountry('8.8.8.8');  // country edition only
$city = Geolocator::lookupCity('8.8.8.8');        // city edition only
$asn = Geolocator::lookupAsn('8.8.8.8');          // ASN edition only
```

Each returns a `LocationResult`, never `null`. Editions you did not ask for are `null`:

```php
$location->ipAddress;                  // always set
$location->country?->countryCode;      // 'US'
$location->country?->countryName;      // 'United States'
$location->country?->getCoordinates(); // ['latitude' => 37.09, 'longitude' => -95.71] | null
$location->city?->city;                // 'Ashburn'
$location->city?->state1;              // 'Virginia'
$location->city?->state2;              // county/district, often null
$location->city?->latitude;
$location->city?->longitude;
$location->city?->timezone;            // 'America/New_York'
$location->asn?->asn;                  // 15169
$location->asn?->organization;         // 'Google LLC'

$location->hasResults();               // true when at least one edition returned data
$location->toArray();                  // nested arrays
(string) $location;                    // 'Ashburn, Virginia, United States' | 'Unknown Location'
```

Private, reserved and malformed addresses are filtered before the database is queried, so they return
an empty result rather than an exception:

```php
Geolocator::lookup('192.168.1.1')->hasResults(); // false
(string) Geolocator::lookup('192.168.1.1');      // 'Unknown Location'
```

## Resolving the current request

The package registers a `geolocate` macro on `Illuminate\Http\Request`:

```php
Route::get('/where-am-i', function (Illuminate\Http\Request $request) {
    return $request->geolocate()->toArray();
});

$request->geolocate('127.0.0.1'); // fallback used when no address resolves
```

It takes the first non-private address from `getClientIps()`, falls back to `$request->ip()`, then to
`'0.0.0.0'`.

**Do not parse `X-Forwarded-For` or `X-Real-IP` by hand.** Any client can send those headers; they are
only meaningful when the request passes through a trusted proxy configured with Laravel's
`TrustProxies` middleware, which is exactly what `getClientIps()` already consults.

## Failure modes

Three things throw. Everything else returns an empty result.

| Situation                                | What happens                                                                                                                                                                                 |
| ---------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| A database file is missing or unreadable | `\InvalidArgumentException` (missing) or `MaxMind\Db\Reader\InvalidDatabaseException` (corrupt file, or a directory) from the first `lookup*()` call                                         |
| Only some editions are installed         | `lookup()` opens the **country, city and ASN** readers, so a missing city or ASN file throws even when you only read the country. Use the edition-specific methods, or install all editions. |
| An IPv6 address on an IPv4-only install  | IPv6 resolves through `editions.<edition>.ipv6` (`IPLOCATIONDB_*_PATH_V6`), which is missing or unset                                                                                        |

The exception is raised while `MaxMind\Db\Reader` is constructed, **before** the private/malformed
address filter — so on a fresh install even `Geolocator::lookup('192.168.1.1')` throws.

Fix it by running `php artisan geolocator:update-iplocationdb`, or by pointing the
`IPLOCATIONDB_*_PATH` / `IPLOCATIONDB_*_PATH_V6` variables at files you already have.

## Results and helpers

`CountryResult::create()` and `CityResult::create()` are what the driver calls; both return `null` for a
country code that is not in the package's bundled country list. `CountryResult::create()` normalises
legacy codes first (`UK`→`GB`, `EL`→`GR`, `AN`→`CW`, `CS`→`RS`); `CityResult::create()` does not.
`LocationResult::create()` and `AsnResult::create(int $asn, string $organization)` are the other factory
methods, and `CountryResult::getCoordinates(): ?array` reads from the same country list.

`Support\CountryHelper` exposes `getCountries()`, `getCountryCodes()`, `isCountryCodeValid()`,
`getCountry()`, `getCountryName()`, `getCountryCoordinates()` and `normalizeCountryCode()`.
`Support\IPAddressHelper` exposes `isValidIPAddress()`, `isIPv4()`, `isIPv6()` and
`isPrivateIPAddress()`. Use these instead of copying package classes or country data into `app/`.

## Testing

```php
use SameOldNick\Geolocator\DTOs\AsnResult;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Facades\Geolocator;

/** @var \SameOldNick\Geolocator\Drivers\FakeGeolocator $driver */
$driver = Geolocator::fake();

$driver->mock('8.8.8.8', new LocationResult(
    ipAddress: '8.8.8.8',
    country: null,
    city: null,
    asn: AsnResult::create(15169, 'Google LLC'),
));

$driver->mock('1.1.1.1'); // null result: this address resolves to nothing
```

A mock key is an exact address, a glob pattern (e.g. `8.8.8.*`) or the `*` wildcard; an exact address
wins over a glob, which wins over the wildcard. A value is a `LocationResult`, a closure, or `null` for an
empty result. A closure receives the address and the calling method name, so it can answer an
edition-specific lookup differently:

```php
$driver->mock('8.8.*', fn (string $ip, string $method) => $method === 'lookupAsn' ? $asnResult : null);
```

Both arguments of `fake()` are optional and both are passed by name, so `fake(0)` is **not** the same
call: `mockedResults` pins results up front and `chanceOfEmpty` (default `0`) is the percentage chance of
an empty result for an unmocked public address.

```php
Geolocator::fake(mockedResults: ['1.1.1.1' => null], chanceOfEmpty: 100);
```

Unmocked public addresses get generated Faker data. Private, reserved and malformed addresses resolve to
nothing — **unless a mock matches them**, which is how a `'*'` wildcard ends up answering private
addresses.

To hand the facade back to the configured driver mid-test, or after changing `geolocator.driver`, use
`Geolocator::swap(app(GeolocatorManager::class))` and `Geolocator::forgetDrivers()`.

## Custom drivers

```php
Geolocator::extend('my-driver', fn ($app) => new MyGeolocator());
```

Implement `SameOldNick\Geolocator\Contracts\Geolocator`, then select it with `GEOLOCATOR_DRIVER=my-driver`
or `geolocator.driver` in the config. A name resolves through a registered creator or a
`createXDriver()` method on the manager — a container binding alone is **not** consulted, and an
unresolvable name throws `InvalidArgumentException`.

## Keeping the databases up to date

```bash
php artisan geolocator:update-iplocationdb
php artisan geolocator:update-iplocationdb -v   # key/value context for every step
```

Every edition × `ipv4`/`ipv6` is downloaded from `update.urls.<edition>.<ipVersion>[]` to the configured
`editions.<edition>.<ipVersion>` path, retrying each URL configured for that edition. A download lands
in a temporary file and is renamed into place only when it is non-empty.

| Event                      | Fired when                                      | Payload                                          |
| -------------------------- | ----------------------------------------------- | ------------------------------------------------ |
| `DatabaseFileUpdated`      | a database file was replaced                    | `edition`, `ipVersion`, `localPath`              |
| `DatabaseFileUpdateFailed` | no URL was configured, or every download failed | `edition`, `ipVersion`, `localPath`, `reason`    |
| `DatabaseUpdatesCompleted` | the run finished                                | `total`, `successful`, `failed`, `hasFailures()` |

The package only emits these events — register your own listener for mail, Slack or database
notifications.

While `MAXMIND_AUTO_UPDATE` is enabled the package schedules the command itself, `withoutOverlapping()`
and weekly by default, so do **not** add it to `routes/console.php`.

## Deployment notes

- **Restart queue workers and Octane after an update.** Readers are cached in a static array keyed by
  database path, so a long-running process keeps the handle it opened at boot and serves the old file.
- The database directory must be writable by the user running the update command. On an ephemeral or
  read-only filesystem, bake the `.mmdb` files into the image or mount a writable volume.
- **Caching results:** the DTOs implement `Arrayable` only — no `Jsonable` and no `fromArray()` — so a
  store that serialises to JSON hands back an array, not a `LocationResult`. Be deliberate:

```php
$location = Cache::remember("geo:{$ip}", 3600, fn () => Geolocator::lookup($ip)->toArray());
```

## Best practices

1. Check `hasResults()`. `lookup()` never returns `null`, so a null check will never fire.
2. Prefer `lookupCountry()`, `lookupCity()` or `lookupAsn()` when you only need one edition — `lookup()`
   requires all three database files to exist.
3. Skip private and reserved addresses; they always return an empty result.
4. Keep heavy enrichment in queued jobs rather than the request cycle.
5. Extend, don't fork: use `Geolocator::extend()` and the `Support` helpers rather than copying package
   classes into `app/`.

## Troubleshooting

| Symptom                                                           | Cause                                                                                          | Fix                                                                                             |
| ----------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| `InvalidArgumentException` naming a `.mmdb` file                  | the databases were never downloaded                                                            | `php artisan geolocator:update-iplocationdb`, or set `IPLOCATIONDB_*_PATH`                      |
| IPv6 addresses throw while IPv4 works                             | the `-IPv6` editions are not configured                                                        | set `IPLOCATIONDB_*_PATH_V6`, or install them                                                   |
| `$location->country` is `null` but `$location->city` is populated | the country edition has no record for the address; each DTO is built from its own edition only | fall back to `$location->city->countryCode`, or call `lookupCountry()`                          |
| `Driver [x] is not supported.`                                    | no creator is registered for that name                                                         | `Geolocator::extend('x', ...)` or a `createXDriver()` method; a container binding is not enough |
| Lookups return stale data after an update                         | the static reader cache                                                                        | restart the queue workers or Octane process                                                     |
| Fake lookups return empty results at random                       | `chanceOfEmpty` is set above 0                                                                 | pass `chanceOfEmpty: 0` — that is the default                                                   |
