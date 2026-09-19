<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use InvalidArgumentException;
use ReflectionMethod;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Geolocator;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * The default driver. Real lookups need a MaxMind .mmdb fixture, which neither the package
 * nor the reader dependency ships, so this covers the wiring and the failure mode.
 *
 * @internal
 */
class IPLocationDBGeolocatorTest extends TestCase
{
    /**
     * Build a driver for the given editions.
     *
     * @param  array<string, array<string, string>>  $editions
     */
    protected function driver(array $editions): Geolocator
    {
        return new Geolocator(['editions' => $editions]);
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
        $driver = $this->driver([
            'country' => [
                'ipv4' => 'storage/app/geolocation/country-ipv4.mmdb',
                'ipv6' => 'storage/app/geolocation/country-ipv6.mmdb',
            ],
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
        $driver = $this->driver([
            'country' => [
                'ipv4' => $this->missingPath(),
                'ipv6' => $this->missingPath(),
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        $driver->lookupCountry('8.8.8.8');
    }
}
