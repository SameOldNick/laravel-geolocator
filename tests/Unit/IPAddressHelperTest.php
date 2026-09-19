<?php

namespace SameOldNick\Geolocator\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use SameOldNick\Geolocator\Support\IPAddressHelper;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * @internal
 */
class IPAddressHelperTest extends TestCase
{
    /**
     * Ensure public addresses are recognised as valid and non-private.
     */
    #[DataProvider('publicAddressProvider')]
    public function test_public_addresses_are_not_private(string $ip): void
    {
        $this->assertTrue(IPAddressHelper::isValidIPAddress($ip));
        $this->assertFalse(IPAddressHelper::isPrivateIPAddress($ip));
    }

    /**
     * Addresses that must be treated as public.
     */
    public static function publicAddressProvider(): array
    {
        return [
            'google dns' => ['8.8.8.8'],
            'just outside 172.16/12' => ['172.32.0.1'],
            'google dns ipv6' => ['2001:4860:4860::8888'],
            // RFC 6598 carrier grade NAT is not covered by FILTER_FLAG_NO_PRIV_RANGE.
            'cgnat 100.64/10' => ['100.64.0.1'],
        ];
    }

    /**
     * Ensure the private and reserved ranges are reported as private.
     */
    #[DataProvider('privateAddressProvider')]
    public function test_private_addresses_are_private(string $ip): void
    {
        $this->assertTrue(IPAddressHelper::isPrivateIPAddress($ip));
    }

    /**
     * Addresses that must be treated as private.
     */
    public static function privateAddressProvider(): array
    {
        return [
            '10/8' => ['10.0.0.1'],
            '172.16/12 lower bound' => ['172.16.0.1'],
            '172.16/12 upper bound' => ['172.31.255.254'],
            '192.168/16' => ['192.168.1.1'],
            'loopback' => ['127.0.0.1'],
            'link local' => ['169.254.1.1'],
            'ipv6 loopback' => ['::1'],
            'ipv6 link local' => ['fe80::1'],
        ];
    }

    /**
     * Ensure an unparseable address is treated as private rather than public, so it is never
     * selected as a client address.
     */
    public function test_invalid_addresses_are_treated_as_private(): void
    {
        $this->assertFalse(IPAddressHelper::isValidIPAddress('not-an-ip'));
        $this->assertTrue(IPAddressHelper::isPrivateIPAddress('not-an-ip'));
        $this->assertTrue(IPAddressHelper::isPrivateIPAddress(''));
    }

    /**
     * Ensure the version helpers are mutually exclusive.
     */
    public function test_version_helpers_are_mutually_exclusive(): void
    {
        $this->assertTrue(IPAddressHelper::isIPv4('8.8.8.8'));
        $this->assertFalse(IPAddressHelper::isIPv6('8.8.8.8'));

        $this->assertTrue(IPAddressHelper::isIPv6('::1'));
        $this->assertFalse(IPAddressHelper::isIPv4('::1'));

        $this->assertFalse(IPAddressHelper::isIPv4('not-an-ip'));
        $this->assertFalse(IPAddressHelper::isIPv6('not-an-ip'));
    }
}
