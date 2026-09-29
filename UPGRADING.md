# Upgrade guide

This guide covers the consumer-visible changes between releases — what an application has to change
when it moves to a new version. Installation, configuration and usage live in [README.md](README.md);
working on the package itself is covered by [CONTRIBUTING.md](CONTRIBUTING.md).

## 1.x to 2.0

The lookup API is unchanged: `lookup()`, `lookupCountry()`, `lookupCity()`, `lookupAsn()`, the
`LocationResult` shape and the config keys all behave as they did. What broke is the faking side and
the way the ip-location-db driver is assembled.

Most of those breaks are `TypeError`s rather than silent misbehaviour, so a test suite or an
application that calls the old signatures fails loudly rather than quietly testing something else.
Three kinds of break are not loud, so read them even if nothing is throwing: the moved class fails
with `Class "..." not found`, the `geolocate` macro changed behaviour without changing its signature,
and `new IPLocationDB\Geolocator($config)` is now accepted with the argument ignored.

### Upgrade with an AI agent

Every step below is written so that a coding agent can apply it mechanically. If you would rather have
one do it, paste this prompt into your assistant (Copilot, Claude Code, Cursor, …) with the application
repository open, then review the diff it produces:

```text
You are upgrading a Laravel application from sameoldnick/laravel-geolocator 1.x to 2.0. Work through
these steps in order, then verify your work and print the migration report described at the end. Do not
rename or re-key anything under geolocator.* in config, and leave lookup(), lookupCountry(),
lookupCity() and lookupAsn() calls alone — those are unchanged.

1. In the root composer.json, raise sameoldnick/laravel-geolocator to ^2.0 and any maxmind-db/reader
   constraint that cannot accept 1.14.0 to ^1.14.0, then run:
   composer update sameoldnick/laravel-geolocator maxmind-db/reader
2. Rename the fake class in every import and type hint: SameOldNick\Geolocator\Drivers\FakeGeolocator
   is now SameOldNick\Geolocator\Drivers\Fake\FakeGeolocator. The old name is gone, not aliased, so a
   stale import is a fatal error.
3. Fix every Geolocator::fake() call: the mocked results are the first parameter now, so the chance of
   an empty result has to be named — Geolocator::fake(0) becomes Geolocator::fake(chanceOfEmpty: 0).
   Passing an integer positionally throws a TypeError.
4. Update direct construction: new FakeGeolocator() takes (array $mockedResults, int $chanceOfEmpty)
   with no defaults. Prefer Geolocator::fake().
5. Stop constructing the ip-location-db driver yourself: new IPLocationDB\Geolocator and
   new IPLocationDB\Geolocator($config) both ignore the argument and resolve their readers from the
   container, so use the Geolocator facade or app('geolocator') instead. If the application extends or
   overrides the driver, port that too: createReader() and getDatabasePath() are gone from
   IPLocationDB\Geolocator and now live on the reader providers — CountryReaderProvider and its city
   and ASN siblings, extending AbstractReaderProvider — which are scoped container bindings, so that
   is the seam to replace.
6. Fix tests that relied on fake() returning an empty result for 10% of public addresses: it is
   deterministic now and returns data by default. Ask for the empty result with
   Geolocator::fake(chanceOfEmpty: 100), or pin the address with $driver->mock('8.8.8.8').
7. Check every $request->geolocate() call site. Its arguments are unchanged, but the client address now
   comes from $request->ip() and falls back to the first parameter when that is null; the old
   implementation walked getClientIps() and skipped private addresses. Where code depended on that
   skip, resolve the address explicitly and pass it to Geolocator::lookup().
8. If the application defines its own geolocate macro on Illuminate\Http\Request, the package no longer
   overwrites it. Remove the local definition to get the package macro back.
9. Verify: search the codebase for Drivers\FakeGeolocator, Geolocator::fake, geolocate(,
   IPLocationDB\Geolocator, createReader( and getDatabasePath( and confirm nothing is left behind,
   then run the application's test suite (vendor/bin/pest, php artisan test or vendor/bin/phpunit —
   whichever this repository uses).

Finish by printing a migration report of the changes you actually made — not of files you only read:

- Files changed — a flat list of every file you edited, by path.
- Dependencies — the composer.json constraints you changed, and the update command you ran.
- Renames — every file where FakeGeolocator was renamed, listed by path.
- fake() calls — each call site you rewrote, with its before and after.
- Driver construction — each new IPLocationDB\Geolocator you replaced, and what you replaced it with.
- Driver extensions — any subclass or override you ported to the reader providers.
- geolocate() — each $request->geolocate() call site you reviewed, and whether it needed a change.
- Not migrated — anything you could not change, why, and what the developer has to do by hand.
- Verification — the search result, and the exact test command with its pass/fail summary.
```

### Required: update the import of the moved class

The fake moved to `SameOldNick\Geolocator\Drivers\Fake\FakeGeolocator`. The old
`SameOldNick\Geolocator\Drivers\FakeGeolocator` is gone rather than aliased, so an import of it is a
fatal error:

```php
// 1.x
use SameOldNick\Geolocator\Drivers\FakeGeolocator;

// 2.0
use SameOldNick\Geolocator\Drivers\Fake\FakeGeolocator;
```

That is the only class renamed out from under a released name. The reader seams — the `Reader` and
`ReaderProvider` contracts, `AbstractReaderProvider` and `FakeReader` — are new in 2.0 and have no 1.x
equivalent to update.

### Required: pass the fake's percentage by name

`Geolocator::fake()` takes the mocked results as its **first** parameter, so the chance of an empty
result has to be passed by name:

| 1.x                    | 2.0                                   |
| ---------------------- | ------------------------------------- |
| `Geolocator::fake(0)`  | `Geolocator::fake(chanceOfEmpty: 0)`  |
| `Geolocator::fake(50)` | `Geolocator::fake(chanceOfEmpty: 50)` |

`Geolocator::fake(0)` now passes `0` into an `array $mockedResults` parameter and throws a
`TypeError`. The mocked results themselves go in positionally or by name:

```php
Geolocator::fake(['8.8.8.8' => $result]);   // positional
Geolocator::fake(mockedResults: [...]);     // by name
```

### Required: the default chance of an empty result is now 0

`fake()` used to return an empty result for 10% of public addresses. It is deterministic now, which is
what makes a suite that does not pin an address reproducible. A test that relied on the old
occasional empty result has to ask for one:

```php
Geolocator::fake(chanceOfEmpty: 100); // every unmocked public address is empty

$driver->mock('8.8.8.8');             // or pin one address to null
```

### Required: `new FakeGeolocator()` takes both arguments

The constructor is now `(array $mockedResults, int $chanceOfEmpty)` and neither parameter has a
default, so `new FakeGeolocator(10)` fails on the type of the first argument. Prefer
`Geolocator::fake()`; if you construct the fake yourself, import the new name and pass both:

```php
use SameOldNick\Geolocator\Drivers\Fake\FakeGeolocator;

$driver = new FakeGeolocator([], chanceOfEmpty: 0);
```

### Required if you instantiated the driver yourself: resolve it from the container

`SameOldNick\Geolocator\Drivers\IPLocationDB\Geolocator` no longer accepts the config array — its
constructor takes no arguments — and it pulls each edition's reader provider out of the container on
every lookup, so it only works inside a booted application. Build it through the facade or the
container alias:

```php
Geolocator::lookup('8.8.8.8');        // facade (unchanged)
app('geolocator')->lookup('8.8.8.8'); // container alias
```

`new IPLocationDB\Geolocator` still constructs, and extra arguments are ignored rather than rejected,
so `new IPLocationDB\Geolocator($config)` no longer fails at the point of construction. The edition
paths come from `geolocator.drivers.iplocationdb.editions`; what fails instead is the first lookup,
and only outside a booted application.

If you extended the driver, `createReader()` and `getDatabasePath()` are gone from it. A database path
now belongs to a reader provider — `Drivers\IPLocationDB\Providers\CountryReaderProvider` and its city
and ASN siblings, extending `AbstractReaderProvider`, where `createReader()` and `getReader()` live —
and those providers are registered as scoped bindings in the service provider, so that is the seam to
replace.

### No action: readers are cached per scope

The reason for the refactor: the driver used to keep one reader per database path in a `static`
property for the life of the process, so a queue worker or an Octane process kept the handle it opened
at boot and had to be restarted after `geolocator:update-iplocationdb` replaced a file. Each edition's
reader is now held by a scoped provider, which the container discards between requests and jobs, so
the next lookup opens the new file. Nothing to migrate — but if your own documentation says to restart
workers after updating, that advice is obsolete. Only a process that keeps a reader open across the
update (a long-running console command, or the update job itself) still serves the old file until it
finishes.

### Check your use of `$request->geolocate()`

The macro stays backwards compatible for `$request->geolocate()` and `$request->geolocate('127.0.0.1')`,
and now also takes an edition and a driver:

```php
$request->geolocate();                        // the full result
$request->geolocate(edition: 'country');      // one edition
$request->geolocate(driver: 'custom');        // a non-default driver
$request->geolocate('127.0.0.1', 'city');     // positional: default address, then edition
```

Two behaviour changes are worth checking:

- It resolves the client address with `Request::ip()` and falls back to `$default` when that is `null`.
  The previous implementation walked `getClientIps()` and skipped private addresses, so a request whose
  first client address was private used to fall through to the next entry in the list. `ip()` already
  honours your trusted-proxy configuration, and a private address is now returned to the driver, which
  resolves it to an empty result. If you depended on the skip, resolve the address yourself and pass it
  to `Geolocator::lookup()`.
- If your application defines its own `geolocate` macro on `Request`, the package leaves it alone
  instead of overwriting it. Remove your definition to get the package one back.

### Check your dependency constraint

`maxmind-db/reader` is now constrained to `^1.14.0` instead of `*`. If your root `composer.json` pins an
older 1.x release, raise it before updating, or the solver will refuse:

```bash
composer update maxmind-db/reader
```

### New in 2.0

Nothing here is required, but it is why you are upgrading:

- The fake can pin results by exact address, glob pattern (`'8.8.8.*'`) or the `'*'` wildcard, and the
  most specific match wins — an exact address first, then a glob, then the wildcard. A mock may also be
  a closure receiving the looked up address and the calling method name (`lookup`, `lookupCountry`,
  `lookupCity` or `lookupAsn`).
- The `geolocate` request macro can perform a single-edition lookup, or use a custom driver.
- `SameOldNick\Geolocator\Drivers\IPLocationDB\Contracts\ReaderProvider` (with `AbstractReaderProvider`
  and the three edition providers) is available if you want to control how a database file is opened,
  and `Drivers\IPLocationDB\Contracts\Reader` is the interface the driver consumes — implemented by
  the real `Drivers\IPLocationDB\Reader` and by the new `Drivers\Fake\FakeReader`, which share their
  mock matching with the fake geolocator through `Drivers\Fake\Concerns\MocksResults`.

### Checklist

- [ ] Replace `Geolocator::fake($percentage)` with `Geolocator::fake(chanceOfEmpty: $percentage)`.
- [ ] Update any `new FakeGeolocator(...)` call to `new FakeGeolocator([], $chanceOfEmpty)`.
- [ ] Point `Drivers\FakeGeolocator` imports at `Drivers\Fake\FakeGeolocator`.
- [ ] Stop instantiating `Drivers\IPLocationDB\Geolocator` yourself — it resolves its readers from
      the container — and resolve it through the facade or the `geolocator` alias instead.
- [ ] Re-check tests that relied on the fake returning an empty result 10% of the time.
- [ ] Raise a `maxmind-db/reader` constraint below `^1.14.0`.
