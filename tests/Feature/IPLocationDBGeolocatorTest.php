<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use InvalidArgumentException;
use Mockery;
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Providers\CountryReaderProvider;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Reader;
use SameOldNick\Geolocator\Facades\Geolocator;
use SameOldNick\Geolocator\Tests\Fixtures\RecordingReaderProvider;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * The default driver, reached through the facade. Real lookups need a MaxMind .mmdb fixture, which
 * neither the package nor the reader dependency ships, so this covers the wiring and the failure mode.
 *
 * @internal
 */
class IPLocationDBGeolocatorTest extends TestCase
{
    /**
     * Point the country edition at the given paths and resolve the driver through the facade.
     *
     * @param  array<string, string>  $paths  Local database path per IP version
     */
    protected function driverFor(array $paths): GeolocatorContract
    {
        config()->set('geolocator.drivers.iplocationdb.editions', ['country' => $paths]);
        Geolocator::forgetDrivers();

        return Geolocator::driver('iplocationdb');
    }

    /**
     * A path that is guaranteed not to exist.
     */
    protected function missingPath(): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'geolocator-missing-'.uniqid().'.mmdb';
    }

    /**
     * Ensure the IP version picks the matching database, which is the driver's only real
     * logic and is otherwise unreachable without a valid database file.
     */
    public function test_the_ip_version_selects_the_matching_database(): void
    {
        $provider = new class('storage/app/geolocation/country-ipv4.mmdb', 'storage/app/geolocation/country-ipv6.mmdb') extends CountryReaderProvider
        {
            /** @var array<int, int> */
            public array $requestedVersions = [];

            public function getReader(int $ipVersion): Reader
            {
                $this->requestedVersions[] = $ipVersion;

                return Mockery::mock(Reader::class)->shouldIgnoreMissing();
            }
        };

        $this->app->instance(CountryReaderProvider::class, $provider);

        $driver = $this->driverFor([
            'ipv4' => 'storage/app/geolocation/country-ipv4.mmdb',
            'ipv6' => 'storage/app/geolocation/country-ipv6.mmdb',
        ]);

        $driver->lookupCountry('8.8.8.8');
        $driver->lookupCountry('2001:4860:4860::8888');

        $this->assertSame([4, 6], $provider->requestedVersions);
    }

    /**
     * Ensure each lookup is served by the reader for its own IP version, even when the provider has
     * already handed out a reader for the other version in the same scope. Unlike the test above,
     * this leaves getReader() alone, so the provider's own reader cache is exercised.
     */
    public function test_each_address_uses_the_reader_for_its_own_ip_version(): void
    {
        $country = new RecordingReaderProvider(
            edition: 'country',
            databasePathv4: 'storage/app/geolocation/country-ipv4.mmdb',
            databasePathv6: 'storage/app/geolocation/country-ipv6.mmdb',
        );

        $this->app->instance(CountryReaderProvider::class, $country);

        $driver = $this->driverFor([
            'ipv4' => 'storage/app/geolocation/country-ipv4.mmdb',
            'ipv6' => 'storage/app/geolocation/country-ipv6.mmdb',
        ]);

        $driver->lookupCountry('8.8.8.8');
        $driver->lookupCountry('2001:4860:4860::8888');

        $this->assertSame([
            'storage/app/geolocation/country-ipv4.mmdb',
            'storage/app/geolocation/country-ipv6.mmdb',
        ], $country->requestedPaths);
    }

    /**
     * Ensure the reader providers live for the scope rather than for the process: a lookup after the
     * scope has been flushed must go through a new provider, and so open a new reader. That is what
     * lets a queue worker or an Octane process pick up a replaced database without a restart.
     */
    public function test_a_flushed_scope_gives_the_next_lookup_a_new_reader_provider(): void
    {
        /** @var array<int, RecordingReaderProvider> $providers */
        $providers = [];

        $this->app->scoped(CountryReaderProvider::class, function () use (&$providers) {
            $provider = new RecordingReaderProvider(databasePathv4: 'storage/app/geolocation/country-ipv4.mmdb');

            $providers[] = $provider;

            return $provider;
        });

        $driver = $this->driverFor([
            'ipv4' => 'storage/app/geolocation/country-ipv4.mmdb',
        ]);

        $driver->lookupCountry('8.8.8.8');
        $driver->lookupCountry('9.9.9.9');

        $this->assertCount(1, $providers, 'two lookups in one scope should share a provider');
        $this->assertCount(1, $providers[0]->requestedPaths, 'and share the reader it opened');

        // What the queue worker does between jobs, and Octane does between requests.
        $this->app->forgetScopedInstances();

        $driver->lookupCountry('1.1.1.1');

        $this->assertCount(2, $providers, 'a flushed scope should build a new provider');
        $this->assertCount(1, $providers[1]->requestedPaths, 'and open a reader of its own');
    }

    /**
     * Ensure the configured path is read for the edition and version that was asked for.
     */
    public function test_the_provider_resolves_the_path_for_the_ip_version(): void
    {
        $provider = new class('storage/app/geolocation/country-ipv4.mmdb', 'storage/app/geolocation/country-ipv6.mmdb') extends CountryReaderProvider
        {
            public function pathFor(int $ipVersion): string
            {
                return $this->getDatabasePath($ipVersion);
            }
        };

        $this->assertSame('storage/app/geolocation/country-ipv4.mmdb', $provider->pathFor(4));
        $this->assertSame('storage/app/geolocation/country-ipv6.mmdb', $provider->pathFor(6));
    }

    /**
     * Ensure a missing database file surfaces an error instead of returning an empty result.
     */
    public function test_a_missing_database_file_surfaces_an_error(): void
    {
        $this->driverFor([
            'ipv4' => $this->missingPath(),
            'ipv6' => $this->missingPath(),
        ]);

        $this->expectException(InvalidArgumentException::class);

        Geolocator::lookupCountry('8.8.8.8');
    }
}
