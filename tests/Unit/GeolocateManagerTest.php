<?php

namespace SameOldNick\Geolocator\Tests\Unit;

use SameOldNick\Geolocator\Drivers\FakeGeolocator;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Geolocator as IpLocationGeolocator;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Geolocate;
use SameOldNick\Geolocator\Tests\TestCase;

class GeolocateManagerTest extends TestCase
{
    /**
     * Test default driver respects configuration.
     */
    public function test_default_driver_respects_config(): void
    {
        config()->set('geolocation.driver', 'iplocationdb');

        $geolocate = new Geolocate($this->app);

        $this->assertSame('iplocationdb', $geolocate->getDefaultDriver());
    }

    /**
     * Test fake toggle changes default driver.
     */
    public function test_fake_toggle_changes_default_driver(): void
    {
        config()->set('geolocation.driver', 'iplocationdb');

        $geolocate = new Geolocate($this->app);

        $this->assertFalse($geolocate->isFake());
        $geolocate->fake();
        $this->assertTrue($geolocate->isFake());
        $this->assertSame('fake', $geolocate->getDefaultDriver());

        $geolocate->fake(false);
        $this->assertFalse($geolocate->isFake());
        $this->assertSame('iplocationdb', $geolocate->getDefaultDriver());
    }

    /**
     * Test driver instances are created for default drivers.
     */
    public function test_driver_instances_created(): void
    {
        config()->set('geolocation.driver', 'iplocationdb');

        $geolocate = new Geolocate($this->app);
        $this->assertInstanceOf(IpLocationGeolocator::class, $geolocate->driver());

        $geolocate = new Geolocate($this->app);
        $geolocate->fake();
        $this->assertInstanceOf(FakeGeolocator::class, $geolocate->driver());
    }

    /**
     * Test lookup delegates to the active driver.
     */
    public function test_lookup_delegates_to_driver(): void
    {
        $geolocate = new Geolocate($this->app);
        $geolocate->fake();

        $driver = $geolocate->driver('fake');
        $result = new LocationResult(
            ipAddress: '8.8.8.8',
            country: null,
            city: null,
            asn: null,
        );

        $driver->mock('8.8.8.8', $result);

        $this->assertSame($result, $geolocate->lookup('8.8.8.8'));
    }
}
