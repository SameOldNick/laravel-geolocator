# Laravel Geolocator

[![Tests](https://github.com/SameOldNick/laravel-geolocator/actions/workflows/tests.yml/badge.svg)](https://github.com/SameOldNick/laravel-geolocator/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/SameOldNick/laravel-geolocator/graph/badge.svg?token=eU4BR7v2Cm)](https://codecov.io/gh/SameOldNick/laravel-geolocator)
[![Packagist Version](https://img.shields.io/packagist/v/sameoldnick/laravel-geolocator)](https://packagist.org/packages/sameoldnick/laravel-geolocator)

An offline IP geolocation package for Laravel, backed by MaxMind databases. Look up the country, city
and ASN behind an IP address, resolve the location of the current request, and keep the databases up
to date with a scheduled Artisan command.

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
  - [Configuration](#configuration)
  - [Deployment notes](#deployment-notes)
- [Usage](#usage)
  - [Quick start](#quick-start)
  - [Resolving the geolocator](#resolving-the-geolocator)
  - [Looking up an address](#looking-up-an-address)
  - [When a lookup throws](#when-a-lookup-throws)
  - [Resolving the current request](#resolving-the-current-request)
  - [Faking lookups in your tests](#faking-lookups-in-your-tests)
  - [Writing a custom driver](#writing-a-custom-driver)
  - [Keeping the databases up to date](#keeping-the-databases-up-to-date)
  - [Events](#events)
- [AI Guidelines](#ai-guidelines)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)

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
php artisan geolocator:update-iplocationdb
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

### Quick start

Once the databases are in place, the shortest working path is a route that resolves the caller:

```php
use Illuminate\Http\Request;

Route::get('/where-am-i', function (Request $request) {
    $location = $request->geolocate();

    return [
        'ip' => $location->ipAddress,
        'country' => $location->country?->countryCode,
        'city' => $location->city?->city,
        'asn' => $location->asn?->organization,
    ];
});
```

That is the whole integration. The service provider, the `geolocate` macro and the `Geolocator`
facade alias are registered for you, there is no API key to configure, and a lookup is a file read
rather than an HTTP call.

To look up an address other than the caller's, call the facade directly:

```php
use SameOldNick\Geolocator\Facades\Geolocator;

$location = Geolocator::lookup('8.8.8.8');
```

Both return the same `LocationResult` — see [Looking up an address](#looking-up-an-address) for what
you can read from it.

### Resolving the geolocator

```php
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\Facades\Geolocator;

Geolocator::lookup('8.8.8.8');                     // facade
app(GeolocatorContract::class)->lookup('8.8.8.8'); // contract
app('geolocator')->lookup('8.8.8.8');              // container alias
```

All three resolve the same manager instance. The contract is imported under an `as` alias because the
facade and the contract share their short name. The package also registers a global `Geolocator`
facade alias through `extra.laravel.aliases`, so the shortest entry point needs no import:

```php
\Geolocator::lookup('8.8.8.8');
```

### Looking up an address

```php
$location = Geolocator::lookup('8.8.8.8');        // country, city and ASN
$country = Geolocator::lookupCountry('8.8.8.8');  // country only
$city = Geolocator::lookupCity('8.8.8.8');        // city only
$asn = Geolocator::lookupAsn('8.8.8.8');          // ASN only
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
Geolocator::lookup('192.168.1.1')->hasResults(); // false
(string) Geolocator::lookup('192.168.1.1');      // 'Unknown Location'
```

### When a lookup throws

Lookups are file reads, and three things make them throw rather than return an empty result:

- **A missing database file.** The exception comes from `maxmind-db/reader` itself — an
  `\InvalidArgumentException` naming the file — so that is the type to catch; there is no
  package-specific exception. Run `php artisan geolocator:update-iplocationdb` once per environment, or
  bake the files into your image.
- **`lookup()` reads all three editions.** It opens the country, city _and_ ASN readers, so all three
  files have to exist even if you only ever read the country from the result. To depend on fewer
  files, call `lookupCountry()`, `lookupCity()` or `lookupAsn()`, which read one edition each.
- **IPv6 addresses read the `-IPv6` editions.** `lookup('2a00:1450:4009::200e')` resolves through
  `IPLOCATIONDB_*_PATH_V6`, so an IPv6 address throws on a machine where only the IPv4 editions were
  configured — while every IPv4 address keeps working.

Private, reserved and unparseable addresses are not on that list: they are filtered before the
database is queried, so they return an empty result. What an empty result does not mean is that the
database is absent — when the file cannot be opened, the lookup throws even for `192.168.1.1`.

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
use SameOldNick\Geolocator\Facades\Geolocator;

/** @var FakeGeolocator $driver */
$driver = Geolocator::fake();

Geolocator::lookup('8.8.8.8'); // random result, no database access

// Return a specific result for an address.
$driver->mock('8.8.8.8', new LocationResult(
    ipAddress: '8.8.8.8',
    country: null,
    city: null,
    asn: AsnResult::create(15169, 'Google LLC'),
));

// Or mock an address that resolves to nothing.
$driver->mock('1.1.1.1');
```

By default `fake()` returns an empty result for 10% of public addresses, which is what makes the fake
non-deterministic. Pass a different percentage, or `0` when you need real data for every public
address:

```php
Geolocator::fake(0);
```

The fake stays installed for the rest of the test, which is usually what you want since every test
gets a fresh container. To hand the facade back to the configured driver mid-test, swap its real root
back in:

```php
Geolocator::swap(app(\SameOldNick\Geolocator\GeolocatorManager::class));
```

### Writing a custom driver

Implement `SameOldNick\Geolocator\Contracts\Geolocator` and register it with `extend()`:

```php
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Facades\Geolocator;

class MyGeolocator implements GeolocatorContract
{
    public function lookup(string $ip): LocationResult { /* ... */ }

    public function lookupCountry(string $ip): LocationResult { /* ... */ }

    public function lookupCity(string $ip): LocationResult { /* ... */ }

    public function lookupAsn(string $ip): LocationResult { /* ... */ }
}

Geolocator::extend('my-driver', fn ($app) => new MyGeolocator());
```

Select it with `GEOLOCATOR_DRIVER=my-driver`, or by setting `geolocator.driver` in the config file.

A driver name is resolved either by a registered creator (as above) or by a `createXDriver()` method
on the manager. Any other name throws `InvalidArgumentException: Driver [x] not supported.` — the
container is not consulted, so a container binding alone will not register a driver.

### Keeping the databases up to date

```bash
php artisan geolocator:update-iplocationdb       # update now
php artisan geolocator:update-iplocationdb -v    # log each step, with context
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

The package emits these events but sends no notifications itself — register a listener to notify
whatever your application uses (mail, Slack, database notifications).

## AI Guidelines

The package ships a [Laravel Boost](https://laravel.com/docs/boost) guideline at
`resources/boost/guidelines/core.blade.php`. When a Boost user runs `php artisan boost:install`, or
`php artisan boost:update --discover` after installing this package, the guideline is merged into
their agent's context. It covers the parts that are easy to get wrong: a missing database throwing
instead of returning an empty result, the aliasing needed when importing the facade and the contract
together, the `geolocate` request macro, and why `X-Forwarded-For` must not be parsed by hand.

Nothing depends on Boost in either direction: discovery is by convention from
`vendor/sameoldnick/laravel-geolocator/resources/boost`, so the guideline costs users who do not use
Boost nothing at all. Boost renders a guideline as Blade and silently skips one that fails to render,
so its snippets are kept inside `@verbatim` — see [CONTRIBUTING.md](CONTRIBUTING.md) for how to
re-render it after editing.

There is no agent skill to go with it. The package has no multi-step authoring workflow for an agent
to improvise — setup is a one-time console task — so a guideline is enough.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for what has changed recently. This project follows
[Semantic Versioning](https://semver.org/).

## Contributing

Pull requests are welcome. Please keep the suite green and the code style applied before opening one:

```bash
composer test      # the test suite
composer format    # the code style
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the development environment, the full set of checks, the
test layout and the documentation rules.

## Security

Please review the [security policy](SECURITY.md) before reporting a vulnerability, and do not open a
public issue for security problems.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
