<?php

namespace SameOldNick\Geolocator\Contracts;

use SameOldNick\Geolocator\DTOs\LocationResult;

interface Geolocator
{
    /**
     * Lookup geolocation of IP address
     *
     * @param  string  $ip  IP address to lookup (IPv4 or IPv6)
     */
    public function lookup(string $ip): LocationResult;

    /**
     * Lookup country information of IP address
     *
     * @param  string  $ip  IP address to lookup (IPv4 or IPv6)
     */
    public function lookupCountry(string $ip): LocationResult;

    /**
     * Lookup city information of IP address
     *
     * @param  string  $ip  IP address to lookup (IPv4 or IPv6)
     */
    public function lookupCity(string $ip): LocationResult;

    /**
     * Lookup ASN information of IP address
     *
     * @param  string  $ip  IP address to lookup (IPv4 or IPv6)
     */
    public function lookupAsn(string $ip): LocationResult;
}
