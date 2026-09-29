<?php

namespace SameOldNick\Geolocator\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\Drivers\Fake\FakeGeolocator;
use SameOldNick\Geolocator\DTOs\LocationResult;

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
     * @param  array<string, LocationResult|Closure|null>  $mockedResults  Results to return, keyed by exact address, glob pattern (e.g. "8.8.8.*") or the "*" wildcard. A closure receives the looked up address and the calling method name, and may return null for an empty result.
     * @param  int|null  $chanceOfEmpty  Percentage chance of an empty result for an unpinned public address. Set to 0 for deterministic results. Default: 0.
     */
    public static function fake(array $mockedResults = [], ?int $chanceOfEmpty = null): GeolocatorContract|FakeGeolocator
    {
        static::swap($fake = new FakeGeolocator(
            $mockedResults,
            $chanceOfEmpty ?? 0,
        ));

        return $fake;
    }
}
