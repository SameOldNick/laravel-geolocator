<?php

namespace SameOldNick\Geolocator\Tests\Unit;

use SameOldNick\Geolocator\DTOs\CountryResult;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Tests\TestCase;

class LocationResultTest extends TestCase
{
    /**
     * Test hasResults returns false when all results are null.
     */
    public function test_has_results_returns_false_when_empty(): void
    {
        $result = new LocationResult(
            ipAddress: '8.8.8.8',
            country: null,
            city: null,
            asn: null,
        );

        $this->assertFalse($result->hasResults());
    }

    /**
     * Test hasResults returns true when any result exists.
     */
    public function test_has_results_returns_true_when_any_result_exists(): void
    {
        $result = new LocationResult(
            ipAddress: '8.8.8.8',
            country: CountryResult::create('US'),
            city: null,
            asn: null,
        );

        $this->assertTrue($result->hasResults());
    }

    /**
     * Test toArray returns the expected structure.
     */
    public function test_to_array_returns_expected_structure(): void
    {
        $result = new LocationResult(
            ipAddress: '8.8.8.8',
            country: CountryResult::create('US'),
            city: null,
            asn: null,
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('ipAddress', $array);
        $this->assertArrayHasKey('country', $array);
        $this->assertArrayHasKey('city', $array);
        $this->assertArrayHasKey('asn', $array);
        $this->assertSame('8.8.8.8', $array['ipAddress']);
        $this->assertSame('US', $array['country']['countryCode']);
        $this->assertNull($array['city']);
        $this->assertNull($array['asn']);
    }
}
