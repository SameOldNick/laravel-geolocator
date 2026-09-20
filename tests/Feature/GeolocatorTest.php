<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use Illuminate\Http\Request;
use InvalidArgumentException;
use ReflectionProperty;
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Geolocator as IpLocationDbGeolocator;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Facades\Geolocator;
use SameOldNick\Geolocator\GeolocatorManager;
use SameOldNick\Geolocator\Tests\Fixtures\RecordingGeolocator;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * Covers lookups, driver resolution and the request macro through the facade.
 *
 * @internal
 */
class GeolocatorTest extends TestCase
{
    /**
     * The IP address used by the delegation tests.
     */
    protected const PUBLIC_IP = '8.8.8.8';

    /**
     * Reset the static trusted proxy state owned by Symfony's request.
     */
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], -1);

        parent::tearDown();
    }

    /**
     * Register a recording driver and make it the configured default driver.
     */
    protected function useRecordingDriver(): RecordingGeolocator
    {
        $recorder = new RecordingGeolocator($this->cannedResult());

        Geolocator::extend('recording', fn () => $recorder);

        config()->set('geolocator.driver', 'recording');
        Geolocator::forgetDrivers();

        return $recorder;
    }

    /**
     * Create a result that a driver can return.
     */
    protected function cannedResult(): LocationResult
    {
        return new LocationResult(
            ipAddress: self::PUBLIC_IP,
            country: null,
            city: null,
            asn: null,
        );
    }

    /**
     * Ensure the facade and the container aliases resolve the same manager instance.
     */
    public function test_facade_and_container_aliases_share_the_manager(): void
    {
        $manager = $this->app->make(GeolocatorManager::class);

        $this->assertSame($manager, $this->app->make('geolocator'));
        $this->assertSame($manager, $this->app->make(GeolocatorContract::class));
        $this->assertSame($manager, Geolocator::getFacadeRoot());
    }

    /**
     * Ensure the default driver is read from the configured driver name.
     */
    public function test_default_driver_comes_from_config(): void
    {
        config()->set('geolocator.driver', 'iplocationdb');
        Geolocator::forgetDrivers();

        $this->assertSame('iplocationdb', Geolocator::getDefaultDriver());
        $this->assertInstanceOf(IpLocationDbGeolocator::class, Geolocator::driver());
    }

    /**
     * Ensure a driver registered with extend() can be selected through the config.
     */
    public function test_an_extended_driver_can_be_selected_through_config(): void
    {
        $recorder = $this->useRecordingDriver();

        $this->assertSame('recording', Geolocator::getDefaultDriver());
        $this->assertSame($recorder, Geolocator::driver());
    }

    /**
     * Ensure every lookup method is delegated to the active driver.
     */
    public function test_lookup_methods_delegate_to_the_active_driver(): void
    {
        $recorder = $this->useRecordingDriver();

        $this->assertSame($recorder->result, Geolocator::lookup(self::PUBLIC_IP));
        $this->assertSame($recorder->result, Geolocator::lookupCountry(self::PUBLIC_IP));
        $this->assertSame($recorder->result, Geolocator::lookupCity(self::PUBLIC_IP));
        $this->assertSame($recorder->result, Geolocator::lookupAsn(self::PUBLIC_IP));

        $this->assertSame([
            ['method' => 'lookup', 'ip' => self::PUBLIC_IP],
            ['method' => 'lookupCountry', 'ip' => self::PUBLIC_IP],
            ['method' => 'lookupCity', 'ip' => self::PUBLIC_IP],
            ['method' => 'lookupAsn', 'ip' => self::PUBLIC_IP],
        ], $recorder->calls);
    }

    /**
     * Ensure driver instances are reused per name.
     */
    public function test_driver_instances_are_cached_per_name(): void
    {
        $this->assertSame(Geolocator::driver('iplocationdb'), Geolocator::driver('iplocationdb'));
    }

    /**
     * Ensure the ip-location-db driver is created with the configured editions.
     */
    public function test_iplocationdb_driver_receives_editions_config(): void
    {
        $path = storage_path('app/geolocation/custom-country.mmdb');
        config()->set('geolocator.drivers.iplocationdb.editions.country.ipv4', $path);
        Geolocator::forgetDrivers();

        $driver = Geolocator::driver('iplocationdb');
        $this->assertInstanceOf(IpLocationDbGeolocator::class, $driver);

        $config = (new ReflectionProperty(IpLocationDbGeolocator::class, 'config'))->getValue($driver);

        $this->assertSame($path, $config['editions']['country']['ipv4']);
    }

    /**
     * Ensure an unsupported driver name fails loudly.
     */
    public function test_an_unsupported_driver_name_fails(): void
    {
        config()->set('geolocator.driver', 'redis');
        Geolocator::forgetDrivers();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('redis');

        Geolocator::lookup(self::PUBLIC_IP);
    }

    /**
     * Ensure a missing driver configuration fails loudly rather than silently defaulting.
     */
    public function test_a_missing_driver_configuration_fails(): void
    {
        config()->set('geolocator.driver', null);
        Geolocator::forgetDrivers();

        $this->expectException(InvalidArgumentException::class);

        Geolocator::lookup(self::PUBLIC_IP);
    }

    /**
     * Ensure the request macro resolves the configured driver.
     */
    public function test_request_macro_uses_the_configured_driver(): void
    {
        $recorder = $this->useRecordingDriver();

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => self::PUBLIC_IP]);

        $this->assertSame($recorder->result, $request->geolocate());
        $this->assertSame(self::PUBLIC_IP, $recorder->calls[0]['ip']);
    }

    /**
     * Ensure the request macro falls back to the default when the request has no address.
     */
    public function test_request_macro_uses_the_default_when_no_ip_is_available(): void
    {
        $recorder = $this->useRecordingDriver();

        // Symfony leaves REMOTE_ADDR unset for requests built outside a server context.
        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => null]);

        $this->assertSame($recorder->result, $request->geolocate());
        $this->assertSame('0.0.0.0', $recorder->calls[0]['ip']);
    }

    /**
     * Ensure the request macro honours a custom default.
     */
    public function test_request_macro_honours_a_custom_default(): void
    {
        $recorder = $this->useRecordingDriver();

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => null]);

        $this->assertSame($recorder->result, $request->geolocate('1.2.3.4'));
        $this->assertSame('1.2.3.4', $recorder->calls[0]['ip']);
    }

    /**
     * Ensure the request macro uses the forwarded public IP when the proxy is trusted.
     */
    public function test_request_macro_uses_the_forwarded_public_ip_from_a_trusted_proxy(): void
    {
        Request::setTrustedProxies(['10.0.0.5'], Request::HEADER_X_FORWARDED_FOR);

        $recorder = $this->useRecordingDriver();

        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => self::PUBLIC_IP,
        ]);

        $this->assertSame($recorder->result, $request->geolocate());
        $this->assertSame(self::PUBLIC_IP, $recorder->calls[0]['ip']);
    }

    /**
     * Ensure the request macro ignores a forwarded header sent by an untrusted proxy.
     */
    public function test_request_macro_ignores_the_forwarded_header_from_an_untrusted_proxy(): void
    {
        $recorder = $this->useRecordingDriver();

        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => self::PUBLIC_IP,
        ]);

        $this->assertSame($recorder->result, $request->geolocate());
        $this->assertSame('10.0.0.5', $recorder->calls[0]['ip']);
    }

    /**
     * Ensure the request macro looks up the request IP when no public IP is present.
     */
    public function test_request_macro_falls_back_to_the_request_ip(): void
    {
        $recorder = $this->useRecordingDriver();

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.5']);

        $this->assertSame($recorder->result, $request->geolocate());
        $this->assertSame('10.0.0.5', $recorder->calls[0]['ip']);
    }

    /**
     * Characterisation test: the macro resolves the manager class, so a facade fake does not reach
     * it — the macro keeps using the configured driver while facade calls go to the swapped fake.
     * Delete this once the macro resolves the contract instead.
     */
    public function test_request_macro_is_unaffected_by_a_facade_fake(): void
    {
        $recorder = $this->useRecordingDriver();

        Geolocator::fake();

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => self::PUBLIC_IP]);

        $this->assertSame($recorder->result, $request->geolocate());
        $this->assertSame([['method' => 'lookup', 'ip' => self::PUBLIC_IP]], $recorder->calls);

        $this->assertNotSame($recorder->result, Geolocator::lookup(self::PUBLIC_IP));
    }
}
