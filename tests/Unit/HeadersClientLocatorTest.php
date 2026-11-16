<?php

namespace SameOldNick\Geolocator\Tests\Unit;

use Illuminate\Http\Request;
use SameOldNick\Geolocator\ClientLocators\Headers\HeadersClientLocator;
use SameOldNick\Geolocator\Contracts\ClientLocator as ClientLocatorContract;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * The headers client locator: build the location from headers injected by an upstream proxy such as
 * the IP2Location Caddy module, with no database lookup.
 *
 * @internal
 */
class HeadersClientLocatorTest extends TestCase
{
    /**
     * The client address reported on the result.
     */
    protected const PUBLIC_IP = '8.8.8.8';

    /**
     * Set up the test by setting the config options.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('geolocator.client', 'headers');
        config()->set('geolocator.clients.headers', [
            'prefix' => 'X_IP2LOCATION_',
            'fallback_ip' => '0.0.0.0',
            'mappings' => [
                'country' => [
                    'country_code' => 'COUNTRY-CODE',
                    'country_name' => 'COUNTRY-NAME',
                ],
                'city' => [
                    'country_code' => 'COUNTRY-CODE',
                    'country_name' => 'COUNTRY-NAME',
                    'city' => 'CITY',
                    'state1' => 'REGION',
                    'state2' => 'STATE2',
                    'latitude' => 'LATITUDE',
                    'longitude' => 'LONGITUDE',
                    'timezone' => 'TIME-ZONE',
                ],
                'asn' => [
                    'asn' => 'ASN',
                    'organization' => 'AS',
                ],
            ],
        ]);
    }

    /**
     * Ensure the locator is a client locator.
     */
    public function test_it_implements_the_client_locator_contract(): void
    {
        $this->bindRequest();

        $this->assertInstanceOf(ClientLocatorContract::class, $this->app->make(HeadersClientLocator::class));
    }

    /**
     * Ensure a full lookup maps every configured header onto the result.
     */
    public function test_it_maps_every_edition_from_the_configured_headers(): void
    {
        $this->bindRequest($this->defaultHeaders());

        $result = $this->app->make(HeadersClientLocator::class)->lookup();

        $this->assertSame(self::PUBLIC_IP, $result->ipAddress);
        $this->assertTrue($result->hasResults());

        $this->assertSame('US', $result->country?->countryCode);
        $this->assertSame('United States', $result->country?->countryName);

        $this->assertSame('Mountain View', $result->city?->city);
        $this->assertSame('California', $result->city?->state1);
        $this->assertSame('Santa Clara County', $result->city?->state2);
        $this->assertSame('America/Los_Angeles', $result->city?->timezone);
        $this->assertEqualsWithDelta(37.4056, $result->city?->latitude, 0.0001);
        $this->assertEqualsWithDelta(-122.0775, $result->city?->longitude, 0.0001);

        $this->assertSame(15169, $result->asn?->asn);
        $this->assertSame('GOOGLE', $result->asn?->organization);
    }

    /**
     * Ensure the country edition returns only the country record.
     */
    public function test_the_country_edition_returns_only_the_country(): void
    {
        $this->bindRequest($this->defaultHeaders());

        $result = $this->app->make(HeadersClientLocator::class)->lookupCountry();

        $this->assertSame('US', $result->country?->countryCode);
        $this->assertNull($result->city);
        $this->assertNull($result->asn);
    }

    /**
     * Ensure the city edition returns only the city record.
     */
    public function test_the_city_edition_returns_only_the_city(): void
    {
        $this->bindRequest($this->defaultHeaders());

        $result = $this->app->make(HeadersClientLocator::class)->lookupCity();

        $this->assertSame('Mountain View', $result->city?->city);
        $this->assertNull($result->country);
        $this->assertNull($result->asn);
    }

    /**
     * Ensure the ASN edition returns only the ASN record.
     */
    public function test_the_asn_edition_returns_only_the_asn(): void
    {
        $this->bindRequest($this->defaultHeaders());

        $result = $this->app->make(HeadersClientLocator::class)->lookupAsn();

        $this->assertSame(15169, $result->asn?->asn);
        $this->assertSame('GOOGLE', $result->asn?->organization);
        $this->assertNull($result->country);
        $this->assertNull($result->city);
    }

    /**
     * Ensure a request without the mapped headers yields an empty result rather than throwing.
     */
    public function test_a_request_without_the_headers_returns_an_empty_result(): void
    {
        $this->bindRequest();

        $locator = $this->app->make(HeadersClientLocator::class);

        $this->assertFalse($locator->lookup()->hasResults());
        $this->assertFalse($locator->lookupCountry()->hasResults());
        $this->assertFalse($locator->lookupCity()->hasResults());
        $this->assertFalse($locator->lookupAsn()->hasResults());
    }

    /**
     * Ensure headers the locator does not know about are ignored.
     */
    public function test_unrelated_headers_are_ignored(): void
    {
        $this->bindRequest([
            'REMOTE_ADDR' => self::PUBLIC_IP,
            'HTTP_X_IP2LOCATION_UNKNOWN' => 'value',
            'HTTP_X_OTHER_HEADER' => 'value',
        ]);

        $this->assertFalse($this->app->make(HeadersClientLocator::class)->lookup()->hasResults());
    }

    /**
     * Ensure an unknown country code degrades to an empty result instead of a bogus country.
     */
    public function test_an_invalid_country_code_returns_an_empty_result(): void
    {
        $this->bindRequest([
            'REMOTE_ADDR' => self::PUBLIC_IP,
            'HTTP_X_IP2LOCATION_COUNTRY_CODE' => 'ZZ',
        ]);

        $result = $this->app->make(HeadersClientLocator::class)->lookup();

        $this->assertNull($result->country);
        $this->assertFalse($result->hasResults());
    }

    /**
     * Ensure a lowercase country code is normalized to its ISO form.
     */
    public function test_a_lowercase_country_code_is_normalized(): void
    {
        $this->bindRequest([
            'REMOTE_ADDR' => self::PUBLIC_IP,
            'HTTP_X_IP2LOCATION_COUNTRY_CODE' => 'us',
        ]);

        $result = $this->app->make(HeadersClientLocator::class)->lookupCountry();

        $this->assertSame('US', $result->country?->countryCode);
        $this->assertSame('United States', $result->country?->countryName);
    }

    /**
     * Ensure an explicit country name header wins over the generated name.
     */
    public function test_the_country_name_header_wins_over_the_generated_name(): void
    {
        $this->bindRequest([
            'REMOTE_ADDR' => self::PUBLIC_IP,
            'HTTP_X_IP2LOCATION_COUNTRY_CODE' => 'US',
            'HTTP_X_IP2LOCATION_COUNTRY_NAME' => 'United States of America',
        ]);

        $result = $this->app->make(HeadersClientLocator::class)->lookupCountry();

        $this->assertSame('United States of America', $result->country?->countryName);
    }

    /**
     * Ensure a city without a country code is dropped: a city record cannot exist without one.
     */
    public function test_a_city_without_a_country_code_is_ignored(): void
    {
        $this->bindRequest([
            'REMOTE_ADDR' => self::PUBLIC_IP,
            'HTTP_X_IP2LOCATION_CITY' => 'Mountain View',
        ]);

        $result = $this->app->make(HeadersClientLocator::class)->lookupCity();

        $this->assertNull($result->city);
        $this->assertFalse($result->hasResults());
    }

    /**
     * Ensure a city with only a country code is still returned, with its optional fields empty.
     */
    public function test_a_city_without_coordinates_is_still_returned(): void
    {
        $this->bindRequest([
            'REMOTE_ADDR' => self::PUBLIC_IP,
            'HTTP_X_IP2LOCATION_COUNTRY_CODE' => 'US',
            'HTTP_X_IP2LOCATION_CITY' => 'Mountain View',
        ]);

        $result = $this->app->make(HeadersClientLocator::class)->lookupCity();

        $this->assertSame('Mountain View', $result->city?->city);
        $this->assertNull($result->city?->state1);
        $this->assertNull($result->city?->state2);
        $this->assertNull($result->city?->latitude);
        $this->assertNull($result->city?->longitude);
        $this->assertNull($result->city?->timezone);
    }

    /**
     * Ensure an ASN without an organization is dropped rather than built with a missing name.
     */
    public function test_an_asn_without_an_organization_is_ignored(): void
    {
        $this->bindRequest([
            'REMOTE_ADDR' => self::PUBLIC_IP,
            'HTTP_X_IP2LOCATION_ASN' => '15169',
        ]);

        $result = $this->app->make(HeadersClientLocator::class)->lookupAsn();

        $this->assertNull($result->asn);
        $this->assertFalse($result->hasResults());
    }

    /**
     * Ensure empty and whitespace-only header values are treated as missing.
     */
    public function test_empty_header_values_are_treated_as_missing(): void
    {
        $this->bindRequest([
            'REMOTE_ADDR' => self::PUBLIC_IP,
            'HTTP_X_IP2LOCATION_COUNTRY_CODE' => '',
            'HTTP_X_IP2LOCATION_COUNTRY_NAME' => '   ',
            'HTTP_X_IP2LOCATION_CITY' => '',
            'HTTP_X_IP2LOCATION_ASN' => '   ',
        ]);

        $result = $this->app->make(HeadersClientLocator::class)->lookup();

        $this->assertNull($result->country);
        $this->assertNull($result->city);
        $this->assertNull($result->asn);
        $this->assertFalse($result->hasResults());
    }

    /**
     * Ensure a non-numeric ASN header is ignored instead of cast to a wrong number.
     */
    public function test_a_non_numeric_asn_header_is_ignored(): void
    {
        $this->bindRequest([
            'REMOTE_ADDR' => self::PUBLIC_IP,
            'HTTP_X_IP2LOCATION_ASN' => 'not-a-number',
            'HTTP_X_IP2LOCATION_AS' => 'GOOGLE',
        ]);

        $result = $this->app->make(HeadersClientLocator::class)->lookupAsn();

        $this->assertNull($result->asn);
    }

    /**
     * Ensure a configured header prefix is applied to the mapped header names.
     */
    public function test_a_configured_prefix_is_applied_to_the_header_names(): void
    {
        config()->set('geolocator.clients.headers.prefix', 'X_CLIENT_');

        $this->bindRequest([
            'REMOTE_ADDR' => self::PUBLIC_IP,
            'HTTP_X_CLIENT_COUNTRY_CODE' => 'CA',
        ]);

        $result = $this->app->make(HeadersClientLocator::class)->lookupCountry();

        $this->assertSame('CA', $result->country?->countryCode);
    }

    /**
     * Ensure an empty prefix reads the mapped header names as configured.
     */
    public function test_an_empty_prefix_reads_the_configured_header_names(): void
    {
        config()->set('geolocator.clients.headers.prefix', '');
        config()->set('geolocator.clients.headers.mappings.country', [
            'country_code' => 'X-Country',
        ]);

        $this->bindRequest([], ['X-Country' => 'US']);

        $result = $this->app->make(HeadersClientLocator::class)->lookupCountry();

        $this->assertSame('US', $result->country?->countryCode);
    }

    /**
     * Ensure header names are matched regardless of their case.
     */
    public function test_header_names_are_matched_regardless_of_case(): void
    {
        $this->bindRequest([], ['x-ip2location-country-code' => 'US']);

        $result = $this->app->make(HeadersClientLocator::class)->lookupCountry();

        $this->assertSame('US', $result->country?->countryCode);
    }

    /**
     * Ensure the result reports the request's client address.
     */
    public function test_it_reports_the_client_address(): void
    {
        $this->bindRequest($this->defaultHeaders());

        $this->assertSame(self::PUBLIC_IP, $this->app->make(HeadersClientLocator::class)->lookup()->ipAddress);
        $this->assertSame(self::PUBLIC_IP, $this->app->make(HeadersClientLocator::class)->lookupCountry()->ipAddress);
        $this->assertSame(self::PUBLIC_IP, $this->app->make(HeadersClientLocator::class)->lookupCity()->ipAddress);
        $this->assertSame(self::PUBLIC_IP, $this->app->make(HeadersClientLocator::class)->lookupAsn()->ipAddress);
    }

    /**
     * Ensure a request without a client address still returns the header data, reporting the
     * documented fallback address.
     */
    public function test_it_falls_back_when_the_request_has_no_client_address(): void
    {
        $headers = $this->defaultHeaders();
        $headers['REMOTE_ADDR'] = null;

        $this->bindRequest($headers);

        $result = $this->app->make(HeadersClientLocator::class)->lookup();

        $this->assertSame('0.0.0.0', $result->ipAddress);
        $this->assertSame('US', $result->country?->countryCode);
        $this->assertTrue($result->hasResults());
    }

    /**
     * Ensure repeated calls on one instance do not leak one edition into another.
     */
    public function test_the_editions_do_not_leak_between_calls(): void
    {
        $this->bindRequest($this->defaultHeaders());

        $locator = $this->app->make(HeadersClientLocator::class);

        $country = $locator->lookupCountry();
        $city = $locator->lookupCity();

        $this->assertNotNull($country->country);
        $this->assertNull($country->city);
        $this->assertNull($country->asn);

        $this->assertNotNull($city->city);
        $this->assertNull($city->country);
        $this->assertNull($city->asn);
    }

    /**
     * The server parameters a Caddy module proxying an IP2Location lookup would populate, using the
     * package defaults.
     *
     * @return array<string, mixed>
     */
    protected function defaultHeaders(): array
    {
        return [
            'REMOTE_ADDR' => self::PUBLIC_IP,
            'HTTP_X_IP2LOCATION_COUNTRY_CODE' => 'US',
            'HTTP_X_IP2LOCATION_COUNTRY_NAME' => 'United States',
            'HTTP_X_IP2LOCATION_CITY' => 'Mountain View',
            'HTTP_X_IP2LOCATION_REGION' => 'California',
            'HTTP_X_IP2LOCATION_STATE2' => 'Santa Clara County',
            'HTTP_X_IP2LOCATION_LATITUDE' => '37.4056',
            'HTTP_X_IP2LOCATION_LONGITUDE' => '-122.0775',
            'HTTP_X_IP2LOCATION_TIME_ZONE' => 'America/Los_Angeles',
            'HTTP_X_IP2LOCATION_ASN' => '15169',
            'HTTP_X_IP2LOCATION_AS' => 'GOOGLE',
        ];
    }

    /**
     * Bind a request into the container, which is where the locator reads the address and headers from.
     *
     * @param  array<string, mixed>  $server  Server parameters for the request
     * @param  array<string, string>  $headers  Additional headers set directly on the request
     */
    protected function bindRequest(array $server = [], array $headers = []): void
    {
        $request = Request::create('/', 'GET', [], [], [], $server);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        $this->app->instance('request', $request);
    }
}
