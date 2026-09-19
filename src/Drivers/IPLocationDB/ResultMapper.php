<?php

namespace SameOldNick\Geolocator\Drivers\IPLocationDB;

use SameOldNick\Geolocator\DTOs\AsnResult;
use SameOldNick\Geolocator\DTOs\CityResult;
use SameOldNick\Geolocator\DTOs\CountryResult;
use SameOldNick\Geolocator\DTOs\LocationResult;

class ResultMapper
{
    /**
     * Map raw database records to LocationResult DTO
     *
     * @param  string  $ip  IP address
     * @param  array{country?: array|null, city?: array|null, asn?: array|null}  $record  Array of raw records from different databases
     */
    public function mapResult(string $ip, array $record): LocationResult
    {
        return new LocationResult(
            ipAddress: $ip,
            country: $this->mapCountry($record['country'] ?? null),
            city: $this->mapCity($record['city'] ?? null),
            asn: $this->mapAsn($record['asn'] ?? null),
        );
    }

    /**
     * Map country record to CountryResult DTO
     *
     * @param  array|null  $record  Country record data
     */
    public function mapCountry(?array $record): ?CountryResult
    {
        if (! $record) {
            return null;
        }

        return CountryResult::create($record['country_code'] ?? '');
    }

    /**
     * Map city record to CityResult DTO
     *
     * @param  array|null  $record  City record data
     */
    public function mapCity(?array $record): ?CityResult
    {
        if (! $record) {
            return null;
        }

        return CityResult::create(
            countryCode: $record['country_code'] ?? '',
            city: $record['city'] ?? null,
            state1: $record['state1'] ?? null,
            state2: $record['state2'] ?? null,
            latitude: $record['latitude'] ?? null,
            longitude: $record['longitude'] ?? null,
            timezone: $record['timezone'] ?? null,
        );
    }

    /**
     * Map ASN record to AsnResult DTO
     *
     * @param  array|null  $record  ASN record data
     */
    public function mapAsn(?array $record): ?AsnResult
    {
        if (! $record) {
            return null;
        }

        return AsnResult::create(
            asn: $record['autonomous_system_number'] ?? null,
            organization: $record['autonomous_system_organization'] ?? null,
        );
    }
}
