<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Facade;
use ReflectionMethod;
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\Drivers\FakeGeolocator;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Facades\Geolocator;
use SameOldNick\Geolocator\GeolocatorManager;
use SameOldNick\Geolocator\Tests\Fixtures\RecordingGeolocator;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * Covers the facade itself: how it resolves, and how fake() swaps it out.
 *
 * @internal
 */
class GeolocatorFacadeTest extends TestCase
{
    /**
     * Ensure the facade accessor points at the geolocator contract.
     */
    public function test_facade_accessor_is_the_geolocator_contract(): void
    {
        // getFacadeAccessor() is protected, so it has to be read reflectively.
        $accessor = (new ReflectionMethod(Geolocator::class, 'getFacadeAccessor'))->invoke(null);

        $this->assertSame(GeolocatorContract::class, $accessor);
    }

    /**
     * Ensure the facade resolves the same manager instance as the container.
     */
    public function test_facade_resolves_the_manager_singleton(): void
    {
        $this->assertSame($this->app->make(GeolocatorManager::class), Geolocator::getFacadeRoot());
    }

    /**
     * Ensure nothing is faked until fake() is called.
     */
    public function test_the_facade_is_not_faking_by_default(): void
    {
        $this->assertFalse(Geolocator::isFake());
        $this->assertNotInstanceOf(FakeGeolocator::class, Geolocator::getFacadeRoot());
    }

    /**
     * Ensure fake() swaps the root for a fake geolocator and reports it as faking.
     */
    public function test_fake_swaps_the_root_with_a_fake_geolocator(): void
    {
        $fake = Geolocator::fake();

        $this->assertInstanceOf(FakeGeolocator::class, $fake);
        $this->assertSame($fake, Geolocator::getFacadeRoot());
        $this->assertTrue(Geolocator::isFake());
    }

    /**
     * Ensure fake() binds the fake into the container under the contract.
     */
    public function test_fake_binds_the_fake_into_the_container(): void
    {
        $fake = Geolocator::fake();

        // Facade::swap() rebinds the contract, so anything resolving it gets the fake too.
        $this->assertSame($fake, $this->app->make(GeolocatorContract::class));
    }

    /**
     * Ensure fake() only rebinds the contract, leaving the concrete manager resolution untouched.
     */
    public function test_fake_leaves_the_manager_binding_untouched(): void
    {
        $manager = $this->app->make(GeolocatorManager::class);

        $fake = Geolocator::fake();

        $this->assertSame($manager, $this->app->make(GeolocatorManager::class));
        $this->assertSame($manager, $this->app->make('geolocator'));
        $this->assertSame($fake, $this->app->make(GeolocatorContract::class));
    }

    /**
     * Ensure the chance of an empty result can be set through fake().
     */
    public function test_fake_honours_the_chance_of_empty(): void
    {
        $fake = Geolocator::fake(100);

        $this->assertSame(100, $fake->chanceOfEmpty);
        $this->assertFalse(Geolocator::lookup('8.8.8.8')->hasResults());
    }

    /**
     * Ensure the real manager can be swapped back in after faking.
     */
    public function test_the_real_manager_can_be_swapped_back_in(): void
    {
        $manager = $this->app->make(GeolocatorManager::class);

        Geolocator::fake();

        $this->assertTrue(Geolocator::isFake());

        Geolocator::swap($this->app->make(GeolocatorManager::class));

        $this->assertFalse(Geolocator::isFake());
        $this->assertSame($manager, Geolocator::getFacadeRoot());
        $this->assertSame($manager, $this->app->make(GeolocatorContract::class));
    }

    /**
     * Ensure mocked results are returned through the facade.
     */
    public function test_mocked_results_are_returned_through_the_facade(): void
    {
        $fake = Geolocator::fake();

        $result = new LocationResult(
            ipAddress: '8.8.8.8',
            country: null,
            city: null,
            asn: null,
        );

        $fake->mock('8.8.8.8', $result);

        $this->assertSame($result, Geolocator::lookup('8.8.8.8'));
        $this->assertSame($result, Geolocator::lookupCountry('8.8.8.8'));
        $this->assertSame($result, Geolocator::lookupCity('8.8.8.8'));
        $this->assertSame($result, Geolocator::lookupAsn('8.8.8.8'));
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

        Geolocator::extend('custom', fn () => new RecordingGeolocator($result));

        config()->set('geolocator.driver', 'custom');

        Geolocator::forgetDrivers();

        $this->assertSame($result, Geolocator::lookup('8.8.8.8'));
    }

    /**
     * Ensure every alias the package advertises points at a class that exists and is a facade.
     */
    public function test_registered_aliases_point_at_real_facades(): void
    {
        $aliases = $this->packageAliases();

        $this->assertNotEmpty($aliases, 'The package advertises no facade aliases.');

        foreach ($aliases as $alias => $class) {
            $this->assertTrue(class_exists($class), "Alias [{$alias}] points at missing class [{$class}].");
            $this->assertTrue(
                is_subclass_of($class, Facade::class),
                "Alias [{$alias}] points at [{$class}], which is not a facade.",
            );
        }
    }

    /**
     * Ensure the advertised Geolocator alias resolves through Laravel's alias loader.
     */
    public function test_the_geolocator_alias_resolves_to_the_geolocator_facade(): void
    {
        $aliases = $this->packageAliases();

        $this->assertArrayHasKey('Geolocator', $aliases);

        // The root package is not a dependency of itself, so its own aliases are never registered
        // here; hand it to the loader the way Illuminate\Foundation\Bootstrap\RegisterFacades does.
        AliasLoader::getInstance()->alias('Geolocator', $aliases['Geolocator']);

        // The alias name is the class name the loader hooks into, so referring to it as a string
        // avoids importing a class that only exists once this class_alias() has run.
        $alias = 'Geolocator';

        $this->assertTrue(class_exists($alias), 'The Geolocator alias could not be loaded.');
        $this->assertTrue(is_a($alias, Geolocator::class, true), 'The alias does not resolve to the facade.');
        $this->assertSame($this->app->make(GeolocatorManager::class), $alias::getFacadeRoot());
    }

    /**
     * Read the facade aliases this package advertises to consuming applications.
     *
     * @return array<string, string>
     */
    protected function packageAliases(): array
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/composer.json');

        $this->assertIsString($contents, 'The package composer.json could not be read.');

        /** @var array{extra?: array{laravel?: array{aliases?: array<string, string>}}} $composer */
        $composer = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        return $composer['extra']['laravel']['aliases'] ?? [];
    }
}
