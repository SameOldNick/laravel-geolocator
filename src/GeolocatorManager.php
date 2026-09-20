<?php

namespace SameOldNick\Geolocator;

use Illuminate\Support\Manager;
use SameOldNick\Geolocator\Drivers\IPLocationDB;

class GeolocatorManager extends Manager implements Contracts\Geolocator
{
    /**
     * Get the default driver name.
     *
     * @return string|null
     */
    public function getDefaultDriver()
    {
        return $this->config->get('geolocator.driver');
    }

    /**
     * Create an IPLocationDB driver instance.
     *
     * @return IPLocationDB\Geolocator
     */
    public function createIplocationdbDriver()
    {
        return new IPLocationDB\Geolocator($this->config->get('geolocator.drivers.iplocationdb', []));
    }

    /**
     * Lookup full location information of IP address
     *
     * @param  string  $ip  IP address to lookup (IPv4 or IPv6)
     */
    public function lookup(string $ip): DTOs\LocationResult
    {
        return $this->driver()->lookup($ip);
    }

    /**
     * Lookup country information of IP address
     *
     * @param  string  $ip  IP address to lookup (IPv4 or IPv6)
     */
    public function lookupCountry(string $ip): DTOs\LocationResult
    {
        return $this->driver()->lookupCountry($ip);
    }

    /**
     * Lookup city information of IP address
     *
     * @param  string  $ip  IP address to lookup (IPv4 or IPv6)
     */
    public function lookupCity(string $ip): DTOs\LocationResult
    {
        return $this->driver()->lookupCity($ip);
    }

    /**
     * Lookup ASN information of IP address
     *
     * @param  string  $ip  IP address to lookup (IPv4 or IPv6)
     */
    public function lookupAsn(string $ip): DTOs\LocationResult
    {
        return $this->driver()->lookupAsn($ip);
    }
}
