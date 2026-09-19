<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use Illuminate\Http\Request;
use ReflectionProperty;
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\Drivers\FakeGeolocator;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Geolocator as IpLocationDbGeolocator;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Geolocate;
use SameOldNick\Geolocator\Tests\Fixtures\RecordingGeolocator;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * @internal
 */
class GeolocateTest extends TestCase
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
     * Resolve the manager from the container.
     */
    protected function geolocate(): Geolocate
    {
        return $this->app->make(Geolocate::class);
    }

    /**
     * Register a recording driver and make it the configured default driver.
     */
    protected function useRecordingDriver(): RecordingGeolocator
    {
        $recorder = new RecordingGeolocator($this->cannedResult());

        $geolocate = $this->geolocate();
        $geolocate->extend('recording', fn () => $recorder);

        config()->set('geolocator.driver', 'recording');
        $geolocate->forgetDrivers();

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
     * Ensure the manager is bound as a singleton.
     */
    public function test_geolocate_is_registered_as_a_singleton(): void
    {
        $this->assertSame($this->geolocate(), $this->geolocate());
    }

    /**
     * Ensure both container aliases resolve to the singleton manager.
     */
    public function test_geolocate_aliases_resolve_to_the_same_instance(): void
    {
        $geolocate = $this->geolocate();

        $this->assertSame($geolocate, $this->app->make('geolocate'));
        $this->assertSame($geolocate, $this->app->make(GeolocatorContract::class));
    }

    /**
     * Ensure the default driver is read from the configured driver name.
     */
    public function test_default_driver_comes_from_config(): void
    {
        config()->set('geolocator.driver', 'iplocationdb');
        $geolocate = $this->geolocate();
        $geolocate->forgetDrivers();

        $this->assertSame('iplocationdb', $geolocate->getDefaultDriver());
        $this->assertInstanceOf(IpLocationDbGeolocator::class, $geolocate->driver());

        config()->set('geolocator.driver', 'fake');
        $geolocate->forgetDrivers();

        $this->assertSame('fake', $geolocate->getDefaultDriver());
        $this->assertInstanceOf(FakeGeolocator::class, $geolocate->driver());
    }

    /**
     * Ensure the default driver falls back to the fake driver when faking.
     */
    public function test_fake_toggle_overrides_the_configured_driver(): void
    {
        config()->set('geolocator.driver', 'iplocationdb');

        $geolocate = $this->geolocate();
        $geolocate->forgetDrivers();
        $this->assertInstanceOf(IpLocationDbGeolocator::class, $geolocate->driver());

        $geolocate->fake();
        $this->assertSame('fake', $geolocate->getDefaultDriver());
        $this->assertInstanceOf(FakeGeolocator::class, $geolocate->driver());

        $geolocate->fake(false);
        $geolocate->forgetDrivers();
        $this->assertSame('iplocationdb', $geolocate->getDefaultDriver());
        $this->assertInstanceOf(IpLocationDbGeolocator::class, $geolocate->driver());
    }

    /**
     * Ensure faking is visible through every alias of the manager.
     */
    public function test_fake_toggle_is_shared_across_the_container(): void
    {
        $this->geolocate()->fake();

        $this->assertTrue($this->app->make('geolocate')->isFake());
        $this->assertTrue($this->app->make(GeolocatorContract::class)->isFake());
        $this->assertInstanceOf(FakeGeolocator::class, $this->app->make(GeolocatorContract::class)->driver());
    }

    /**
     * Ensure driver instances are reused per name.
     */
    public function test_driver_instances_are_cached_per_name(): void
    {
        $geolocate = $this->geolocate();

        $this->assertSame($geolocate->driver('fake'), $geolocate->driver('fake'));
        $this->assertNotSame($geolocate->driver('fake'), $geolocate->driver('iplocationdb'));
    }

    /**
     * Ensure the fake driver is created with the configured chance of an empty result.
     */
    public function test_fake_driver_receives_configured_chance_of_empty(): void
    {
        config()->set('geolocator.drivers.fake.chance_of_empty', 100);

        $geolocate = $this->geolocate();
        $geolocate->fake();
        $geolocate->forgetDrivers();

        $driver = $geolocate->driver();
        $this->assertInstanceOf(FakeGeolocator::class, $driver);
        $this->assertSame(100, $driver->chanceOfEmpty);
        $this->assertFalse($driver->lookup(self::PUBLIC_IP)->hasResults());
    }

    /**
     * Ensure the ip-location-db driver is created with the configured editions.
     */
    public function test_iplocationdb_driver_receives_editions_config(): void
    {
        $path = storage_path('app/geolocation/custom-country.mmdb');
        config()->set('geolocator.drivers.iplocationdb.editions.country.ipv4', $path);

        $geolocate = $this->geolocate();
        $geolocate->forgetDrivers();

        $driver = $geolocate->driver('iplocationdb');
        $this->assertInstanceOf(IpLocationDbGeolocator::class, $driver);

        $config = (new ReflectionProperty(IpLocationDbGeolocator::class, 'config'))->getValue($driver);

        $this->assertSame($path, $config['editions']['country']['ipv4']);
    }

    /**
     * Ensure lookup is delegated to the active driver and its result is returned.
     */
    public function test_lookup_delegates_to_the_active_driver(): void
    {
        $recorder = $this->useRecordingDriver();

        $this->assertSame($recorder->result, $this->geolocate()->lookup(self::PUBLIC_IP));
        $this->assertSame([['method' => 'lookup', 'ip' => self::PUBLIC_IP]], $recorder->calls);
    }

    /**
     * Ensure lookupCountry is delegated to the active driver.
     */
    public function test_lookup_country_delegates_to_the_active_driver(): void
    {
        $recorder = $this->useRecordingDriver();

        $this->assertSame($recorder->result, $this->geolocate()->lookupCountry(self::PUBLIC_IP));
        $this->assertSame([['method' => 'lookupCountry', 'ip' => self::PUBLIC_IP]], $recorder->calls);
    }

    /**
     * Ensure lookupCity is delegated to the active driver.
     */
    public function test_lookup_city_delegates_to_the_active_driver(): void
    {
        $recorder = $this->useRecordingDriver();

        $this->assertSame($recorder->result, $this->geolocate()->lookupCity(self::PUBLIC_IP));
        $this->assertSame([['method' => 'lookupCity', 'ip' => self::PUBLIC_IP]], $recorder->calls);
    }

    /**
     * Ensure lookupAsn is delegated to the active driver.
     */
    public function test_lookup_asn_delegates_to_the_active_driver(): void
    {
        $recorder = $this->useRecordingDriver();

        $this->assertSame($recorder->result, $this->geolocate()->lookupAsn(self::PUBLIC_IP));
        $this->assertSame([['method' => 'lookupAsn', 'ip' => self::PUBLIC_IP]], $recorder->calls);
    }

    /**
     * Ensure the request macro resolves the manager from the container when faking is enabled.
     */
    public function test_request_macro_uses_the_fake_driver_registered_on_the_manager(): void
    {
        $geolocate = $this->geolocate();
        $geolocate->fake();

        $driver = $geolocate->driver();
        $this->assertInstanceOf(FakeGeolocator::class, $driver);

        $result = $this->cannedResult();
        $driver->mock(self::PUBLIC_IP, $result);

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => self::PUBLIC_IP]);

        $this->assertSame($result, $request->geolocate());
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
}
