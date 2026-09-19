<?php

namespace SameOldNick\Geolocator\Tests\Unit;

use SameOldNick\Geolocator\Support\CountryHelper;
use SameOldNick\Geolocator\Support\IPAddressHelper;
use SameOldNick\Geolocator\Tests\TestCase;

class HelpersTest extends TestCase
{
    /**
     * Test IP address helper utilities.
     */
    public function test_ip_address_helper_detects_ip_types(): void
    {
        $this->assertTrue(IPAddressHelper::isValidIPAddress('8.8.8.8'));
        $this->assertTrue(IPAddressHelper::isIPv4('8.8.8.8'));
        $this->assertFalse(IPAddressHelper::isIPv6('8.8.8.8'));
        $this->assertFalse(IPAddressHelper::isPrivateIPAddress('8.8.8.8'));

        $this->assertTrue(IPAddressHelper::isValidIPAddress('2001:4860:4860::8888'));
        $this->assertTrue(IPAddressHelper::isIPv6('2001:4860:4860::8888'));
        $this->assertFalse(IPAddressHelper::isIPv4('2001:4860:4860::8888'));
        $this->assertFalse(IPAddressHelper::isPrivateIPAddress('2001:4860:4860::8888'));

        $this->assertTrue(IPAddressHelper::isPrivateIPAddress('192.168.1.1'));
        $this->assertFalse(IPAddressHelper::isValidIPAddress('not-an-ip'));
    }

    /**
     * Test country helper lookup methods.
     */
    public function test_country_helper_lookup_methods(): void
    {
        $this->assertTrue(CountryHelper::isCountryCodeValid('US'));

        $country = CountryHelper::getCountry('US');
        $this->assertNotNull($country);
        $this->assertSame('United States', CountryHelper::getCountryName('US'));

        $coordinates = CountryHelper::getCountryCoordinates('US');
        $this->assertNotNull($coordinates);
        $this->assertIsFloat($coordinates['latitude']);
        $this->assertIsFloat($coordinates['longitude']);
    }
}
