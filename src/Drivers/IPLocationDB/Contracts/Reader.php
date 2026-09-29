<?php

namespace SameOldNick\Geolocator\Drivers\IPLocationDB\Contracts;

interface Reader
{
    /**
     * Get record for the given IP address
     *
     * @param  string  $ip  IP address to lookup
     * @return array|null Array of record data or null if not found
     */
    public function getRecord(string $ip): ?array;
}
