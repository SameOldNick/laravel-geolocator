<?php

namespace SameOldNick\Geolocator\Drivers\IPLocationDB\Providers;

use Illuminate\Container\Attributes\Config;

class CityReaderProvider extends AbstractReaderProvider
{
    /**
     * Create a new City reader provider instance.
     *
     * @param  string|null  $databasePathv4  The path to the database file for IPv4 addresses
     * @param  string|null  $databasePathv6  The path to the database file for IPv6 addresses
     */
    public function __construct(
        #[Config('geolocator.drivers.iplocationdb.editions.city.ipv4')]
        ?string $databasePathv4,
        #[Config('geolocator.drivers.iplocationdb.editions.city.ipv6')]
        ?string $databasePathv6,
    ) {
        parent::__construct('city', $databasePathv4, $databasePathv6);
    }
}
