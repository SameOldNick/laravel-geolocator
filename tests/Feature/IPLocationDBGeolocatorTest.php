<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use InvalidArgumentException;
use Mockery;
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Providers\CountryReaderProvider;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Reader;
use SameOldNick\Geolocator\Facades\Geolocator;
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
        $provider = new class extends CountryReaderProvider
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
     * Ensure the configured path is read for the edition and version that was asked for.
     */
    public function test_the_provider_resolves_the_path_for_the_ip_version(): void
    {
        config()->set('geolocator.drivers.iplocationdb.editions', [
            'country' => [
                'ipv4' => 'storage/app/geolocation/country-ipv4.mmdb',
                'ipv6' => 'storage/app/geolocation/country-ipv6.mmdb',
            ],
        ]);

        $provider = new class extends CountryReaderProvider
        {
            public function pathFor(int $ipVersion): string
            {
                return $this->getDatabasePath($this->getEdition(), $ipVersion);
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
