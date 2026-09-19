<?php

namespace SameOldNick\Geolocator\Support;

/**
 * Helper class for IP address validation and manipulation
 *
 * TODO: Move to different namespace as it's also used by the Request resolvePublicIP() macro, which is not strictly geolocation-related.
 */
class IPAddressHelper
{
    /**
     * Validates if the given IP address is valid (IPv4 or IPv6)
     *
     * @param  string  $ipAddress  The IP address to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidIPAddress(string $ipAddress): bool
    {
        return filter_var($ipAddress, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Determines if the given IP address is IPv4
     *
     * @param  string  $ipAddress  The IP address to check
     * @return bool True if IPv4, false otherwise
     */
    public static function isIPv4(string $ipAddress): bool
    {
        return filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Determines if the given IP address is IPv6
     *
     * @param  string  $ipAddress  The IP address to check
     * @return bool True if IPv6, false otherwise
     */
    public static function isIPv6(string $ipAddress): bool
    {
        return filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * Checks if the given IPv4 or IPv6 address is private
     *
     * @param  string  $ipAddress  The IP address to check
     * @return bool True if private, false otherwise
     */
    public static function isPrivateIPAddress(string $ipAddress): bool
    {
        return filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
