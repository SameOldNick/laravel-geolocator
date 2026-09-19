<?php

namespace SameOldNick\Geolocator\Tests\Unit;

use SameOldNick\Geolocator\DTOs\CountryResult;
use SameOldNick\Geolocator\Support\CountryHelper;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * @internal
 */
class CountryHelperTest extends TestCase
{
    /**
     * Ensure country codes are trimmed, upper cased and mapped onto current ISO codes.
     */
    public function test_country_codes_are_normalized_and_mapped(): void
    {
        $this->assertSame('US', CountryHelper::normalizeCountryCode('us'));

        $this->assertSame('GB', CountryHelper::normalizeCountryCode('UK'));
        $this->assertSame('GB', CountryHelper::normalizeCountryCode(' uk '));
        $this->assertSame('GR', CountryHelper::normalizeCountryCode('EL'));
        $this->assertSame('CW', CountryHelper::normalizeCountryCode('AN'));
        $this->assertSame('RS', CountryHelper::normalizeCountryCode('CS'));

        $this->assertSame('ZZ', CountryHelper::normalizeCountryCode('zz'));
    }

    /**
     * Ensure unknown country codes resolve to null rather than throwing.
     */
    public function test_unknown_country_codes_resolve_to_null(): void
    {
        $this->assertFalse(CountryHelper::isCountryCodeValid('ZZ'));
        $this->assertNull(CountryHelper::getCountry('ZZ'));
        $this->assertNull(CountryHelper::getCountryName('ZZ'));
        $this->assertNull(CountryHelper::getCountryCoordinates('ZZ'));
    }

    /**
     * Ensure coordinates are returned as floats.
     */
    public function test_country_coordinates_are_cast_to_floats(): void
    {
        $coordinates = CountryHelper::getCountryCoordinates('US');

        $this->assertIsArray($coordinates);
        $this->assertIsFloat($coordinates['latitude']);
        $this->assertIsFloat($coordinates['longitude']);
    }

    /**
     * Ensure the country list is read once and reused.
     */
    public function test_the_country_list_is_cached(): void
    {
        $countries = CountryHelper::getCountries();

        $this->assertArrayHasKey('US', $countries);
        $this->assertSame($countries, CountryHelper::getCountries());
        $this->assertSame(array_keys($countries), CountryHelper::getCountryCodes());
    }

    /**
     * Ensure a legacy code creates a result for the country it maps onto.
     */
    public function test_mapped_codes_create_a_result_for_the_target_country(): void
    {
        $result = CountryResult::create('uk');

        $this->assertNotNull($result);
        $this->assertSame('GB', $result->countryCode);
        $this->assertSame(CountryHelper::getCountryName('GB'), $result->countryName);
        $this->assertSame(CountryHelper::getCountryCoordinates('GB'), $result->getCoordinates());
    }
}
