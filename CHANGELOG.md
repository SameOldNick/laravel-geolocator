# Changelog

All notable changes are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2026-11-16

> **This is a major release.** It contains breaking changes to the `geolocate` request macro and makes
> the DTO, driver and client locator classes `readonly`. See [UPGRADING.md](UPGRADING.md) for the
> migration.

### Added

- `Contracts\ClientLocator` and `ClientLocatorManager`, a singleton manager (also aliased as `client-locator` and as the contract) that locates the current client rather than an address you already have. It ships with two implementations: `ClientLocators\IpClientLocator`, which resolves `$request->ip()` and delegates to a geolocator driver, and `ClientLocators\Headers\HeadersClientLocator`, which builds the result from headers injected by an upstream proxy such as the [IP2Location Caddy Middleware](https://github.com/ip2location/ip2location-caddy).
- `Facades\ClientLocator` with `fake()`, the global `ClientLocator` alias, and `ClientLocators\Fake\FakeClientLocator`, so a test can pin the client's location without a request or a database.
- `ClientLocators\Headers\RequestHeaderMapper`, the header-name normalisation and per-edition mapping behind the headers client locator.
- `geolocator.client` (`GEOLOCATOR_CLIENT`) and the `geolocator.clients.ip` and `geolocator.clients.headers` blocks, including the header prefix, the fallback address and the per-field header mappings.
- `Drivers\IPLocationDB\Exceptions\MissingDatabaseConfiguration`, thrown when an edition's path is not configured at all instead of failing with an unrelated error.

### Changed

- **Breaking:** `Request::geolocate()` is now `geolocate($edition = 'all', $driver = null)`. The default-address argument is gone — the address comes from the configured client locator — and the second argument names a client locator (`ip`, `headers`, or a name registered with `ClientLocator::extend()`) rather than a geolocator driver.
- The `geolocate` macro delegates to `ClientLocatorManager`, so client lookups resolve their geolocator driver from `geolocator.clients.ip.driver` rather than `geolocator.driver`.
- `ClientLocatorManager` is a singleton like `GeolocatorManager`, so a client locator registered with `ClientLocator::extend()` during boot survives the scope flush that Octane and queue workers perform between requests and jobs.
- The ip-location-db reader providers take their database paths through the constructor (`#[Config]` attributes on the shipped providers) instead of reading the config themselves, and `AbstractReaderProvider` no longer declares `getEdition()`.
- `CountryResult::create()` accepts an optional country name, which the headers client locator uses when the proxy supplies one.
- **Breaking:** the `LocationResult`, `CountryResult`, `CityResult` and `AsnResult` DTOs, the ip-location-db `Geolocator`, `Reader` and `ResultMapper`, and the `ClientLocators` implementations are now `readonly` classes. Their properties were already readonly, so reads are unchanged, but a subclass must also be `readonly` and Mockery can no longer mock the concrete classes.
- The test workflow runs a Laravel 11, 12 and 13 matrix, and a Rafter secret scan runs on pushes and pull requests to `main`.

### Fixed

- `CityResult::create()` normalises legacy country codes before validating them, so a code such as `UK` now produces a city result with the normalised `GB` code and name instead of `null`, matching `CountryResult::create()`.

## [2.0.0] - 2026-09-29

> **This is a major release.** It contains breaking changes to `Geolocator::fake()` and the
> `FakeGeolocator` constructor. See [UPGRADING.md](UPGRADING.md) for the steps, and the entries marked
> **Breaking** below for the details.

### Added

- `Drivers\IPLocationDB\Contracts\Reader` and `Contracts\ReaderProvider` interfaces, with `AbstractReaderProvider` and the `CountryReaderProvider`, `CityReaderProvider` and `AsnReaderProvider` implementations, registered as scoped container bindings, so each edition's reader is resolved through the container instead of a static cache.
- `Drivers\Fake\FakeReader`, an in-memory reader for the ip-location-db driver, sharing its mock matching with `FakeGeolocator` through the new `Drivers\Fake\Concerns\MocksResults` concern.
- `Request::geolocate()` accepts an edition (`all`, `country`, `city` or `asn`) and an optional driver name, so the macro can perform a single-edition lookup or resolve through a custom driver.
- Laravel Boost agent skill in `resources/boost/skills/geolocation-lookup/SKILL.md`, and the Boost guideline at `resources/boost/guidelines/core.blade.php` updated for the new macro arguments, the `fake()` signature and the per-scope readers.
- `FakeGeolocator::mock()` accepts glob patterns and the `*` wildcard, and a mock may be a closure receiving the looked up address and the calling method name. `Drivers\Fake\FakeReader::mock()` follows the same rules.
- `CONTRIBUTING.md` covering the development environment, the checks to run, the test layout and the documentation rules.
- README trimmed to the entry point — description, requirements, installation and a quick start — with the rest of the consumer documentation moved to GitHub wiki pages and linked from an index; the documented examples remain covered by `tests/Feature/ReadmeExamplesTest.php`.

### Changed

- **Breaking:** `Drivers\IPLocationDB\Geolocator` no longer takes the driver config array — its constructor takes no arguments — and it resolves each edition's reader provider from the container on every lookup, so it only works inside a booted application: build it through the facade or the `geolocator` alias rather than with `new`. Its `createReader()` and `getDatabasePath()` hooks moved to the reader providers.
- **Breaking:** `Geolocator::fake()` takes the mocked results first (`fake(array $mockedResults = [], ?int $chanceOfEmpty = null)`), so the percentage now has to be passed by name: `Geolocator::fake(chanceOfEmpty: 100)`. The default chance of an empty result is `0`.
- **Breaking:** `FakeGeolocator` moved from `Drivers\FakeGeolocator` to `Drivers\Fake\FakeGeolocator`, and its constructor is `(array $mockedResults, int $chanceOfEmpty)`; both arguments are required, and the first is no longer an optional integer.
- **Breaking:** `maxmind-db/reader` is now constrained to `^1.14.0` instead of `*`, so an application pinned below `1.14.0` has to raise its constraint before updating — the solver refuses otherwise.
- The `geolocate` request macro is registered only when the application has not already defined one, so a host application can supply its own without it being overwritten.
- The `geolocate` request macro resolves the client address with `Request::ip()` and falls back to `$default` when it is null, replacing the previous scan over `getClientIps()` that skipped private addresses.
- The ip-location-db driver no longer caches readers in a process-wide static property, and it resolves the scoped reader providers on each lookup; they discard their readers between requests and jobs, so `queue:work` and Octane pick up a replaced database without a restart.

### Fixed

- `CountryResult::create('AN')` returned `null` for the mapped code `CW`, which the country list was missing; Curaçao is now included, so legacy `AN` records resolve and the fake driver no longer throws a `TypeError` when it draws that key.

## [1.0.0] - 2026-09-20

### Added

- Initial release: offline IP geolocation (country, city, and ASN) backed by MaxMind databases.
- `lookup`, `lookupCountry`, `lookupCity`, and `lookupAsn` on the `Geolocator` facade, the `Geolocator` contract, and the `geolocator` container alias.
- `Request::geolocate()` macro for resolving the current request's location.
- `geolocator:update-iplocationdb` command and scheduler registration to keep the databases current.
- `DatabaseFileUpdated`, `DatabaseFileUpdateFailed`, and `DatabaseUpdatesCompleted` events.
- `Geolocator::fake()` test driver with per-address mocking.
- Laravel Boost AI guidelines in `resources/boost/guidelines/core.blade.php`.
