<?php

namespace SameOldNick\Geolocator\Drivers\IPLocationDB;

use InvalidArgumentException;
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Support\IPAddressHelper;

class Geolocator implements GeolocatorContract
{
    /**
     * The result mapper
     */
    protected readonly ResultMapper $mapper;

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        $this->mapper = new ResultMapper;
    }

    /**
     * {@inheritDoc}
     */
    public function lookup(string $ip): LocationResult
    {
        return $this->mapper->mapResult($ip, [
            'country' => $this->getReader('country', $ip)->getRecord($ip),
            'city' => $this->getReader('city', $ip)->getRecord($ip),
            'asn' => $this->getReader('asn', $ip)->getRecord($ip),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCountry(string $ip): LocationResult
    {
        $reader = $this->getReader('country', $ip);

        $record = $reader->getRecord($ip);

        return $this->mapper->mapResult($ip, [
            'country' => $record,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCity(string $ip): LocationResult
    {
        $reader = $this->getReader('city', $ip);

        $record = $reader->getRecord($ip);

        return $this->mapper->mapResult($ip, [
            'city' => $record,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function lookupAsn(string $ip): LocationResult
    {
        $reader = $this->getReader('asn', $ip);

        $record = $reader->getRecord($ip);

        return $this->mapper->mapResult($ip, [
            'asn' => $record,
        ]);
    }

    /**
     * Determine if IPv6 address
     */
    protected function isIpv6(string $ip): bool
    {
        return IPAddressHelper::isIPv6($ip);
    }

    /**
     * Get a reader instance for the given edition and IP address
     */
    protected function getReader(string $edition, string $ip): Reader
    {
        $providerClass = match ($edition) {
            'country' => Providers\CountryReaderProvider::class,
            'city' => Providers\CityReaderProvider::class,
            'asn' => Providers\AsnReaderProvider::class,
            default => throw new InvalidArgumentException("Invalid edition: $edition"),
        };

        $provider = app($providerClass);

        return $provider->getReader($this->isIpv6($ip) ? 6 : 4);
    }
}
