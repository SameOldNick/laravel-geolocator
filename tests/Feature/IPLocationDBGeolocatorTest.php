<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use InvalidArgumentException;
use ReflectionMethod;
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\Facades\Geolocate;
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
        Geolocate::forgetDrivers();

        return Geolocate::driver('iplocationdb');
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
        $driver = $this->driverFor([
            'ipv4' => 'storage/app/geolocation/country-ipv4.mmdb',
            'ipv6' => 'storage/app/geolocation/country-ipv6.mmdb',
        ]);

        $getDatabasePath = new ReflectionMethod($driver, 'getDatabasePath');

        $this->assertSame(
            'storage/app/geolocation/country-ipv4.mmdb',
            $getDatabasePath->invoke($driver, 'country', '8.8.8.8'),
        );
        $this->assertSame(
            'storage/app/geolocation/country-ipv6.mmdb',
            $getDatabasePath->invoke($driver, 'country', '2001:4860:4860::8888'),
        );
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

        Geolocate::lookupCountry('8.8.8.8');
    }
}
