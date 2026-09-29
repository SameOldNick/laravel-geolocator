# Laravel Geolocator

[![Tests](https://github.com/SameOldNick/laravel-geolocator/actions/workflows/tests.yml/badge.svg)](https://github.com/SameOldNick/laravel-geolocator/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/SameOldNick/laravel-geolocator/graph/badge.svg?token=eU4BR7v2Cm)](https://codecov.io/gh/SameOldNick/laravel-geolocator)
[![Packagist Version](https://img.shields.io/packagist/v/sameoldnick/laravel-geolocator)](https://packagist.org/packages/sameoldnick/laravel-geolocator)

An offline IP geolocation package for Laravel, backed by MaxMind databases. Look up the country, city
and ASN behind an IP address, resolve the location of the current request, and keep the databases up
to date with a scheduled Artisan command.

## Requirements

- PHP 8.4 or newer
- Laravel 11, 12 or 13 (`illuminate/contracts ^11.0 || ^12.0 || ^13.0`)
- Composer

The MaxMind database reader is pure PHP. `ext-bcmath` or `ext-gmp` (for the pure PHP decoder) and
`ext-maxminddb` (a faster C decoder) are recommended but optional. See
[Installation](https://github.com/SameOldNick/laravel-geolocator/wiki/Installation) for the full setup,
including deployment notes.

## Installation

```bash
composer require sameoldnick/laravel-geolocator
php artisan vendor:publish --tag=geolocator-config
php artisan geolocator:update-iplocationdb
```

The service provider is auto-discovered. The package does not ship the databases, so run the update
command once per environment; **lookups throw an `InvalidArgumentException` when a configured database
file is missing**. Every path and environment variable is in
[Configuration](https://github.com/SameOldNick/laravel-geolocator/wiki/Configuration).

## Quick start

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

Both return the same `LocationResult` — see [Usage](https://github.com/SameOldNick/laravel-geolocator/wiki/Usage)
for what you can read from it.

## Documentation

This file is the entry point; the [wiki](https://github.com/SameOldNick/laravel-geolocator/wiki) holds
the detail.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for what has changed recently, and [UPGRADING.md](UPGRADING.md) for
the steps each major version needs. This project follows
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
