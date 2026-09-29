<?php

namespace SameOldNick\Geolocator\Tests\Unit;

use SameOldNick\Geolocator\Drivers\Fake\FakeGeolocator;
use SameOldNick\Geolocator\DTOs\AsnResult;
use SameOldNick\Geolocator\DTOs\CityResult;
use SameOldNick\Geolocator\DTOs\CountryResult;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * Covers the fake driver: mock matching and precedence, and the fallback data it generates.
 *
 * @internal
 */
class FakeGeolocatorTest extends TestCase
{
    /**
     * Test that mocked results are returned for a specific IP.
     */
    public function test_mocked_result_is_returned(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [],
            chanceOfEmpty: 0,
        );
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
        $geolocator = new FakeGeolocator(
            mockedResults: [],
            chanceOfEmpty: 0,
        );

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
        $geolocator = new FakeGeolocator(
            mockedResults: [],
            chanceOfEmpty: 100,
        );

        $result = $geolocator->lookup('8.8.8.8');

        $this->assertFalse($result->hasResults());
    }

    /**
     * Test that lookup methods return expected result shape.
     */
    public function test_lookup_methods_return_expected_shape(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [],
            chanceOfEmpty: 0,
        );

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

    /**
     * Test that mocking an address with null yields an empty result.
     */
    public function test_mocking_an_address_with_null_yields_an_empty_result(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [
                '8.8.8.8' => null,
            ],
            chanceOfEmpty: 0,
        );

        $result = $geolocator->lookup('8.8.8.8');

        $this->assertSame('8.8.8.8', $result->ipAddress);
        $this->assertFalse($result->hasResults());
    }

    /**
     * Test that mocking a specific address yields the mocked result value.
     */
    public function test_mocking_a_specific_address_yields_the_mocked_result_value(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [
                '8.8.8.8' => LocationResult::create(
                    ipAddress: '8.8.8.8',
                    country: CountryResult::create('US'),
                    city: CityResult::create('US', 'Ashburn', 'Virginia', null, 39.0438, -77.4874, 'America/New_York'),
                    asn: AsnResult::create(15169, 'Google LLC'),
                ),
            ],
            chanceOfEmpty: 100,
        );

        $result = $geolocator->lookup('8.8.8.8');

        $this->assertSame('8.8.8.8', $result->ipAddress);
        $this->assertTrue($result->hasResults());
    }

    /**
     * Test that a closure mock for a specific address is resolved.
     */
    public function test_mocking_a_specific_address_yields_the_mocked_result_closure(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [
                '8.8.8.8' => function () {
                    return LocationResult::create(
                        ipAddress: '8.8.8.8',
                        country: CountryResult::create('US'),
                        city: CityResult::create('US', 'Ashburn', 'Virginia', null, 39.0438, -77.4874, 'America/New_York'),
                        asn: AsnResult::create(15169, 'Google LLC'),
                    );
                },
            ],
            chanceOfEmpty: 100,
        );

        $result = $geolocator->lookup('8.8.8.8');

        $this->assertSame('8.8.8.8', $result->ipAddress);
        $this->assertTrue($result->hasResults());
    }

    /**
     * Test that a glob pattern mock is returned for a matching address.
     */
    public function test_mocking_a_glob_pattern_yields_the_mocked_result_value(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [
                '8.8.8.*' => LocationResult::create(
                    ipAddress: '8.8.8.8',
                    country: CountryResult::create('US'),
                    city: CityResult::create('US', 'Ashburn', 'Virginia', null, 39.0438, -77.4874, 'America/New_York'),
                    asn: AsnResult::create(15169, 'Google LLC'),
                ),
            ],
            chanceOfEmpty: 100,
        );

        $result = $geolocator->lookup('8.8.8.8');

        $this->assertSame('8.8.8.8', $result->ipAddress);
        $this->assertTrue($result->hasResults());
    }

    /**
     * Test that a closure mock for a glob pattern receives the looked up address.
     */
    public function test_mocking_a_glob_pattern_yields_the_mocked_result_closure(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [
                '8.8.8.*' => fn ($ip) => LocationResult::create(
                    ipAddress: $ip,
                    country: CountryResult::create('US'),
                    city: CityResult::create('US', 'Ashburn', 'Virginia', null, 39.0438, -77.4874, 'America/New_York'),
                    asn: AsnResult::create(15169, 'Google LLC'),
                ),
            ],
            chanceOfEmpty: 100,
        );

        $result = $geolocator->lookup('8.8.8.8');

        $this->assertSame('8.8.8.8', $result->ipAddress);
        $this->assertTrue($result->hasResults());
    }

    /**
     * Test that the wildcard mock is returned for any address.
     */
    public function test_mocking_a_wildcard_pattern_yields_the_mocked_result_value(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [
                '*' => LocationResult::create(
                    ipAddress: '8.8.8.8',
                    country: CountryResult::create('US'),
                    city: CityResult::create('US', 'Ashburn', 'Virginia', null, 39.0438, -77.4874, 'America/New_York'),
                    asn: AsnResult::create(15169, 'Google LLC'),
                ),
            ],
            chanceOfEmpty: 100,
        );

        $result = $geolocator->lookup('8.8.8.8');

        $this->assertSame('8.8.8.8', $result->ipAddress);
        $this->assertTrue($result->hasResults());
    }

    /**
     * Test that a closure mock for the wildcard receives the looked up address.
     */
    public function test_mocking_a_wildcard_pattern_yields_the_mocked_result_closure(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [
                '*' => fn ($ip) => LocationResult::create(
                    ipAddress: $ip,
                    country: CountryResult::create('US'),
                    city: CityResult::create('US', 'Ashburn', 'Virginia', null, 39.0438, -77.4874, 'America/New_York'),
                    asn: AsnResult::create(15169, 'Google LLC'),
                ),
            ],
            chanceOfEmpty: 100,
        );

        $result = $geolocator->lookup('8.8.8.8');

        $this->assertSame('8.8.8.8', $result->ipAddress);
        $this->assertTrue($result->hasResults());
    }

    /**
     * Test that an exact address takes precedence over glob and wildcard mocks.
     */
    public function test_mocking_a_specific_address_takes_precedence_over_patterns(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [
                '8.8.8.8' => fn ($ip) => LocationResult::create(
                    ipAddress: $ip,
                    country: CountryResult::create('US'),
                    city: CityResult::create('US', 'Ashburn', 'Virginia', null, 39.0438, -77.4874, 'America/New_York'),
                    asn: AsnResult::create(15169, 'Google LLC'),
                ),
                '8.*' => fn ($ip) => LocationResult::create(
                    ipAddress: '1.1.1.1',
                    country: CountryResult::create('US'),
                    city: CityResult::create('US', 'Ashburn', 'Virginia', null, 39.0438, -77.4874, 'America/New_York'),
                    asn: AsnResult::create(15169, 'Google LLC'),
                ),
                '*' => fn ($ip) => LocationResult::create(
                    ipAddress: '1.1.1.1',
                    country: CountryResult::create('US'),
                    city: CityResult::create('US', 'Ashburn', 'Virginia', null, 39.0438, -77.4874, 'America/New_York'),
                    asn: AsnResult::create(15169, 'Google LLC'),
                ),
            ],
            chanceOfEmpty: 100,
        );

        $result = $geolocator->lookup('8.8.8.8');

        $this->assertSame('8.8.8.8', $result->ipAddress);
        $this->assertTrue($result->hasResults());
    }

    /**
     * Test that a glob pattern takes precedence over the wildcard mock.
     */
    public function test_mocking_a_glob_pattern_takes_precedence_over_patterns(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [
                '8.*' => fn ($ip) => LocationResult::create(
                    ipAddress: $ip,
                    country: CountryResult::create('US'),
                    city: CityResult::create('US', 'Ashburn', 'Virginia', null, 39.0438, -77.4874, 'America/New_York'),
                    asn: AsnResult::create(15169, 'Google LLC'),
                ),
                '*' => fn ($ip) => LocationResult::create(
                    ipAddress: '1.1.1.1',
                    country: CountryResult::create('US'),
                    city: CityResult::create('US', 'Ashburn', 'Virginia', null, 39.0438, -77.4874, 'America/New_York'),
                    asn: AsnResult::create(15169, 'Google LLC'),
                ),
            ],
            chanceOfEmpty: 100,
        );

        $result = $geolocator->lookup('8.8.8.8');

        $this->assertSame('8.8.8.8', $result->ipAddress);
        $this->assertTrue($result->hasResults());
    }

    /**
     * Test that the wildcard mock is used when nothing more specific matches.
     */
    public function test_mocking_a_wildcard_pattern_is_used_when_nothing_more_specific_matches(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [
                '1.1.1.*' => fn ($ip) => LocationResult::create(
                    ipAddress: '1.1.1.1',
                    country: CountryResult::create('US'),
                    city: CityResult::create('US', 'Ashburn', 'Virginia', null, 39.0438, -77.4874, 'America/New_York'),
                    asn: AsnResult::create(15169, 'Google LLC'),
                ),
                '*' => fn ($ip) => LocationResult::create(
                    ipAddress: $ip,
                    country: CountryResult::create('US'),
                    city: CityResult::create('US', 'Ashburn', 'Virginia', null, 39.0438, -77.4874, 'America/New_York'),
                    asn: AsnResult::create(15169, 'Google LLC'),
                ),
            ],
            chanceOfEmpty: 100,
        );

        $result = $geolocator->lookup('8.8.8.8');

        $this->assertSame('8.8.8.8', $result->ipAddress);
        $this->assertTrue($result->hasResults());
    }

    /**
     * Test that an unmatched address still yields an empty result when the chance of empty is 100.
     */
    public function test_unmatched_address_yields_empty_result_when_chance_of_empty_is_100(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [
                '1.1.1.*' => fn ($ip) => LocationResult::create(
                    ipAddress: '1.1.1.1',
                    country: CountryResult::create('US'),
                    city: CityResult::create('US', 'Ashburn', 'Virginia', null, 39.0438, -77.4874, 'America/New_York'),
                    asn: AsnResult::create(15169, 'Google LLC'),
                ),
            ],
            chanceOfEmpty: 100,
        );

        $result = $geolocator->lookup('8.8.8.8');

        $this->assertSame('8.8.8.8', $result->ipAddress);
        $this->assertFalse($result->hasResults());
    }

    /**
     * Test that lookup() generates a result for every edition when nothing is mocked.
     */
    public function test_lookup_generates_a_result_for_every_edition(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [],
            chanceOfEmpty: 0,
        );

        $result = $geolocator->lookup('8.8.4.4');

        $this->assertSame('8.8.4.4', $result->ipAddress);
        $this->assertTrue($result->hasResults());
        $this->assertNotNull($result->country);
        $this->assertNotNull($result->city);
        $this->assertNotNull($result->asn);
    }

    /**
     * Test that a closure mock returning null yields an empty result.
     */
    public function test_mocking_an_address_with_a_closure_returning_null_yields_an_empty_result(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [
                '8.8.8.8' => fn () => null,
            ],
            chanceOfEmpty: 0,
        );

        $result = $geolocator->lookup('8.8.8.8');

        $this->assertSame('8.8.8.8', $result->ipAddress);
        $this->assertFalse($result->hasResults());
    }

    /**
     * Test that a closure mock receives the address and the name of the method being called.
     */
    public function test_a_closure_mock_receives_the_calling_method(): void
    {
        $methods = [];

        $geolocator = new FakeGeolocator(
            mockedResults: [
                '8.8.8.8' => function (string $ip, string $method) use (&$methods) {
                    $methods[] = $method;

                    return LocationResult::create(
                        ipAddress: $ip,
                        country: null,
                        city: null,
                        asn: null,
                    );
                },
            ],
            chanceOfEmpty: 0,
        );

        $geolocator->lookup('8.8.8.8');
        $geolocator->lookupCountry('8.8.8.8');
        $geolocator->lookupCity('8.8.8.8');
        $geolocator->lookupAsn('8.8.8.8');

        $this->assertSame(['lookup', 'lookupCountry', 'lookupCity', 'lookupAsn'], $methods);
    }

    /**
     * Test that a mocked private address returns the mock rather than an empty result.
     */
    public function test_mocked_private_address_returns_the_mocked_result(): void
    {
        $result = LocationResult::create(
            ipAddress: '192.168.1.1',
            country: CountryResult::create('US'),
            city: null,
            asn: null,
        );

        $geolocator = new FakeGeolocator(
            mockedResults: [
                '192.168.1.1' => $result,
            ],
            chanceOfEmpty: 100,
        );

        $this->assertSame($result, $geolocator->lookup('192.168.1.1'));
    }

    /**
     * Test that a wildcard mock also answers private addresses, bypassing the private address filter.
     */
    public function test_a_wildcard_mock_answers_private_addresses(): void
    {
        $result = LocationResult::create(
            ipAddress: '192.168.1.1',
            country: CountryResult::create('US'),
            city: null,
            asn: null,
        );

        $geolocator = new FakeGeolocator(
            mockedResults: [
                '*' => $result,
            ],
            chanceOfEmpty: 0,
        );

        $this->assertSame($result, $geolocator->lookup('192.168.1.1'));
    }

    /**
     * Test that a malformed address yields an empty result rather than throwing.
     */
    public function test_a_malformed_address_yields_an_empty_result(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [],
            chanceOfEmpty: 0,
        );

        $result = $geolocator->lookup('not-an-ip');

        $this->assertSame('not-an-ip', $result->ipAddress);
        $this->assertFalse($result->hasResults());
    }

    /**
     * Test that a public IPv6 address is not treated as private.
     */
    public function test_a_public_ipv6_address_generates_a_result(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [],
            chanceOfEmpty: 0,
        );

        $result = $geolocator->lookup('2606:4700::1111');

        $this->assertSame('2606:4700::1111', $result->ipAddress);
        $this->assertTrue($result->hasResults());
    }

    /**
     * Test that mock() is chainable and the last mock for an address wins.
     */
    public function test_mocking_the_same_address_twice_keeps_the_last_result(): void
    {
        $geolocator = new FakeGeolocator(
            mockedResults: [],
            chanceOfEmpty: 0,
        );

        $first = LocationResult::create(ipAddress: '8.8.8.8', country: null, city: null, asn: null);
        $second = LocationResult::create(ipAddress: '1.1.1.1', country: null, city: null, asn: null);

        $this->assertSame($geolocator, $geolocator->mock('8.8.8.8', $first));
        $this->assertSame($geolocator, $geolocator->mock('8.8.8.8', $second));

        $this->assertSame($second, $geolocator->lookup('8.8.8.8'));
    }

    /**
     * Test that the mocked results are exposed for inspection.
     */
    public function test_mocked_results_are_exposed(): void
    {
        $result = LocationResult::create(ipAddress: '8.8.8.8', country: null, city: null, asn: null);

        $geolocator = new FakeGeolocator(
            mockedResults: [],
            chanceOfEmpty: 0,
        );

        $geolocator->mock('8.8.8.8', $result);
        $geolocator->mock('*', null);

        $this->assertSame(['8.8.8.8' => $result, '*' => null], $geolocator->getMocks());
    }
}
