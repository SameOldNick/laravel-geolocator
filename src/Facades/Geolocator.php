<?php

namespace SameOldNick\Geolocator\Facades;

use Illuminate\Support\Facades\Facade;
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\Drivers\FakeGeolocator;

/**
 * Geolocator Facade
 *
 * @method static \SameOldNick\Geolocator\DTOs\LocationResult lookup(string $ip)
 * @method static \SameOldNick\Geolocator\DTOs\LocationResult lookupCountry(string $ip)
 * @method static \SameOldNick\Geolocator\DTOs\LocationResult lookupCity(string $ip)
 * @method static \SameOldNick\Geolocator\DTOs\LocationResult lookupAsn(string $ip)
 * @method static \SameOldNick\Geolocator\Contracts\Geolocator driver(string|null $driver = null)
 * @method static \SameOldNick\Geolocator\GeolocatorManager extend(string $driver, \Closure $callback)
 * @method static \SameOldNick\Geolocator\GeolocatorManager forgetDrivers()
 * @method static string|null getDefaultDriver()
 *
 * @see GeolocatorContract
 */
class Geolocator extends Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return GeolocatorContract::class;
    }

    /**
     * Swap the facade root for a fake geolocator.
     *
     * @param  int|null  $chanceOfEmpty  Percentage chance of returning an empty result. Set to 0 for deterministic results. Default: 10.
     */
    public static function fake(?int $chanceOfEmpty = null): GeolocatorContract|FakeGeolocator
    {
        static::swap($fake = new FakeGeolocator(
            $chanceOfEmpty ?? 10,
        ));

        return $fake;
    }
}
