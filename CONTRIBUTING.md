# Contributing

This file is for working on the package. What a _consumer_ needs — installation, configuration and
usage — is in [README.md](README.md), and it stays there.

## Requirements

- PHP 8.4
- Composer 2
- Optional: Xdebug or PCOV, for `composer test-coverage`
- Optional: `ext-bcmath` or `ext-gmp` (the pure PHP MaxMind decoder), or `ext-maxminddb` for faster
  lookups

No MaxMind database and no database server are needed. The suite never reads a real `.mmdb` file.

## Getting started

```bash
git clone https://github.com/SameOldNick/laravel-geolocator.git
cd laravel-geolocator
composer install
```

`composer install` prepares the Orchestra Testbench skeleton through its post-autoload-dump scripts.
From there:

```bash
composer build     # build the workbench demo application
composer serve     # build it and serve it (with the process timeout disabled)
composer artisan   # run Artisan inside the workbench application
```

The demo application lives in `workbench/`, and is wired up by `testbench.yaml`.

## Checks

| Command                                            | Purpose                            | Current state               |
| -------------------------------------------------- | ---------------------------------- | --------------------------- |
| `composer test`                                    | The Pest suite                     | Green — 97 tests, ~4s       |
| `composer test-coverage`                           | The same, with coverage            | Green, needs Xdebug or PCOV |
| `vendor/bin/pest tests/Feature/GeolocatorTest.php` | A single file                      | —                           |
| `composer format`                                  | Pint, **rewriting** files in place | —                           |
| `vendor/bin/pint --test`                           | Pint, check only                   | **Fails** — see below       |
| `vendor/bin/phpstan analyse src --memory-limit=1G` | PHPStan at level 7                 | **33 errors** — see below   |
| `composer analyse`, `composer lint`                | Wrappers around the above          | **Fail** — see below        |

A few things worth knowing before you conclude your change broke something:

- **PHPStan has no committed configuration.** `phpstan.neon` is listed in `.gitignore`, so a fresh
  clone has none; the invocation in the table assumes a local config with `paths: src`, `level: 7` and
  `tmpDir: build/phpstan`. `composer analyse` uses that file's paths, which include `routes` — a
  directory this package does not have — and aborts with `Path .../routes does not exist`. `composer
lint` chains Pint and PHPStan, so it fails while either does.
- **The baselines are not zero.** `vendor/bin/pint --test` already fails on `src/DTOs/CityResult.php`
  and `src/DTOs/CountryResult.php` (`line_ending`, `unary_operator_spaces`,
  `not_operator_with_successor_space`, `ordered_imports`), and PHPStan's 33 errors are pre-existing
  (for example `missingType.iterableValue` in `Support\CountryHelper`). Fixing either is a change of
  its own; do not fold it into an unrelated pull request.
- **Pass explicit paths to Pint.** A bare directory argument reformats every file beneath it,
  including files you never touched.
- **Line endings.** The working tree mixes CRLF and LF. Write new files with LF, or Pint's
  `line_ending` fixer will flag them.

## Continuous integration

`.github/workflows/tests.yml` runs on every push and pull request to `main`, on PHP 8.4 with Xdebug:

```bash
composer install -q --no-ansi --no-interaction --no-scripts --no-progress --prefer-dist
composer build
composer test -- --parallel --coverage-clover coverage.xml --log-junit junit.xml
```

Coverage and test results are both uploaded to Codecov. Note that the test step is
`continue-on-error: true`, so a failing suite still shows a green workflow — read the job output or
the Codecov report rather than trusting the tick.

## Test layout

- The runner is Pest, but the tests are plain PHPUnit classes: plain `public function test_*` methods,
  and the PHPUnit assertion API. Pest is not used for its `it()`/`test()` functions.
- `tests/TestCase.php` boots Orchestra Testbench, so the container, the config repository and
  `Geolocator::fake()` are available in every test.
- `tests/Feature/` covers the package through the container; `tests/Unit/` covers the DTOs, the
  helpers, the manager and the updater. Both directories are wired up in `phpunit.xml` by the
  `Test.php` suffix.
- `tests/Fixtures/` holds the doubles: `RecordingGeolocator` records the calls a driver receives, and
  `ScriptedUpdater` scripts the outcome of each download.
- `phpunit.xml` runs the suite in random order with `failOnWarning`, `failOnRisky` and
  `beStrictAboutOutputDuringTests` enabled, so a test that depends on another, or that echoes
  something, fails the run.
- When you swap `geolocator.driver` mid-test, call `Geolocator::forgetDrivers()` afterwards, or the
  manager keeps handing back the driver it already resolved.

## Documentation

- `README.md` is the consumer documentation and is what Packagist renders, so consumer-facing prose
  belongs there.
- `tests/Feature/ReadmeExamplesTest.php` executes the examples in the README. If you change documented
  behaviour, update the README and that test together.
- Keep consumer documentation in the repository rather than the GitHub wiki. A wiki is a separate
  repository, so it is not versioned alongside a tag, is not exercised by CI, and Packagist cannot
  render it — anything moved there stops being read.
- The Boost guideline at `resources/boost/guidelines/core.blade.php` is rendered as Blade by Boost,
  which silently skips a file that fails to render. Keep its snippets inside `@verbatim`, and render it
  by hand after editing. No agent skill is shipped with it deliberately: there is no multi-step
  authoring workflow for an agent to improvise.

## Gaps in the suite

Worth knowing before you assume something is covered:

- No `.mmdb` fixture is committed, so the ip-location-db driver is only covered through its path
  selection and its failure mode. A licence-compatible edition is needed to go further.
- Nothing asserts the README's reference tables or its manifest claims; they are maintained by hand.
- The Boost guideline is not covered by a test either.

## Pull requests

- Keep the suite green and the code style applied, and open the pull request against `main`.
- This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
  [Semantic Versioning](https://semver.org/): give any consumer-visible change an entry in
  [CHANGELOG.md](CHANGELOG.md).
- The label set used here is `bug`, `documentation`, `tests`, `ci`, `chore`, `enhancement`,
  `packaging`, `security`, `dx` and `good first issue`.
- Report vulnerabilities through the process in [SECURITY.md](SECURITY.md), not in a public issue.
