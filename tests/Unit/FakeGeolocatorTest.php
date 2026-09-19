<?php

namespace SameOldNick\Geolocator\Tests\Unit;

use SameOldNick\Geolocator\Drivers\FakeGeolocator;
use SameOldNick\Geolocator\DTOs\AsnResult;
use SameOldNick\Geolocator\DTOs\CityResult;
use SameOldNick\Geolocator\DTOs\CountryResult;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Tests\TestCase;

class FakeGeolocatorTest extends TestCase
{
    /**
     * Test that mocked results are returned for a specific IP.
     */
    public function test_mocked_result_is_returned(): void
    {
        $geolocator = new FakeGeolocator(0);
        $result = new LocationResult(
            ipAddress: '8.8.8.8',
            country: CountryResult::create('US'),
            city: CityResult::create('US', 'Austin', 'Texas', null, 30.2672, -97.7431, 'America/Chicago'),
            asn: AsnResult::create(15169, 'Google LLC'),
        );

        $geolocator->mock('8.8.8.8', $result);

        $this->assertSame($result, $geolocator->lookup('8.8.8.8'));
    }

    /**
     * Test that private IPs return empty results.
     */
    public function test_private_ip_returns_empty_result(): void
    {
        $geolocator = new FakeGeolocator(0);

        $result = $geolocator->lookup('192.168.1.10');

        $this->assertFalse($result->hasResults());
        $this->assertSame('192.168.1.10', $result->ipAddress);
        $this->assertNull($result->country);
        $this->assertNull($result->city);
        $this->assertNull($result->asn);
    }

    /**
     * Test that chance of empty can force empty results.
     */
    public function test_chance_of_empty_can_force_empty_results(): void
    {
        $geolocator = new FakeGeolocator(100);

        $result = $geolocator->lookup('8.8.8.8');

        $this->assertFalse($result->hasResults());
    }

    /**
     * Test that lookup methods return expected result shape.
     */
    public function test_lookup_methods_return_expected_shape(): void
    {
        $geolocator = new FakeGeolocator(0);

        $countryResult = $geolocator->lookupCountry('8.8.4.4');
        $this->assertTrue($countryResult->hasResults());
        $this->assertNotNull($countryResult->country);
        $this->assertNull($countryResult->city);
        $this->assertNull($countryResult->asn);

        $cityResult = $geolocator->lookupCity('8.8.4.4');
        $this->assertTrue($cityResult->hasResults());
        $this->assertNull($cityResult->country);
        $this->assertNotNull($cityResult->city);
        $this->assertNull($cityResult->asn);

        $asnResult = $geolocator->lookupAsn('8.8.4.4');
        $this->assertTrue($asnResult->hasResults());
        $this->assertNull($asnResult->country);
        $this->assertNull($asnResult->city);
        $this->assertNotNull($asnResult->asn);
    }
}
