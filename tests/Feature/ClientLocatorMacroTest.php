<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use Illuminate\Http\Request;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use SameOldNick\Geolocator\ClientLocatorManager;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Facades\Geolocator;
use SameOldNick\Geolocator\Tests\Fixtures\RecordingClientLocator;
use SameOldNick\Geolocator\Tests\Fixtures\RecordingGeolocator;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * Looking up the client's location through the geolocate request macro.
 *
 * @internal
 */
class ClientLocatorMacroTest extends TestCase
{
    /**
     * The client address used by the delegation tests.
     */
    protected const PUBLIC_IP = '8.8.8.8';

    protected function setUp(): void
    {
        parent::setUp();

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
     * Reset the static trusted proxy state owned by Symfony's request.
     */
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], -1);

        parent::tearDown();
    }

    /**
     * Ensure the macro resolves the client locator configured for the application.
     */
    public function test_the_macro_uses_the_configured_client_locator(): void
    {
        $recorder = $this->useRecordingClientLocator();

        $request = $this->bindRequest(['REMOTE_ADDR' => self::PUBLIC_IP]);

        $this->assertSame($recorder->result, $request->geolocate());
        $this->assertSame(['lookup'], $recorder->calls);
    }

    /**
     * Ensure the macro resolves the IP client locator out of the box and looks up the request address.
     */
    public function test_the_macro_uses_the_ip_client_locator_by_default(): void
    {
        $driver = $this->useRecordingGeolocator();

        $request = $this->bindRequest(['REMOTE_ADDR' => self::PUBLIC_IP]);

        $this->assertSame('ip', $this->manager()->getDefaultDriver());
        $this->assertSame($driver->result, $request->geolocate());
        $this->assertSame([['method' => 'lookup', 'ip' => self::PUBLIC_IP]], $driver->calls);
    }

    /**
     * Ensure the macro uses the headers client locator when the application configures it, without
     * any database lookup.
     */
    public function test_the_macro_uses_the_headers_client_locator_when_configured(): void
    {
        config()->set('geolocator.client', 'headers');
        $this->manager()->forgetDrivers();

        $request = $this->bindRequest([
            'REMOTE_ADDR' => self::PUBLIC_IP,
            'HTTP_X_IP2LOCATION_COUNTRY_CODE' => 'US',
            'HTTP_X_IP2LOCATION_CITY' => 'Mountain View',
        ]);

        $result = $request->geolocate();

        $this->assertSame(self::PUBLIC_IP, $result->ipAddress);
        $this->assertSame('US', $result->country?->countryCode);
        $this->assertSame('Mountain View', $result->city?->city);
    }

    /**
     * Ensure the macro passes a single edition to the headers client locator.
     */
    public function test_the_macro_uses_the_headers_client_locator_for_a_single_edition(): void
    {
        config()->set('geolocator.client', 'headers');
        $this->manager()->forgetDrivers();

        $request = $this->bindRequest([
            'REMOTE_ADDR' => self::PUBLIC_IP,
            'HTTP_X_IP2LOCATION_COUNTRY_CODE' => 'US',
            'HTTP_X_IP2LOCATION_CITY' => 'Mountain View',
        ]);

        $result = $request->geolocate('city');

        $this->assertSame('Mountain View', $result->city?->city);
        $this->assertNull($result->country);
        $this->assertNull($result->asn);
    }

    /**
     * Ensure each edition name calls the matching client locator method.
     *
     * @param  string|null  $edition  The edition passed to the macro
     * @param  string  $expected  The client locator method expected to be called
     */
    #[DataProvider('editions')]
    public function test_the_macro_routes_the_edition_to_the_client_locator(?string $edition, string $expected): void
    {
        $recorder = $this->useRecordingClientLocator();

        $request = $this->bindRequest(['REMOTE_ADDR' => self::PUBLIC_IP]);

        $this->assertSame($recorder->result, $request->geolocate($edition));
        $this->assertSame([$expected], $recorder->calls);
    }

    /**
     * Ensure an unknown edition falls back to the full lookup instead of failing.
     *
     * @param  string  $edition  An edition the macro does not recognise
     */
    #[DataProvider('unknownEditions')]
    public function test_the_macro_falls_back_to_the_full_lookup_for_an_unknown_edition(string $edition): void
    {
        $recorder = $this->useRecordingClientLocator();

        $request = $this->bindRequest(['REMOTE_ADDR' => self::PUBLIC_IP]);

        $this->assertSame($recorder->result, $request->geolocate($edition));
        $this->assertSame(['lookup'], $recorder->calls);
    }

    /**
     * Ensure the first argument is an edition, not a default address: the resolved address still comes
     * from the request.
     */
    public function test_the_macro_does_not_treat_the_first_argument_as_a_default_address(): void
    {
        $driver = $this->useRecordingGeolocator();

        $request = $this->bindRequest(['REMOTE_ADDR' => self::PUBLIC_IP]);

        $this->assertSame($driver->result, $request->geolocate('1.2.3.4'));
        $this->assertSame([['method' => 'lookup', 'ip' => self::PUBLIC_IP]], $driver->calls);
    }

    /**
     * Ensure the macro can be pointed at a client locator other than the configured default.
     */
    public function test_the_macro_accepts_an_explicit_client_locator(): void
    {
        $default = $this->useRecordingClientLocator();

        $request = $this->bindRequest([
            'REMOTE_ADDR' => self::PUBLIC_IP,
            'HTTP_X_IP2LOCATION_COUNTRY_CODE' => 'US',
        ]);

        $result = $request->geolocate(driver: 'headers');

        $this->assertSame('US', $result->country?->countryCode);
        $this->assertSame([], $default->calls);
    }

    /**
     * Ensure an unsupported client locator name fails loudly.
     */
    public function test_the_macro_throws_for_an_unsupported_client_locator(): void
    {
        $request = $this->bindRequest(['REMOTE_ADDR' => self::PUBLIC_IP]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [bogus] not supported.');

        $request->geolocate(driver: 'bogus');
    }

    /**
     * Ensure a missing client locator configuration fails loudly.
     */
    public function test_the_macro_throws_when_no_client_locator_is_configured(): void
    {
        config()->set('geolocator.client', null);
        $this->manager()->forgetDrivers();

        $request = $this->bindRequest(['REMOTE_ADDR' => self::PUBLIC_IP]);

        $this->expectException(InvalidArgumentException::class);

        $request->geolocate();
    }

    /**
     * Ensure a request without a client address fails loudly through the IP locator, rather than
     * looking up an invented address.
     */
    public function test_the_macro_propagates_a_missing_client_address_failure(): void
    {
        $this->useRecordingGeolocator();

        $request = $this->bindRequest(['REMOTE_ADDR' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to determine client IP address.');

        $request->geolocate();
    }

    /**
     * Ensure the macro uses the forwarded address when the proxy is trusted.
     */
    public function test_the_macro_uses_the_forwarded_address_from_a_trusted_proxy(): void
    {
        Request::setTrustedProxies(['10.0.0.5'], Request::HEADER_X_FORWARDED_FOR);

        $driver = $this->useRecordingGeolocator();

        $request = $this->bindRequest([
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => self::PUBLIC_IP,
        ]);

        $this->assertSame($driver->result, $request->geolocate());
        $this->assertSame([['method' => 'lookup', 'ip' => self::PUBLIC_IP]], $driver->calls);
    }

    /**
     * Ensure the macro ignores a forwarded address sent by an untrusted proxy.
     */
    public function test_the_macro_ignores_the_forwarded_address_from_an_untrusted_proxy(): void
    {
        $driver = $this->useRecordingGeolocator();

        $request = $this->bindRequest([
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => self::PUBLIC_IP,
        ]);

        $this->assertSame($driver->result, $request->geolocate());
        $this->assertSame([['method' => 'lookup', 'ip' => '10.0.0.5']], $driver->calls);
    }

    /**
     * Characterisation test: the macro resolves the manager class rather than the swapped contract, so
     * a facade fake does not reach it.
     */
    public function test_the_macro_is_not_intercepted_by_a_geolocator_facade_fake(): void
    {
        $driver = $this->useRecordingGeolocator();

        Geolocator::fake();

        $request = $this->bindRequest(['REMOTE_ADDR' => self::PUBLIC_IP]);

        $this->assertSame($driver->result, $request->geolocate());
        $this->assertSame([['method' => 'lookup', 'ip' => self::PUBLIC_IP]], $driver->calls);
        $this->assertNotSame($driver->result, Geolocator::lookup(self::PUBLIC_IP));
    }

    /**
     * The edition names the macro understands, plus the default when none is given.
     *
     * @return array<string, array{string|null, string}>
     */
    public static function editions(): array
    {
        return [
            'default' => [null, 'lookup'],
            'all editions' => ['all', 'lookup'],
            'country' => ['country', 'lookupCountry'],
            'city' => ['city', 'lookupCity'],
            'asn' => ['asn', 'lookupAsn'],
        ];
    }

    /**
     * Edition values that are not recognised by the macro.
     *
     * @return array<string, array{string}>
     */
    public static function unknownEditions(): array
    {
        return [
            'empty string' => [''],
            'unknown name' => ['bogus'],
            'wrong case' => ['Country'],
            'with whitespace' => [' country '],
        ];
    }

    /**
     * Bind a request into the container, which is where the client locators read from, and return it.
     *
     * @param  array<string, mixed>  $server  Server parameters for the request
     */
    protected function bindRequest(array $server = []): Request
    {
        $request = Request::create('/', 'GET', [], [], [], $server);

        $this->app->instance('request', $request);

        return $request;
    }

    /**
     * Register a recording client locator and, unless told otherwise, make it the configured default.
     */
    protected function useRecordingClientLocator(string $driver = 'recording', bool $makeDefault = true): RecordingClientLocator
    {
        $recorder = new RecordingClientLocator($this->cannedResult());

        $this->manager()->extend($driver, fn () => $recorder);

        if ($makeDefault) {
            config()->set('geolocator.client', $driver);
        }

        $this->manager()->forgetDrivers();

        return $recorder;
    }

    /**
     * Register a recording geolocator driver and make it the driver the IP client locator resolves.
     */
    protected function useRecordingGeolocator(string $driver = 'recording-geolocator'): RecordingGeolocator
    {
        $recorder = new RecordingGeolocator($this->cannedResult());

        Geolocator::extend($driver, fn () => $recorder);

        config()->set('geolocator.client', 'ip');
        config()->set('geolocator.clients.ip.driver', $driver);

        $this->manager()->forgetDrivers();
        Geolocator::forgetDrivers();

        return $recorder;
    }

    /**
     * Resolve the scoped client locator manager the macro uses.
     */
    protected function manager(): ClientLocatorManager
    {
        return $this->app->make(ClientLocatorManager::class);
    }

    /**
     * Create a result that a client locator or driver can return.
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
}
