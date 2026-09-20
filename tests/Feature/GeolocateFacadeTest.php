<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use ReflectionMethod;
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\Drivers\FakeGeolocator;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Facades\Geolocate;
use SameOldNick\Geolocator\Geolocate as GeolocateManager;
use SameOldNick\Geolocator\Tests\Fixtures\RecordingGeolocator;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * Covers the facade itself: how it resolves, and how fake() swaps it out.
 *
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
     * Ensure nothing is faked until fake() is called.
     */
    public function test_the_facade_is_not_faking_by_default(): void
    {
        $this->assertFalse(Geolocate::isFake());
        $this->assertNotInstanceOf(FakeGeolocator::class, Geolocate::getFacadeRoot());
    }

    /**
     * Ensure fake() swaps the root for a fake geolocator and reports it as faking.
     */
    public function test_fake_swaps_the_root_with_a_fake_geolocator(): void
    {
        $fake = Geolocate::fake();

        $this->assertInstanceOf(FakeGeolocator::class, $fake);
        $this->assertSame($fake, Geolocate::getFacadeRoot());
        $this->assertTrue(Geolocate::isFake());
    }

    /**
     * Ensure fake() binds the fake into the container under the contract.
     */
    public function test_fake_binds_the_fake_into_the_container(): void
    {
        $fake = Geolocate::fake();

        // Facade::swap() rebinds the contract, so anything resolving it gets the fake too.
        $this->assertSame($fake, $this->app->make(GeolocatorContract::class));
    }

    /**
     * Ensure fake() only rebinds the contract, leaving the concrete manager resolution untouched.
     */
    public function test_fake_leaves_the_manager_binding_untouched(): void
    {
        $manager = $this->app->make(GeolocateManager::class);

        $fake = Geolocate::fake();

        $this->assertSame($manager, $this->app->make(GeolocateManager::class));
        $this->assertSame($manager, $this->app->make('geolocate'));
        $this->assertSame($fake, $this->app->make(GeolocatorContract::class));
    }

    /**
     * Ensure the chance of an empty result can be set through fake().
     */
    public function test_fake_honours_the_chance_of_empty(): void
    {
        $fake = Geolocate::fake(100);

        $this->assertSame(100, $fake->chanceOfEmpty);
        $this->assertFalse(Geolocate::lookup('8.8.8.8')->hasResults());
    }

    /**
     * Ensure the real manager can be swapped back in after faking.
     */
    public function test_the_real_manager_can_be_swapped_back_in(): void
    {
        $manager = $this->app->make(GeolocateManager::class);

        Geolocate::fake();

        $this->assertTrue(Geolocate::isFake());

        Geolocate::swap($this->app->make(GeolocateManager::class));

        $this->assertFalse(Geolocate::isFake());
        $this->assertSame($manager, Geolocate::getFacadeRoot());
        $this->assertSame($manager, $this->app->make(GeolocatorContract::class));
    }

    /**
     * Ensure mocked results are returned through the facade.
     */
    public function test_mocked_results_are_returned_through_the_facade(): void
    {
        $fake = Geolocate::fake();

        $result = new LocationResult(
            ipAddress: '8.8.8.8',
            country: null,
            city: null,
            asn: null,
        );

        $fake->mock('8.8.8.8', $result);

        $this->assertSame($result, Geolocate::lookup('8.8.8.8'));
        $this->assertSame($result, Geolocate::lookupCountry('8.8.8.8'));
        $this->assertSame($result, Geolocate::lookupCity('8.8.8.8'));
        $this->assertSame($result, Geolocate::lookupAsn('8.8.8.8'));
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
}
