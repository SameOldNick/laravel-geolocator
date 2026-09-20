# Laravel Geolocator

[![Tests](https://github.com/SameOldNick/laravel-geolocator/actions/workflows/tests.yml/badge.svg)](https://github.com/SameOldNick/laravel-geolocator/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/SameOldNick/laravel-geolocator/graph/badge.svg?token=eU4BR7v2Cm)](https://codecov.io/gh/SameOldNick/laravel-geolocator)
An offline IP geolocation package for Laravel, backed by MaxMind databases. Look up the country, city
and ASN behind an IP address, resolve the location of the current request, and keep the databases up
to date with a scheduled Artisan command.

## Requirements

- PHP 8.4 or newer
- Laravel 11, 12 or 13 (`illuminate/contracts ^11.0 || ^12.0 || ^13.0`)
- Composer

The MaxMind database reader is pure PHP, but MaxMind recommends one of the following extensions:

- `ext-bcmath` or `ext-gmp`, required for decoding larger integers with the pure PHP decoder
- `ext-maxminddb`, a C-based decoder that provides significantly faster lookups

## Installation

```bash
composer require sameoldnick/laravel-geolocator
```

The service provider is auto-discovered. Publish the configuration file:

```bash
php artisan vendor:publish --tag=geolocator-config
```

Download the MaxMind databases:

```bash
php artisan geolocation:update-iplocationdb
```

The package does not ship the databases. The command downloads the country, city and ASN editions
(both IPv4 and IPv6) into `storage/app/geolocation`. **Lookups throw an `InvalidArgumentException`
when the configured database file is missing**, so run the command once after installing, or point
the package at databases you already have.

### Configuration

Everything lives under the `geolocator` config key; the values below can be set in `config/geolocator.php`
or through the environment.

| Environment variable              | Default                                              | Description                                                  |
| --------------------------------- | ---------------------------------------------------- | ------------------------------------------------------------ |
| `GEOLOCATOR_DRIVER`               | `iplocationdb`                                       | Driver used by the manager                                   |
| `IPLOCATIONDB_COUNTRY_DB_PATH`    | `storage/app/geolocation/GeoLite2-Country.mmdb`      | Country edition, IPv4                                        |
| `IPLOCATIONDB_COUNTRY_DB_PATH_V6` | `storage/app/geolocation/GeoLite2-Country-IPv6.mmdb` | Country edition, IPv6                                        |
| `IPLOCATIONDB_CITY_DB_PATH`       | `storage/app/geolocation/GeoLite2-City.mmdb`         | City edition, IPv4                                           |
| `IPLOCATIONDB_CITY_DB_PATH_V6`    | `storage/app/geolocation/GeoLite2-City-IPv6.mmdb`    | City edition, IPv6                                           |
| `IPLOCATIONDB_ASN_DB_PATH`        | `storage/app/geolocation/GeoLite2-ASN.mmdb`          | ASN edition, IPv4                                            |
| `IPLOCATIONDB_ASN_DB_PATH_V6`     | `storage/app/geolocation/GeoLite2-ASN-IPv6.mmdb`     | ASN edition, IPv6                                            |
| `MAXMIND_AUTO_UPDATE`             | `true`                                               | Register the update command on the schedule                  |
| `MAXMIND_UPDATE_FREQUENCY`        | `weekly`                                             | `hourly`, `daily`, `weekly`, `monthly`, or a cron expression |
| `GEOLOCATOR_FAKE_CHANCE_OF_EMPTY` | `10`                                                 | Percentage of empty results the fake driver returns          |

The databases are GeoLite2 data redistributed by the
[ip-location-db](https://github.com/sapics/ip-location-db) project; review that project's licensing
and attribution terms before redistributing the files yourself.

### Deployment notes

- **Network**: the update command downloads over HTTPS from a public CDN, so whatever machine runs it
  needs outbound access (or a proxy) at install and update time. Lookups themselves are offline.
- **Filesystem**: the databases are written to `storage/app/geolocation`, which needs to be writable
  and large enough for the editions you enable (the city edition is the largest). On a read-only or
  ephemeral filesystem, either bake the databases into your image or point the `IPLOCATIONDB_*_PATH`
  variables at a writable mount.
- **Long-running workers**: the ip-location-db driver caches one reader per database path for the
  lifetime of the process, so restart queue workers and Octane after updating, otherwise they keep
  reading the handle they opened at boot.

## Usage

### Resolving the geolocator

```php
use SameOldNick\Geolocator\Contracts\Geolocator;
use SameOldNick\Geolocator\Facades\Geolocate;

Geolocate::lookup('8.8.8.8');              // facade
app(Geolocator::class)->lookup('8.8.8.8'); // contract
app('geolocate')->lookup('8.8.8.8');       // container alias
```

All three resolve the same manager instance.

### Looking up an address

```php
$location = Geolocate::lookup('8.8.8.8');        // country, city and ASN
$country = Geolocate::lookupCountry('8.8.8.8');  // country only
$city = Geolocate::lookupCity('8.8.8.8');        // city only
$asn = Geolocate::lookupAsn('8.8.8.8');          // ASN only
```

Each returns a `LocationResult`, with the editions you did not ask for left as `null`:

```php
$location->ipAddress;                  // '8.8.8.8'
$location->country?->countryCode;      // e.g. 'US'
$location->country?->countryName;      // e.g. 'United States'
$location->country?->getCoordinates(); // e.g. ['latitude' => 37.09, 'longitude' => -95.71]
$location->city?->city;                // e.g. 'Ashburn'
$location->city?->state1;              // e.g. 'Virginia'
$location->city?->timezone;            // e.g. 'America/New_York'
$location->asn?->asn;                  // e.g. 15169
$location->asn?->organization;         // e.g. 'Google LLC'

$location->hasResults();               // true when any edition returned data
$location->toArray();
```

Private, reserved and unparseable addresses return no records, so the result is empty:

```php
Geolocate::lookup('192.168.1.1')->hasResults(); // false
(string) Geolocate::lookup('192.168.1.1');      // 'Unknown Location'
```

### Resolving the current request

The package registers a `geolocate` macro on the request:

```php
Route::get('/location', function (Illuminate\Http\Request $request) {
    return $request->geolocate()->toArray();
});
```

It takes the first non-private address from `$request->getClientIps()`, falls back to
`$request->ip()`, and finally to `'0.0.0.0'` when the request carries no address at all.
Pass your own default with `$request->geolocate('127.0.0.1')`.

> `X-Forwarded-For` is only consulted when the request comes from a trusted proxy. Configure this
> with Laravel's `TrustProxies` middleware, otherwise the header is ignored and the proxy's own
> address is used.

### Faking lookups in your tests

```php
use SameOldNick\Geolocator\Drivers\FakeGeolocator;
use SameOldNick\Geolocator\DTOs\AsnResult;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Facades\Geolocate;

Geolocate::fake();

Geolocate::lookup('8.8.8.8'); // random result, no database access

/** @var FakeGeolocator $driver */
$driver = Geolocate::driver();

// Return a specific result for an address.
$driver->mock('8.8.8.8', new LocationResult(
    ipAddress: '8.8.8.8',
    country: null,
    city: null,
    asn: AsnResult::create(15169, 'Google LLC'),
));

// Or mock an address that resolves to nothing.
$driver->mock('1.1.1.1');

Geolocate::fake(false); // back to the configured driver
```

Set `GEOLOCATOR_FAKE_CHANCE_OF_EMPTY=0` when you need the fake driver to return data for every
public address.

### Writing a custom driver

Implement `SameOldNick\Geolocator\Contracts\Geolocator` and register it with `extend()`:

```php
use SameOldNick\Geolocator\Contracts\Geolocator;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Facades\Geolocate;

class MyGeolocator implements Geolocator
{
    public function lookup(string $ip): LocationResult { /* ... */ }

    public function lookupCountry(string $ip): LocationResult { /* ... */ }

    public function lookupCity(string $ip): LocationResult { /* ... */ }

    public function lookupAsn(string $ip): LocationResult { /* ... */ }
}

Geolocate::extend('my-driver', fn ($app) => new MyGeolocator());
```

Select it with `GEOLOCATOR_DRIVER=my-driver`, or by setting `geolocator.driver` in the config file.

A driver name is resolved either by a registered creator (as above) or by a `createXDriver()` method
on the manager. Any other name throws `InvalidArgumentException: Driver [x] not supported.` — the
container is not consulted, so a container binding alone will not register a driver.

### Keeping the databases up to date

```bash
php artisan geolocation:update-iplocationdb       # update now
php artisan geolocation:update-iplocationdb -v    # log each step, with context
```

Downloads are retried across every URL configured for an edition. While `MAXMIND_AUTO_UPDATE` is
enabled the command is also registered on the scheduler (`MAXMIND_UPDATE_FREQUENCY`, weekly by
default) and runs `withoutOverlapping()`.

### Events

| Event                      | Dispatched when                        | Payload                                          |
| -------------------------- | -------------------------------------- | ------------------------------------------------ |
| `DatabaseFileUpdated`      | a database file was replaced           | `edition`, `ipVersion`, `localPath`              |
| `DatabaseFileUpdateFailed` | a file was skipped or every URL failed | `edition`, `ipVersion`, `localPath`, `reason`    |
| `DatabaseUpdatesCompleted` | a full update run finished             | `total`, `successful`, `failed`, `hasFailures()` |

Each file update also reports progress through the optional callback passed to
`Updater::update(callable $callback)`.

## Testing

```bash
composer test             # Pest test suite
composer test-coverage    # the same, with coverage
composer format           # Pint code style

vendor/bin/pest tests/Feature/GeolocateTest.php   # a single file
```

The runner is Pest, but the tests are plain PHPUnit classes. `tests/TestCase.php` boots Orchestra
Testbench, and `composer serve` starts the workbench demo application.

Test doubles live in `tests/Fixtures`: `RecordingGeolocator` records the calls a driver receives,
and `ScriptedUpdater` scripts the outcome of each download. Real MaxMind databases are not committed,
so the ip-location-db driver is covered through its path selection and its failure mode.

Static analysis runs with `vendor/bin/phpstan analyse src --memory-limit=1G`. The `composer analyse`
and `composer lint` scripts currently fail, because `phpstan.neon` lists a `routes` path this package
does not have and the default 128M memory limit is too low for level 7.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for what has changed recently. This project follows
[Semantic Versioning](https://semver.org/).

## Contributing

Pull requests are welcome. Please keep the suite green and the code style applied before opening one:

```bash
composer test      # the test suite
composer format    # the code style
```

## Security

Please review the [security policy](SECURITY.md) before reporting a vulnerability, and do not open a
public issue for security problems.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
