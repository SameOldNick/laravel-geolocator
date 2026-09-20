<?php

namespace SameOldNick\Geolocator\Tests\Unit;

use SameOldNick\Geolocator\Drivers\FakeGeolocator;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Geolocator as IpLocationGeolocator;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Facades\Geolocator;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * Driver resolution, exercised through the facade.
 *
 * @internal
 */
class GeolocatorManagerTest extends TestCase
{
    /**
     * Ensure the default driver follows the configured driver name.
     */
    public function test_default_driver_respects_config(): void
    {
        config()->set('geolocator.driver', 'iplocationdb');
        Geolocator::forgetDrivers();

        $this->assertSame('iplocationdb', Geolocator::getDefaultDriver());
        $this->assertInstanceOf(IpLocationGeolocator::class, Geolocator::driver());
    }

    /**
     * Ensure a driver registered with extend() becomes selectable by name.
     */
    public function test_extended_drivers_are_selectable_by_name(): void
    {
        $driver = new FakeGeolocator(0);

        Geolocator::extend('extended', fn () => $driver);

        config()->set('geolocator.driver', 'extended');
        Geolocator::forgetDrivers();

        $this->assertSame('extended', Geolocator::getDefaultDriver());
        $this->assertSame($driver, Geolocator::driver());
    }

    /**
     * Ensure lookups are delegated to the configured driver.
     */
    public function test_lookup_delegates_to_the_configured_driver(): void
    {
        $driver = new FakeGeolocator(0);

        $result = new LocationResult(
            ipAddress: '8.8.8.8',
            country: null,
            city: null,
            asn: null,
        );

        $driver->mock('8.8.8.8', $result);

        Geolocator::extend('extended', fn () => $driver);

        config()->set('geolocator.driver', 'extended');
        Geolocator::forgetDrivers();

        $this->assertSame($result, Geolocator::lookup('8.8.8.8'));
    }
}
