<?php

namespace SameOldNick\Geolocator\Facades;

use Illuminate\Support\Facades\Facade;
use SameOldNick\Geolocator\ClientLocators\Fake\FakeClientLocator;
use SameOldNick\Geolocator\Contracts\ClientLocator as ClientLocatorContract;
use SameOldNick\Geolocator\DTOs\LocationResult;

/**
 * Client Locator Facade
 *
 * @method static \SameOldNick\Geolocator\DTOs\LocationResult lookup()
 * @method static \SameOldNick\Geolocator\DTOs\LocationResult lookupCountry()
 * @method static \SameOldNick\Geolocator\DTOs\LocationResult lookupCity()
 * @method static \SameOldNick\Geolocator\DTOs\LocationResult lookupAsn()
 * @method static \SameOldNick\Geolocator\Contracts\ClientLocator driver(string|null $driver = null)
 * @method static \SameOldNick\Geolocator\ClientLocatorManager extend(string $driver, \Closure $callback)
 * @method static \SameOldNick\Geolocator\ClientLocatorManager forgetDrivers()
 * @method static string|null getDefaultDriver()
 *
 * @see ClientLocatorContract
 */
class ClientLocator extends Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return ClientLocatorContract::class;
    }

    /**
     * Swap the facade root for a fake client locator.
     *
     * @param  LocationResult|null  $result  The result every lookup is answered with. A single-edition
     *                                       lookup returns it with the other editions cleared. Defaults
     *                                       to an empty result for "0.0.0.0".
     */
    public static function fake(?LocationResult $result = null): ClientLocatorContract|FakeClientLocator
    {
        static::swap($fake = new FakeClientLocator(
            $result ?? new LocationResult(
                ipAddress: '0.0.0.0',
                country: null,
                city: null,
                asn: null,
            ),
        ));

        return $fake;
    }
}
