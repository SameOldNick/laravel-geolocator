<?php

namespace SameOldNick\Geolocator\Tests\Unit;

use ReflectionClass;
use SameOldNick\Geolocator\Drivers\Fake\FakeReader;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Providers\AsnReaderProvider;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Providers\CityReaderProvider;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Providers\CountryReaderProvider;
use SameOldNick\Geolocator\Tests\Fixtures\RecordingReaderProvider;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * @internal
 */
class ReaderProviderTest extends TestCase
{
    /**
     * Ensure the getDatabasePath() method returns the correct path based on configuration.
     */
    public function test_get_database_path_returns_correct_path(): void
    {
        $path = storage_path('app/geolocation/custom-country.mmdb');
        config()->set('geolocator.drivers.iplocationdb.editions.country.ipv4', $path);

        $provider = $this->app->make(CountryReaderProvider::class);

        $reflection = new ReflectionClass($provider);

        $databasePath = $reflection->getMethod('getDatabasePath')->invoke($provider, 'country', 4);

        $this->assertSame($path, $databasePath);
    }

    /**
     * Ensure the configured path is resolved for the IP version that was asked for.
     */
    public function test_create_reader_resolves_the_path_for_the_ip_version(): void
    {
        config()->set('geolocator.drivers.iplocationdb.editions.fake', [
            'ipv4' => 'storage/app/geolocation/fake-ipv4.mmdb',
            'ipv6' => 'storage/app/geolocation/fake-ipv6.mmdb',
        ]);

        $provider = new RecordingReaderProvider;

        $ipv4 = $provider->createReader(4);
        $ipv6 = $provider->createReader(6);

        $this->assertInstanceOf(FakeReader::class, $ipv4);
        $this->assertInstanceOf(FakeReader::class, $ipv6);

        $this->assertSame('storage/app/geolocation/fake-ipv4.mmdb', $ipv4->databasePath);
        $this->assertSame('storage/app/geolocation/fake-ipv6.mmdb', $ipv6->databasePath);

        $this->assertSame([
            'storage/app/geolocation/fake-ipv4.mmdb',
            'storage/app/geolocation/fake-ipv6.mmdb',
        ], $provider->requestedPaths);
    }

    /**
     * Ensure a reader is only created once and reused, so repeated lookups do not reopen the file.
     */
    public function test_get_reader_reuses_the_reader_created_for_the_same_ip_version(): void
    {
        config()->set('geolocator.drivers.iplocationdb.editions.fake.ipv4', 'storage/app/geolocation/fake-ipv4.mmdb');

        $provider = new RecordingReaderProvider;

        $reader = $provider->getReader(4);

        $this->assertInstanceOf(FakeReader::class, $reader);
        $this->assertSame($reader, $provider->getReader(4));
        $this->assertSame('storage/app/geolocation/fake-ipv4.mmdb', $reader->databasePath);
        $this->assertSame(['storage/app/geolocation/fake-ipv4.mmdb'], $provider->requestedPaths);
    }

    /**
     * Ensure the reader handed out is the one the provider created, so a test can swap the database.
     */
    public function test_get_reader_returns_the_injected_reader(): void
    {
        config()->set('geolocator.drivers.iplocationdb.editions.fake.ipv4', 'storage/app/geolocation/fake-ipv4.mmdb');

        $injected = new FakeReader('storage/app/geolocation/fake-ipv4.mmdb');

        $provider = new RecordingReaderProvider('fake', $injected);

        $this->assertSame($injected, $provider->getReader(4));
    }

    /**
     * Ensure each concrete provider resolves the edition it is responsible for.
     */
    public function test_each_provider_resolves_its_own_edition(): void
    {
        config()->set('geolocator.drivers.iplocationdb.editions', [
            'country' => [
                'ipv4' => 'storage/app/geolocation/country-ipv4.mmdb',
                'ipv6' => 'storage/app/geolocation/country-ipv6.mmdb',
            ],
            'city' => ['ipv4' => 'storage/app/geolocation/city-ipv4.mmdb'],
            'asn' => ['ipv4' => 'storage/app/geolocation/asn-ipv4.mmdb'],
        ]);

        $country = new class extends CountryReaderProvider
        {
            public function pathFor(int $ipVersion): string
            {
                return $this->getDatabasePath($this->getEdition(), $ipVersion);
            }
        };

        $city = new class extends CityReaderProvider
        {
            public function pathFor(int $ipVersion): string
            {
                return $this->getDatabasePath($this->getEdition(), $ipVersion);
            }
        };

        $asn = new class extends AsnReaderProvider
        {
            public function pathFor(int $ipVersion): string
            {
                return $this->getDatabasePath($this->getEdition(), $ipVersion);
            }
        };

        $this->assertSame('storage/app/geolocation/country-ipv4.mmdb', $country->pathFor(4));
        $this->assertSame('storage/app/geolocation/country-ipv6.mmdb', $country->pathFor(6));
        $this->assertSame('storage/app/geolocation/city-ipv4.mmdb', $city->pathFor(4));
        $this->assertSame('storage/app/geolocation/asn-ipv4.mmdb', $asn->pathFor(4));
    }

    /**
     * Ensure a missing configuration key falls back to the given default.
     */
    public function test_a_missing_configuration_key_falls_back_to_the_default(): void
    {
        config()->set('geolocator.drivers.iplocationdb.editions.country.ipv4', 'storage/app/geolocation/country-ipv4.mmdb');

        $provider = new class extends CountryReaderProvider
        {
            public function configFor(string $key, mixed $default = null): mixed
            {
                return $this->getConfig($key, $default);
            }
        };

        $this->assertSame('storage/app/geolocation/country-ipv4.mmdb', $provider->configFor('editions.country.ipv4'));
        $this->assertSame('fallback', $provider->configFor('editions.country.ipv7', 'fallback'));
    }
}
