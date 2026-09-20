# Changelog

All notable changes are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-09-20

### Added

- Initial release: offline IP geolocation (country, city, and ASN) backed by MaxMind databases.
- `lookup`, `lookupCountry`, `lookupCity`, and `lookupAsn` on the `Geolocator` facade, the `Geolocator` contract, and the `geolocator` container alias.
- `Request::geolocate()` macro for resolving the current request's location.
- `geolocator:update-iplocationdb` command and scheduler registration to keep the databases current.
- `DatabaseFileUpdated`, `DatabaseFileUpdateFailed`, and `DatabaseUpdatesCompleted` events.
- `Geolocator::fake()` test driver with per-address mocking.
- Laravel Boost AI guidelines in `resources/boost/guidelines/core.blade.php`.
