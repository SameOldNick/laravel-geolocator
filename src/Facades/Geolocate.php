<?php

namespace SameOldNick\Geolocator\Facades;

use SameOldNick\Geolocator\Contracts\Geolocator;
use Illuminate\Support\Facades\Facade;

/**
 * Geolocate Facade
 *
 * @method static \SameOldNick\Geolocator\DTOs\LocationResult lookup(string $ip)
 * @method static \SameOldNick\Geolocator\DTOs\LocationResult lookupCountry(string $ip)
 * @method static \SameOldNick\Geolocator\DTOs\LocationResult lookupCity(string $ip)
 * @method static \SameOldNick\Geolocator\DTOs\LocationResult lookupAsn(string $ip)
 *
 * @see Geolocator
 */
class Geolocate extends Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return Geolocator::class;
    }
}
