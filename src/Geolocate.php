<?php

namespace SameOldNick\Geolocator;

use Illuminate\Support\Manager;
use SameOldNick\Geolocator\Drivers\FakeGeolocator;
use SameOldNick\Geolocator\Drivers\IPLocationDB;

class Geolocate extends Manager implements Contracts\Geolocator
{
    /**
     * Indicates if the fake geolocator is enabled.
     */
    protected bool $isFake = false;

    /**
     * Determine if the fake geolocator is enabled
     */
    public function isFake(): bool
    {
        return $this->isFake;
    }

    /**
     * Enable or disable the fake geolocator
     */
    public function fake(bool $enabled = true): void
    {
        $this->isFake = $enabled;
    }

    /**
     * Get the default driver name.
     *
     * @return string|null
     */
    public function getDefaultDriver()
    {
        return $this->isFake ? 'fake' : $this->config->get('geolocator.driver');
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
     * Create a Fake driver instance.
     *
     * @return FakeGeolocator
     */
    public function createFakeDriver()
    {
        return new FakeGeolocator($this->config->get('geolocator.drivers.fake.chance_of_empty', 10));
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
