<?php

namespace SameOldNick\Geolocator\Tests\Fixtures;

use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\DTOs\LocationResult;

/**
 * A geolocator driver that records every lookup it receives.
 *
 * Used to assert that the manager delegates to the active driver with the
 * expected arguments and returns its result untouched, without needing a
 * real MaxMind database on disk.
 */
class RecordingGeolocator implements GeolocatorContract
{
    /**
     * The calls received, in order.
     *
     * @var array<int, array{method: string, ip: string}>
     */
    public array $calls = [];

    /**
     * Create a new class instance.
     */
    public function __construct(public readonly LocationResult $result) {}

    /**
     * {@inheritDoc}
     */
    public function lookup(string $ip): LocationResult
    {
        return $this->record(__FUNCTION__, $ip);
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCountry(string $ip): LocationResult
    {
        return $this->record(__FUNCTION__, $ip);
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCity(string $ip): LocationResult
    {
        return $this->record(__FUNCTION__, $ip);
    }

    /**
     * {@inheritDoc}
     */
    public function lookupAsn(string $ip): LocationResult
    {
        return $this->record(__FUNCTION__, $ip);
    }

    /**
     * Record a received call and return the canned result.
     */
    protected function record(string $method, string $ip): LocationResult
    {
        $this->calls[] = ['method' => $method, 'ip' => $ip];

        return $this->result;
    }
}
