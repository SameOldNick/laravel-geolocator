<?php

namespace SameOldNick\Geolocator\Drivers\IPLocationDB;

use InvalidArgumentException;
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Contracts\Reader as ReaderContract;
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
     * Get the reader provider for the given edition
     *
     * @param  string  $edition  The edition name (country, city, asn)
     * @return Contracts\ReaderProvider The reader provider instance
     */
    protected function getReaderProvider(string $edition): Contracts\ReaderProvider
    {
        return match ($edition) {
            'country' => app(Providers\CountryReaderProvider::class),
            'city' => app(Providers\CityReaderProvider::class),
            'asn' => app(Providers\AsnReaderProvider::class),
            default => throw new InvalidArgumentException("Invalid edition: $edition"),
        };
    }

    /**
     * Get a reader instance for the given edition and IP address
     *
     * @param  string  $edition  The edition name (country, city, asn)
     * @param  string  $ip  The IP address
     * @return ReaderContract The reader instance
     */
    protected function getReader(string $edition, string $ip): ReaderContract
    {
        $provider = $this->getReaderProvider($edition);

        return $provider->getReader($this->isIpv6($ip) ? 6 : 4);
    }
}
