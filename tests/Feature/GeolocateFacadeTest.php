<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use Illuminate\Support\Facades\Facade;
use ReflectionMethod;
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\Drivers\FakeGeolocator;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Facades\Geolocate;
use SameOldNick\Geolocator\Geolocate as GeolocateManager;
use SameOldNick\Geolocator\Tests\Fixtures\RecordingGeolocator;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * @internal
 */
class GeolocateFacadeTest extends TestCase
{
    /**
     * Ensure the facade accessor points at the geolocator contract.
     */
    public function test_facade_accessor_is_the_geolocator_contract(): void
    {
        // getFacadeAccessor() is protected, so it has to be read reflectively.
        $accessor = (new ReflectionMethod(Geolocate::class, 'getFacadeAccessor'))->invoke(null);

        $this->assertSame(GeolocatorContract::class, $accessor);
    }

    /**
     * Ensure the facade resolves the same manager instance as the container.
     */
    public function test_facade_resolves_the_manager_singleton(): void
    {
        $this->assertSame($this->app->make(GeolocateManager::class), Geolocate::getFacadeRoot());
    }

    /**
     * Ensure every lookup method on the facade reaches the active driver.
     */
    public function test_facade_delegates_lookups_to_the_active_driver(): void
    {
        $geolocate = $this->app->make(GeolocateManager::class);
        $geolocate->fake();

        $driver = $geolocate->driver('fake');
        $this->assertInstanceOf(FakeGeolocator::class, $driver);

        $result = new LocationResult(
            ipAddress: '8.8.8.8',
            country: null,
            city: null,
            asn: null,
        );

        $driver->mock('8.8.8.8', $result);

        $this->assertSame($result, Geolocate::lookup('8.8.8.8'));
        $this->assertSame($result, Geolocate::lookupCountry('8.8.8.8'));
        $this->assertSame($result, Geolocate::lookupCity('8.8.8.8'));
        $this->assertSame($result, Geolocate::lookupAsn('8.8.8.8'));
    }

    /**
     * Ensure state toggled through the container is visible through the facade.
     */
    public function test_fake_toggled_on_the_container_is_visible_through_the_facade(): void
    {
        $this->app->make(GeolocateManager::class)->fake();

        $this->assertInstanceOf(FakeGeolocator::class, Geolocate::driver());
        $this->assertSame('fake', Geolocate::getDefaultDriver());
        $this->assertTrue(Geolocate::getFacadeRoot()->isFake());
    }

    /**
     * Ensure faking can be toggled through the facade itself.
     */
    public function test_fake_can_be_toggled_through_the_facade(): void
    {
        Geolocate::fake();

        $this->assertInstanceOf(FakeGeolocator::class, Geolocate::driver());
        $this->assertTrue(Geolocate::getFacadeRoot()->isFake());

        Geolocate::fake(false);

        $this->assertFalse(Geolocate::getFacadeRoot()->isFake());
    }

    /**
     * Ensure a custom driver registered through the facade can be selected by name.
     */
    public function test_a_custom_driver_can_be_registered_through_the_facade(): void
    {
        $result = new LocationResult(
            ipAddress: '8.8.8.8',
            country: null,
            city: null,
            asn: null,
        );

        Geolocate::extend('custom', fn () => new RecordingGeolocator($result));

        config()->set('geolocator.driver', 'custom');

        Geolocate::forgetDrivers();

        $this->assertSame($result, Geolocate::lookup('8.8.8.8'));
    }

    /**
     * Characterisation test: Facade::isFake() is public static and shadows the manager's isFake(),
     * so Geolocate::isFake() reports the framework's fake state instead of the package's toggle.
     * Delete this test once the collision is resolved.
     */
    public function test_is_fake_is_shadowed_by_the_framework_facade(): void
    {
        Geolocate::fake();

        $this->assertTrue(Geolocate::getFacadeRoot()->isFake());
        $this->assertFalse(Geolocate::isFake());
        $this->assertSame(
            Facade::class,
            (new ReflectionMethod(Geolocate::class, 'isFake'))->getDeclaringClass()->getName(),
        );
    }
}
