<?php

namespace SameOldNick\Geolocator\Tests\Unit;

use InvalidArgumentException;
use SameOldNick\Geolocator\ClientLocatorManager;
use SameOldNick\Geolocator\ClientLocators\Headers\HeadersClientLocator;
use SameOldNick\Geolocator\ClientLocators\IpClientLocator;
use SameOldNick\Geolocator\Contracts\ClientLocator as ClientLocatorContract;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Tests\Fixtures\RecordingClientLocator;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * Client locator resolution: which locator the manager picks and how it delegates.
 *
 * @internal
 */
class ClientLocatorManagerTest extends TestCase
{
    /**
     * Ensure the manager is a client locator itself, so it can be type-hinted as one.
     */
    public function test_the_manager_implements_the_client_locator_contract(): void
    {
        $this->assertInstanceOf(ClientLocatorContract::class, $this->manager());
    }

    /**
     * Ensure the container, the string alias and the contract resolve the same scoped manager.
     */
    public function test_the_container_aliases_resolve_the_same_manager(): void
    {
        $manager = $this->manager();

        $this->assertSame($manager, $this->app->make('client-locator'));
        $this->assertSame($manager, $this->app->make(ClientLocatorContract::class));
    }

    /**
     * Ensure the default client locator is the IP locator shipped in the config.
     */
    public function test_the_default_client_locator_comes_from_config(): void
    {
        $this->assertSame('ip', $this->manager()->getDefaultDriver());
        $this->assertInstanceOf(IpClientLocator::class, $this->manager()->driver());
    }

    /**
     * Ensure the headers client locator can be selected through the config.
     */
    public function test_the_headers_client_locator_can_be_selected_through_config(): void
    {
        config()->set('geolocator.client', 'headers');
        $this->manager()->forgetDrivers();

        $this->assertSame('headers', $this->manager()->getDefaultDriver());
        $this->assertInstanceOf(HeadersClientLocator::class, $this->manager()->driver());
    }

    /**
     * Ensure a client locator registered with extend() can be selected by name.
     */
    public function test_a_client_locator_registered_with_extend_can_be_selected_by_name(): void
    {
        $recorder = $this->useRecordingClientLocator();

        $this->assertSame('recording', $this->manager()->getDefaultDriver());
        $this->assertSame($recorder, $this->manager()->driver());
    }

    /**
     * Ensure every lookup method is delegated to the active client locator and its result is
     * returned untouched.
     */
    public function test_lookup_methods_delegate_to_the_active_client_locator(): void
    {
        $recorder = $this->useRecordingClientLocator();

        $this->assertSame($recorder->result, $this->manager()->lookup());
        $this->assertSame($recorder->result, $this->manager()->lookupCountry());
        $this->assertSame($recorder->result, $this->manager()->lookupCity());
        $this->assertSame($recorder->result, $this->manager()->lookupAsn());

        $this->assertSame(['lookup', 'lookupCountry', 'lookupCity', 'lookupAsn'], $recorder->calls);
    }

    /**
     * Ensure an explicitly named client locator wins over the configured default.
     */
    public function test_an_explicit_driver_argument_overrides_the_configured_default(): void
    {
        $default = $this->useRecordingClientLocator('default-recording');
        $explicit = $this->useRecordingClientLocator('explicit-recording', makeDefault: false);

        $this->assertSame($explicit, $this->manager()->driver('explicit-recording'));

        $this->assertSame($explicit->result, $this->manager()->driver('explicit-recording')->lookup());
        $this->assertSame(['lookup'], $explicit->calls);
        $this->assertSame([], $default->calls);
    }

    /**
     * Ensure a resolved client locator is reused for its name.
     */
    public function test_client_locator_instances_are_cached_per_name(): void
    {
        $this->assertSame($this->manager()->driver('ip'), $this->manager()->driver('ip'));
    }

    /**
     * Ensure an unsupported client locator name fails loudly.
     */
    public function test_an_unsupported_client_locator_fails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [bogus] not supported.');

        $this->manager()->driver('bogus');
    }

    /**
     * Ensure a missing client locator configuration fails loudly rather than silently picking one.
     */
    public function test_a_missing_client_locator_configuration_fails(): void
    {
        config()->set('geolocator.client', null);
        $this->manager()->forgetDrivers();

        $this->expectException(InvalidArgumentException::class);

        $this->manager()->driver();
    }

    /**
     * Ensure the manager is scoped: one instance per request, a fresh one afterwards.
     */
    public function test_the_manager_is_scoped_to_the_request_lifecycle(): void
    {
        $first = $this->app->make(ClientLocatorManager::class);

        $this->assertSame($first, $this->app->make(ClientLocatorManager::class));

        $this->app->forgetScopedInstances();

        $this->assertNotSame($first, $this->app->make(ClientLocatorManager::class));
    }

    /**
     * Ensure a cached client locator is discarded by forgetDrivers(), so it is built again.
     */
    public function test_forget_drivers_discards_the_cached_client_locator(): void
    {
        $resolved = 0;
        $result = $this->cannedResult();

        // Manager::extend() rebinds the creator to the manager, so nothing may be read from $this here.
        $this->manager()->extend('counting', function () use (&$resolved, $result) {
            $resolved++;

            return new RecordingClientLocator($result);
        });

        config()->set('geolocator.client', 'counting');

        $this->manager()->driver();
        $this->manager()->driver();

        $this->assertSame(1, $resolved);

        $this->manager()->forgetDrivers();
        $this->manager()->driver();

        $this->assertSame(2, $resolved);
    }

    /**
     * Ensure resolution follows the config: each configured name keeps its own cached instance.
     */
    public function test_changing_the_config_selects_the_newly_configured_locator(): void
    {
        $recorder = $this->useRecordingClientLocator();

        $this->assertSame($recorder, $this->manager()->driver());

        config()->set('geolocator.client', 'headers');

        $this->assertInstanceOf(HeadersClientLocator::class, $this->manager()->driver());

        config()->set('geolocator.client', 'recording');

        $this->assertSame($recorder, $this->manager()->driver());
    }

    /**
     * Resolve the scoped manager under test.
     */
    protected function manager(): ClientLocatorManager
    {
        return $this->app->make(ClientLocatorManager::class);
    }

    /**
     * Register a recording client locator and, unless told otherwise, make it the configured default.
     */
    protected function useRecordingClientLocator(string $driver = 'recording', bool $makeDefault = true): RecordingClientLocator
    {
        $recorder = new RecordingClientLocator($this->cannedResult());

        $this->manager()->extend($driver, fn () => $recorder);

        if ($makeDefault) {
            config()->set('geolocator.client', $driver);
        }

        $this->manager()->forgetDrivers();

        return $recorder;
    }

    /**
     * Create a result that a client locator can return.
     */
    protected function cannedResult(): LocationResult
    {
        return new LocationResult(
            ipAddress: '8.8.8.8',
            country: null,
            city: null,
            asn: null,
        );
    }
}
