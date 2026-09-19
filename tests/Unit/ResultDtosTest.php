<?php

namespace SameOldNick\Geolocator\Tests\Unit;

use SameOldNick\Geolocator\DTOs\AsnResult;
use SameOldNick\Geolocator\DTOs\CityResult;
use SameOldNick\Geolocator\DTOs\CountryResult;
use SameOldNick\Geolocator\Support\CountryHelper;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * @internal
 */
class ResultDtosTest extends TestCase
{
    /**
     * Ensure an unknown country code produces no result.
     */
    public function test_country_result_returns_null_for_unknown_codes(): void
    {
        $this->assertNull(CountryResult::create('ZZ'));
    }

    /**
     * Ensure a country result exposes its code, name and coordinates.
     */
    public function test_country_result_exposes_its_data(): void
    {
        $result = CountryResult::create('US');

        $this->assertNotNull($result);
        $this->assertSame('US', (string) $result);
        $this->assertSame(['countryCode' => 'US', 'countryName' => 'United States'], $result->toArray());
        $this->assertSame(CountryHelper::getCountryCoordinates('US'), $result->getCoordinates());
    }

    /**
     * Ensure a city result exposes its fields and falls back to a placeholder without a city.
     */
    public function test_city_result_exposes_its_data(): void
    {
        $result = CityResult::create('US', 'Austin', 'Texas', null, 30.2672, -97.7431, 'America/Chicago');

        $this->assertNotNull($result);
        $this->assertSame('Austin', (string) $result);
        $this->assertSame('Austin', $result->toArray()['city']);
        $this->assertSame('Texas', $result->toArray()['state1']);
        $this->assertSame('America/Chicago', $result->toArray()['timezone']);

        $withoutCity = CityResult::create('US', null, null, null, null, null, null);

        $this->assertNotNull($withoutCity);
        $this->assertSame('Unknown City', (string) $withoutCity);
    }

    /**
     * Ensure a city result is rejected for an unknown country code.
     */
    public function test_city_result_returns_null_for_unknown_codes(): void
    {
        $this->assertNull(CityResult::create('ZZ', 'Nowhere', null, null, null, null, null));
    }

    /**
     * Characterisation test: CityResult::create() validates the raw code, while
     * CountryResult::create() normalises legacy codes first. A legacy code such as "UK"
     * therefore yields a country but no city. Delete this once the two agree.
     */
    public function test_city_result_rejects_legacy_codes_that_country_result_accepts(): void
    {
        $this->assertNotNull(CountryResult::create('UK'));
        $this->assertNull(CityResult::create('UK', 'London', null, null, null, null, null));
    }

    /**
     * Ensure an ASN result exposes its data.
     */
    public function test_asn_result_exposes_its_data(): void
    {
        $result = AsnResult::create(15169, 'Google LLC');

        $this->assertSame(15169, $result->asn);
        $this->assertSame('Google LLC', $result->organization);
        $this->assertSame(['asn' => 15169, 'organization' => 'Google LLC'], $result->toArray());
    }
}
