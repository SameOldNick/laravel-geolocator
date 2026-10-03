<?php

namespace SameOldNick\Geolocator\Drivers\IPLocationDB\Providers;

use Illuminate\Container\Attributes\Config;

class CountryReaderProvider extends AbstractReaderProvider
{
    /**
     * Create a new Country reader provider instance.
     *
     * @param  string|null  $databasePathv4  The path to the database file for IPv4 addresses
     * @param  string|null  $databasePathv6  The path to the database file for IPv6 addresses
     */
    public function __construct(
        #[Config('geolocator.drivers.iplocationdb.editions.country.ipv4')]
        ?string $databasePathv4,
        #[Config('geolocator.drivers.iplocationdb.editions.country.ipv6')]
        ?string $databasePathv6,
    ) {
        parent::__construct('country', $databasePathv4, $databasePathv6);
    }
}
