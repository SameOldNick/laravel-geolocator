<?php

namespace SameOldNick\Geolocator\Drivers\IPLocationDB;

use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Support\IPAddressHelper;

class Geolocator implements GeolocatorContract
{
    /**
     * Cached reader instances
     *
     * @var array<string, Reader>
     */
    protected static array $readers = [];

    /**
     * The result mapper
     */
    protected readonly ResultMapper $mapper;

    /**
     * Create a new class instance.
     */
    public function __construct(protected readonly array $config)
    {
        $this->mapper = new ResultMapper;
    }

    /**
     * {@inheritDoc}
     */
    public function lookup(string $ip): LocationResult
    {
        return $this->mapper->mapResult($ip, [
            'country' => $this->createReader('country', $ip)->getRecord($ip),
            'city' => $this->createReader('city', $ip)->getRecord($ip),
            'asn' => $this->createReader('asn', $ip)->getRecord($ip),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCountry(string $ip): LocationResult
    {
        $reader = $this->createReader('country', $ip);

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
        $reader = $this->createReader('city', $ip);

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
        $reader = $this->createReader('asn', $ip);

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
     * Get database path for edition and IP address
     */
    protected function getDatabasePath(string $edition, string $ip): string
    {
        $ipVersion = $this->isIpv6($ip) ? 'ipv6' : 'ipv4';

        return $this->config['editions'][$edition][$ipVersion];
    }

    /**
     * Create a reader instance for the given edition and IP address
     */
    protected function createReader(string $edition, string $ip): Reader
    {
        $databasePath = $this->getDatabasePath($edition, $ip);

        if (! isset(self::$readers[$databasePath])) {
            self::$readers[$databasePath] = new Reader($databasePath);
        }

        return self::$readers[$databasePath];
    }
}
