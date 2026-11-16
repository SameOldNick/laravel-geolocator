<?php

namespace SameOldNick\Geolocator\Tests\Unit;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use SameOldNick\Geolocator\ClientLocators\IpClientLocator;
use SameOldNick\Geolocator\Contracts\ClientLocator as ClientLocatorContract;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Facades\Geolocator;
use SameOldNick\Geolocator\Tests\Fixtures\RecordingGeolocator;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * The IP client locator: resolve the request address, then delegate to a geolocator driver.
 *
 * @internal
 */
class IpClientLocatorTest extends TestCase
{
    /**
     * The client address used by the delegation tests.
     */
    protected const PUBLIC_IP = '8.8.8.8';

    /**
     * Reset the static trusted proxy state owned by Symfony's request.
     */
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], -1);

        parent::tearDown();
    }

    /**
     * Ensure the locator is a client locator.
     */
    public function test_it_implements_the_client_locator_contract(): void
    {
        $this->assertInstanceOf(ClientLocatorContract::class, $this->makeLocator()[0]);
    }

    /**
     * Ensure every lookup method returns the driver's result untouched.
     */
    public function test_it_returns_the_driver_result_untouched(): void
    {
        [$locator, $recorder] = $this->makeLocator();

        $this->bindRequest(['REMOTE_ADDR' => self::PUBLIC_IP]);

        $this->assertSame($recorder->result, $locator->lookup());
        $this->assertSame($recorder->result, $locator->lookupCountry());
        $this->assertSame($recorder->result, $locator->lookupCity());
        $this->assertSame($recorder->result, $locator->lookupAsn());
    }

    /**
     * Ensure every edition is looked up with the request's client address, in order.
     */
    public function test_it_passes_the_client_address_to_every_edition(): void
    {
        [$locator, $recorder] = $this->makeLocator();

        $this->bindRequest(['REMOTE_ADDR' => self::PUBLIC_IP]);

        $locator->lookup();
        $locator->lookupCountry();
        $locator->lookupCity();
        $locator->lookupAsn();

        $this->assertSame([
            ['method' => 'lookup', 'ip' => self::PUBLIC_IP],
            ['method' => 'lookupCountry', 'ip' => self::PUBLIC_IP],
            ['method' => 'lookupCity', 'ip' => self::PUBLIC_IP],
            ['method' => 'lookupAsn', 'ip' => self::PUBLIC_IP],
        ], $recorder->calls);
    }

    /**
     * Ensure the driver named for the IP client locator is the one used, not the package default.
     */
    public function test_it_uses_the_driver_configured_for_the_ip_client_locator(): void
    {
        $unused = new RecordingGeolocator($this->cannedResult());
        $used = new RecordingGeolocator($this->cannedResult());

        Geolocator::extend('package-default', fn () => $unused);
        Geolocator::extend('client-driver', fn () => $used);

        config()->set('geolocator.driver', 'package-default');
        config()->set('geolocator.clients.ip.driver', 'client-driver');

        Geolocator::forgetDrivers();

        $this->bindRequest(['REMOTE_ADDR' => self::PUBLIC_IP]);

        $locator = $this->app->make(IpClientLocator::class);

        $this->assertSame($used->result, $locator->lookup());
        $this->assertSame([['method' => 'lookup', 'ip' => self::PUBLIC_IP]], $used->calls);
        $this->assertSame([], $unused->calls);
    }

    /**
     * Ensure an unconfigured IP driver falls back to the package default driver.
     */
    public function test_it_falls_back_to_the_package_driver_when_the_ip_driver_is_not_configured(): void
    {
        $recorder = new RecordingGeolocator($this->cannedResult());

        Geolocator::extend('package-default', fn () => $recorder);

        config()->set('geolocator.driver', 'package-default');
        config()->set('geolocator.clients.ip.driver', null);

        Geolocator::forgetDrivers();

        $this->bindRequest(['REMOTE_ADDR' => self::PUBLIC_IP]);

        $locator = $this->app->make(IpClientLocator::class);

        $this->assertSame($recorder->result, $locator->lookup());
        $this->assertSame(self::PUBLIC_IP, $recorder->calls[0]['ip']);
    }

    /**
     * Ensure an IPv6 client address is passed through unchanged.
     */
    public function test_it_passes_an_ipv6_client_address_unchanged(): void
    {
        [$locator, $recorder] = $this->makeLocator();

        $this->bindRequest(['REMOTE_ADDR' => '2001:db8::1']);

        $locator->lookup();

        $this->assertSame('2001:db8::1', $recorder->calls[0]['ip']);
    }

    /**
     * Ensure private addresses reach the driver: filtering them is the driver's decision, not the
     * locator's.
     */
    public function test_it_passes_private_addresses_to_the_driver_without_filtering(): void
    {
        [$locator, $recorder] = $this->makeLocator();

        $this->bindRequest(['REMOTE_ADDR' => '192.168.1.10']);

        $locator->lookup();

        $this->assertSame('192.168.1.10', $recorder->calls[0]['ip']);
    }

    /**
     * Ensure a forwarded address is used when the proxy is trusted.
     */
    public function test_it_uses_the_forwarded_address_from_a_trusted_proxy(): void
    {
        Request::setTrustedProxies(['10.0.0.5'], Request::HEADER_X_FORWARDED_FOR);

        [$locator, $recorder] = $this->makeLocator();

        $this->bindRequest([
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => self::PUBLIC_IP,
        ]);

        $locator->lookup();

        $this->assertSame(self::PUBLIC_IP, $recorder->calls[0]['ip']);
    }

    /**
     * Ensure a forwarded address is ignored when the proxy is not trusted, so a client cannot spoof
     * its location.
     */
    public function test_it_ignores_the_forwarded_address_from_an_untrusted_proxy(): void
    {
        [$locator, $recorder] = $this->makeLocator();

        $this->bindRequest([
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => self::PUBLIC_IP,
        ]);

        $locator->lookup();

        $this->assertSame('10.0.0.5', $recorder->calls[0]['ip']);
    }

    /**
     * Ensure a request without an address fails loudly for every edition rather than looking up an
     * invented address.
     *
     * @param  string  $method  The client locator method to call
     */
    #[DataProvider('clientLocatorMethods')]
    public function test_it_throws_when_the_request_has_no_client_address(string $method): void
    {
        [$locator, $recorder] = $this->makeLocator();

        $this->bindRequest(['REMOTE_ADDR' => null]);

        try {
            $locator->{$method}();
            $this->fail("{$method}() did not throw for a request without a client address.");
        } catch (RuntimeException $e) {
            $this->assertSame('Unable to determine client IP address.', $e->getMessage());
        }

        $this->assertSame([], $recorder->calls);
    }

    /**
     * Characterisation test: the locator holds the manager, so Geolocator::fake() — which swaps the
     * contract binding — does not intercept it.
     */
    public function test_it_is_not_intercepted_by_a_geolocator_facade_fake(): void
    {
        [$locator, $recorder] = $this->makeLocator();

        Geolocator::fake();

        $this->bindRequest(['REMOTE_ADDR' => self::PUBLIC_IP]);

        $this->assertSame($recorder->result, $locator->lookup());
        $this->assertSame([['method' => 'lookup', 'ip' => self::PUBLIC_IP]], $recorder->calls);
    }

    /**
     * The methods a client locator exposes.
     *
     * @return array<string, array{string}>
     */
    public static function clientLocatorMethods(): array
    {
        return [
            'all editions' => ['lookup'],
            'country' => ['lookupCountry'],
            'city' => ['lookupCity'],
            'asn' => ['lookupAsn'],
        ];
    }

    /**
     * Bind a request into the container, which is where the locator reads the address from.
     *
     * @param  array<string, mixed>  $server  Server parameters for the request
     */
    protected function bindRequest(array $server): void
    {
        $this->app->instance('request', Request::create('/', 'GET', [], [], [], $server));
    }

    /**
     * Build the locator with a recording geolocator driver behind it.
     *
     * @return array{0: IpClientLocator, 1: RecordingGeolocator}
     */
    protected function makeLocator(): array
    {
        $recorder = new RecordingGeolocator($this->cannedResult());

        Geolocator::extend('recording', fn () => $recorder);

        config()->set('geolocator.driver', 'iplocationdb');
        config()->set('geolocator.clients.ip.driver', 'recording');

        Geolocator::forgetDrivers();

        return [$this->app->make(IpClientLocator::class), $recorder];
    }

    /**
     * Create a result that a geolocator driver can return.
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
