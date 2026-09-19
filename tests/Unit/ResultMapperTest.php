<?php

namespace SameOldNick\Geolocator\Tests\Unit;

use SameOldNick\Geolocator\Drivers\IPLocationDB\ResultMapper;
use SameOldNick\Geolocator\Tests\TestCase;

class ResultMapperTest extends TestCase
{
    /**
     * Test mapping a complete record.
     */
    public function test_maps_all_records_into_location_result(): void
    {
        $mapper = new ResultMapper;

        $result = $mapper->mapResult('8.8.8.8', [
            'country' => [
                'country_code' => 'US',
            ],
            'city' => [
                'country_code' => 'US',
                'city' => 'Austin',
                'state1' => 'Texas',
                'state2' => null,
                'latitude' => 30.2672,
                'longitude' => -97.7431,
                'timezone' => 'America/Chicago',
            ],
            'asn' => [
                'autonomous_system_number' => 15169,
                'autonomous_system_organization' => 'Google LLC',
            ],
        ]);

        $this->assertSame('8.8.8.8', $result->ipAddress);
        $this->assertSame('US', $result->country?->countryCode);
        $this->assertSame('United States', $result->country?->countryName);
        $this->assertSame('Austin', $result->city?->city);
        $this->assertSame('Texas', $result->city?->state1);
        $this->assertSame(30.2672, $result->city?->latitude);
        $this->assertSame(-97.7431, $result->city?->longitude);
        $this->assertSame('America/Chicago', $result->city?->timezone);
        $this->assertSame(15169, $result->asn?->asn);
        $this->assertSame('Google LLC', $result->asn?->organization);
    }

    /**
     * Test mapping empty records produces no results.
     */
    public function test_maps_empty_records_into_empty_location_result(): void
    {
        $mapper = new ResultMapper;

        $result = $mapper->mapResult('8.8.4.4', []);

        $this->assertFalse($result->hasResults());
        $this->assertNull($result->country);
        $this->assertNull($result->city);
        $this->assertNull($result->asn);
    }
}
