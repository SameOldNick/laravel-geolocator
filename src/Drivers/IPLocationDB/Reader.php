<?php

namespace SameOldNick\Geolocator\Drivers\IPLocationDB;

use MaxMind\Db\Reader as MaxMindReader;
use SameOldNick\Geolocator\Support\IPAddressHelper;

class Reader
{
    /**
     * MaxMind database reader instance
     */
    protected readonly MaxMindReader $reader;

    /**
     * Constructor
     */
    public function __construct(public readonly string $databasePath)
    {
        $this->reader = new MaxMindReader($databasePath);
    }

    /**
     * Get record for the given IP address
     *
     * @param  string  $ip  IP address to lookup
     * @return array|null Array of record data or null if not found
     */
    public function getRecord(string $ip): ?array
    {
        // Return null for private or invalid IP addresses
        if (! IPAddressHelper::isValidIPAddress($ip) || IPAddressHelper::isPrivateIPAddress($ip)) {
            return null;
        }

        return $this->reader->get($ip);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->reader->close();
    }
}
