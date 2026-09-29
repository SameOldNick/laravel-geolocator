<?php

namespace SameOldNick\Geolocator\Drivers\Fake;

use SameOldNick\Geolocator\Drivers\IPLocationDB\Contracts\Reader;
use SameOldNick\Geolocator\Support\IPAddressHelper;

class FakeReader implements Reader
{
    use Concerns\MocksResults;

    /**
     * Constructor
     */
    public function __construct(
        public readonly string $databasePath = '',
    ) {
        //
    }

    /**
     * Set a mocked result for a specific IP address.
     *
     * @param  string  $ip  The IP address to mock
     * @param  array{country_code?: string, city?: string, state1?: string, state2?: string, latitude?: float, longitude?: float, timezone?: string, asn?: int, organization?: string}|null  $result  The mocked result (or null to clear the mock)
     */
    public function mock(string $ip, ?array $result): self
    {
        return $this->setMock($ip, $result);
    }

    /**
     * Get record for the given IP address
     *
     * @param  string  $ip  IP address to lookup
     * @return array|null Array of record data or null if not found
     */
    public function getRecord(string $ip): ?array
    {
        if ($this->hasMockedResult($ip)) {
            return $this->getMockedResult($ip, __FUNCTION__);
        }

        // Return null for private or invalid IP addresses
        if (! IPAddressHelper::isValidIPAddress($ip) || IPAddressHelper::isPrivateIPAddress($ip)) {
            return null;
        }

        return [
            'country_code' => 'US',
            'city' => 'New York',
            'state1' => 'NY',
            'state2' => null,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'timezone' => 'America/New_York',
            'asn' => 12345,
            'organization' => 'Example Organization',
        ];
    }
}
