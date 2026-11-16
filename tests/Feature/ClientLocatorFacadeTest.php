<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Facade;
use ReflectionMethod;
use SameOldNick\Geolocator\ClientLocatorManager;
use SameOldNick\Geolocator\ClientLocators\Fake\FakeClientLocator;
use SameOldNick\Geolocator\ClientLocators\IpClientLocator;
use SameOldNick\Geolocator\Contracts\ClientLocator as ClientLocatorContract;
use SameOldNick\Geolocator\DTOs\AsnResult;
use SameOldNick\Geolocator\DTOs\CityResult;
use SameOldNick\Geolocator\DTOs\CountryResult;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Facades\ClientLocator;
use SameOldNick\Geolocator\Tests\Fixtures\RecordingClientLocator;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * Covers the client locator facade: how it resolves, and how fake() swaps it out.
 *
 * @internal
 */
class ClientLocatorFacadeTest extends TestCase
{
    /**
     * Ensure the facade accessor points at the client locator contract.
     */
    public function test_facade_accessor_is_the_client_locator_contract(): void
    {
        // getFacadeAccessor() is protected, so it has to be read reflectively.
        $accessor = (new ReflectionMethod(ClientLocator::class, 'getFacadeAccessor'))->invoke(null);

        $this->assertSame(ClientLocatorContract::class, $accessor);
    }

    /**
     * Ensure the facade resolves the same scoped manager as the container.
     */
    public function test_facade_resolves_the_scoped_manager(): void
    {
        $this->assertSame($this->app->make(ClientLocatorManager::class), ClientLocator::getFacadeRoot());
    }

    /**
     * Ensure the facade reads the configured client locator, defaulting to the IP locator.
     */
    public function test_facade_resolves_the_configured_client_locator(): void
    {
        $this->assertSame('ip', ClientLocator::getDefaultDriver());
        $this->assertInstanceOf(IpClientLocator::class, ClientLocator::driver());
    }

    /**
     * Ensure lookups are delegated to the configured client locator.
     */
    public function test_lookups_are_delegated_to_the_configured_client_locator(): void
    {
        $recorder = new RecordingClientLocator($this->fullResult());

        ClientLocator::extend('recording', fn () => $recorder);

        config()->set('geolocator.client', 'recording');
        ClientLocator::forgetDrivers();

        $this->assertSame($recorder->result, ClientLocator::lookup());
        $this->assertSame($recorder->result, ClientLocator::lookupCountry());
        $this->assertSame($recorder->result, ClientLocator::lookupCity());
        $this->assertSame($recorder->result, ClientLocator::lookupAsn());

        $this->assertSame(['lookup', 'lookupCountry', 'lookupCity', 'lookupAsn'], $recorder->calls);
    }

    /**
     * Ensure nothing is faked until fake() is called.
     */
    public function test_the_facade_is_not_faking_by_default(): void
    {
        $this->assertFalse(ClientLocator::isFake());
        $this->assertNotInstanceOf(FakeClientLocator::class, ClientLocator::getFacadeRoot());
    }

    /**
     * Ensure fake() swaps the root for a fake client locator and reports it as faking.
     */
    public function test_fake_swaps_the_root_with_a_fake_client_locator(): void
    {
        $fake = ClientLocator::fake();

        $this->assertInstanceOf(FakeClientLocator::class, $fake);
        $this->assertInstanceOf(ClientLocatorContract::class, $fake);
        $this->assertSame($fake, ClientLocator::getFacadeRoot());
        $this->assertTrue(ClientLocator::isFake());
    }

    /**
     * Ensure fake() binds the fake into the container under the contract.
     */
    public function test_fake_binds_the_fake_into_the_container(): void
    {
        $fake = ClientLocator::fake();

        // Facade::swap() rebinds the contract, so anything resolving it gets the fake too.
        $this->assertSame($fake, $this->app->make(ClientLocatorContract::class));
    }

    /**
     * Ensure fake() only rebinds the contract, leaving the concrete manager resolution untouched.
     */
    public function test_fake_leaves_the_manager_binding_untouched(): void
    {
        $manager = $this->app->make(ClientLocatorManager::class);

        $fake = ClientLocator::fake();

        $this->assertSame($manager, $this->app->make(ClientLocatorManager::class));
        $this->assertSame($manager, $this->app->make('client-locator'));
        $this->assertSame($fake, $this->app->make(ClientLocatorContract::class));
    }

    /**
     * Ensure the fake answers a full lookup with the pinned result.
     */
    public function test_fake_returns_the_pinned_result_for_a_full_lookup(): void
    {
        $result = $this->fullResult();

        $fake = ClientLocator::fake($result);

        $this->assertSame($result, $fake->result);
        $this->assertSame($result, ClientLocator::lookup());
    }

    /**
     * Ensure a single-edition lookup clears the editions that were not requested, as the real
     * locators do, while keeping the reported address.
     */
    public function test_fake_clears_unrequested_editions(): void
    {
        ClientLocator::fake($this->fullResult());

        $country = ClientLocator::lookupCountry();
        $this->assertSame('8.8.8.8', $country->ipAddress);
        $this->assertSame('US', $country->country?->countryCode);
        $this->assertNull($country->city);
        $this->assertNull($country->asn);

        $city = ClientLocator::lookupCity();
        $this->assertSame('8.8.8.8', $city->ipAddress);
        $this->assertSame('Ashburn', $city->city?->city);
        $this->assertNull($city->country);
        $this->assertNull($city->asn);

        $asn = ClientLocator::lookupAsn();
        $this->assertSame('8.8.8.8', $asn->ipAddress);
        $this->assertSame(15169, $asn->asn?->asn);
        $this->assertNull($asn->country);
        $this->assertNull($asn->city);
    }

    /**
     * Ensure fake() with no result answers every lookup with an empty result.
     */
    public function test_fake_defaults_to_an_empty_result(): void
    {
        ClientLocator::fake();

        $result = ClientLocator::lookup();

        $this->assertSame('0.0.0.0', $result->ipAddress);
        $this->assertFalse($result->hasResults());
        $this->assertFalse(ClientLocator::lookupCountry()->hasResults());
        $this->assertFalse(ClientLocator::lookupCity()->hasResults());
        $this->assertFalse(ClientLocator::lookupAsn()->hasResults());
    }

    /**
     * Ensure the real manager can be swapped back in after faking.
     */
    public function test_the_real_manager_can_be_swapped_back_in(): void
    {
        $manager = $this->app->make(ClientLocatorManager::class);

        ClientLocator::fake();

        $this->assertTrue(ClientLocator::isFake());

        ClientLocator::swap($manager);

        $this->assertFalse(ClientLocator::isFake());
        $this->assertSame($manager, ClientLocator::getFacadeRoot());
        $this->assertSame($manager, $this->app->make(ClientLocatorContract::class));
    }

    /**
     * Ensure the advertised ClientLocator alias resolves to the facade.
     */
    public function test_the_client_locator_alias_resolves_to_the_facade(): void
    {
        $aliases = $this->packageAliases();

        $this->assertArrayHasKey('ClientLocator', $aliases);
        $this->assertSame(ClientLocator::class, $aliases['ClientLocator']);

        // The root package is not a dependency of itself, so its own aliases are never registered
        // here; hand it to the loader the way Illuminate\Foundation\Bootstrap\RegisterFacades does.
        AliasLoader::getInstance()->alias('ClientLocator', $aliases['ClientLocator']);

        // The alias name is the class name the loader hooks into, so referring to it as a string
        // avoids importing a class that only exists once this class_alias() has run.
        $alias = 'ClientLocator';

        $this->assertTrue(class_exists($alias), 'The ClientLocator alias could not be loaded.');
        $this->assertTrue(is_a($alias, ClientLocator::class, true), 'The alias does not resolve to the facade.');
        $this->assertSame($this->app->make(ClientLocatorManager::class), $alias::getFacadeRoot());
    }

    /**
     * Ensure every advertised alias points at an existing facade, the new one included.
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
     * Create a result carrying every edition.
     */
    protected function fullResult(): LocationResult
    {
        return new LocationResult(
            ipAddress: '8.8.8.8',
            country: CountryResult::create('US'),
            city: CityResult::create(
                countryCode: 'US',
                city: 'Ashburn',
                state1: 'Virginia',
                state2: null,
                latitude: 39.0438,
                longitude: -77.4874,
                timezone: 'America/New_York',
            ),
            asn: AsnResult::create(15169, 'Google LLC'),
        );
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
