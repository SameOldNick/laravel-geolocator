<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use InvalidArgumentException;
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\Drivers\Fake\FakeReader;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Geolocator as IpLocationDbGeolocator;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Providers\CountryReaderProvider;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Facades\Geolocator;
use SameOldNick\Geolocator\GeolocatorManager;
use SameOldNick\Geolocator\Tests\Fixtures\RecordingGeolocator;
use SameOldNick\Geolocator\Tests\Fixtures\RecordingReaderProvider;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * Covers lookups and driver resolution through the facade.
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
     * Register a recording driver and make it the configured default driver.
     */
    protected function useRecordingDriver(string $driver = 'recording', bool $makeDefault = true): RecordingGeolocator
    {
        $recorder = new RecordingGeolocator($this->cannedResult());

        Geolocator::extend($driver, fn () => $recorder);

        if ($makeDefault) {
            config()->set('geolocator.driver', $driver);
        }

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
     * Ensure the ip-location-db driver resolves its reader through the provider, and queries the
     * instance the provider returns for the address's IP version.
     */
    public function test_iplocationdb_driver_gets_its_reader_from_the_provider(): void
    {
        $path = storage_path('app/geolocation/custom-country.mmdb');
        config()->set('geolocator.drivers.iplocationdb.editions.fake.ipv4', $path);

        $reader = new FakeReader;
        $provider = new RecordingReaderProvider(createReader: fn () => $reader);

        $this->app->forgetInstance(CountryReaderProvider::class);
        $this->app->instance(CountryReaderProvider::class, $provider);

        $reader->mock(self::PUBLIC_IP, ['country_code' => 'US']);

        $driver = Geolocator::driver('iplocationdb');
        $this->assertInstanceOf(IpLocationDbGeolocator::class, $driver);

        $result = $driver->lookupCountry(self::PUBLIC_IP);

        $this->assertSame([$path], $provider->requestedPaths);
        $this->assertSame('US', $result->country?->countryCode);
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
}
