<?php

namespace SameOldNick\Geolocator\Tests\Unit;

use SameOldNick\Geolocator\Drivers\FakeGeolocator;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Geolocator as IpLocationGeolocator;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Facades\Geolocate;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * Driver resolution, exercised through the facade.
 *
 * @internal
 */
class GeolocateManagerTest extends TestCase
{
    /**
     * Ensure the default driver follows the configured driver name.
     */
    public function test_default_driver_respects_config(): void
    {
        config()->set('geolocator.driver', 'iplocationdb');
        Geolocate::forgetDrivers();

        $this->assertSame('iplocationdb', Geolocate::getDefaultDriver());
        $this->assertInstanceOf(IpLocationGeolocator::class, Geolocate::driver());
    }

    /**
     * Ensure a driver registered with extend() becomes selectable by name.
     */
    public function test_extended_drivers_are_selectable_by_name(): void
    {
        $driver = new FakeGeolocator(0);

        Geolocate::extend('extended', fn () => $driver);

        config()->set('geolocator.driver', 'extended');
        Geolocate::forgetDrivers();

        $this->assertSame('extended', Geolocate::getDefaultDriver());
        $this->assertSame($driver, Geolocate::driver());
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

        Geolocate::extend('extended', fn () => $driver);

        config()->set('geolocator.driver', 'extended');
        Geolocate::forgetDrivers();

        $this->assertSame($result, Geolocate::lookup('8.8.8.8'));
    }
}
