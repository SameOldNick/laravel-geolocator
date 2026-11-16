<?php

namespace SameOldNick\Geolocator\ClientLocators\Headers;

use Illuminate\Support\Arr;
use SameOldNick\Geolocator\DTOs\AsnResult;
use SameOldNick\Geolocator\DTOs\CityResult;
use SameOldNick\Geolocator\DTOs\CountryResult;
use SameOldNick\Geolocator\DTOs\LocationResult;

readonly class RequestHeaderMapper
{
    /**
     * Create a new RequestHeaderMapper instance.
     *
     * @param  array  $countryHeaderMappings  Mapping of country header keys to their corresponding request header names
     * @param  array  $cityHeaderMappings  Mapping of city header keys to their corresponding request header names
     * @param  array  $asnHeaderMappings  Mapping of ASN header keys to their corresponding request header names
     */
    public function __construct(
        protected array $countryHeaderMappings,
        protected array $cityHeaderMappings,
        protected array $asnHeaderMappings,
    ) {
        //
    }

    /**
     * Map request headers to LocationResult DTO
     *
     * @param  string  $ip  Client IP address
     * @param  array  $headers  Request headers
     */
    public function mapHeaders(string $ip, array $headers): LocationResult
    {
        return new LocationResult(
            ipAddress: $ip,
            country: $this->mapCountry($headers),
            city: $this->mapCity($headers),
            asn: $this->mapAsn($headers),
        );
    }

    /**
     * Map country record to CountryResult DTO
     *
     * @param  array  $headers  Request headers
     */
    public function mapCountry(array $headers): ?CountryResult
    {
        $mapped = $this->getHeaderValues($headers, $this->countryHeaderMappings, ['country_code', 'country_name']);

        if (! ($mapped['country_code'] ?? false)) {
            return null;
        }

        return CountryResult::create($mapped['country_code'] ?? '', $mapped['country_name'] ?? null);
    }

    /**
     * Map city record to CityResult DTO
     *
     * @param  array  $headers  Request headers
     */
    public function mapCity(array $headers): ?CityResult
    {
        $mapped = $this->getHeaderValues(
            $headers,
            $this->cityHeaderMappings,
            ['country_code', 'country_name', 'city', 'state1', 'state2', 'latitude', 'longitude', 'timezone'],
        );

        if (! ($mapped['country_code'] ?? false)) {
            return null;
        }

        return CityResult::create(
            countryCode: $mapped['country_code'] ?? '',
            city: $mapped['city'] ?? null,
            state1: $mapped['state1'] ?? null,
            state2: $mapped['state2'] ?? null,
            latitude: $mapped['latitude'] ?? null,
            longitude: $mapped['longitude'] ?? null,
            timezone: $mapped['timezone'] ?? null,
        );
    }

    /**
     * Map ASN record to AsnResult DTO
     *
     * @param  array  $headers  Request headers
     */
    public function mapAsn(array $headers): ?AsnResult
    {
        $mapped = $this->getHeaderValues(
            $headers,
            $this->asnHeaderMappings,
            ['asn', 'organization'],
        );

        if (! is_numeric($mapped['asn'] ?? null) || ! ($mapped['organization'] ?? null)) {
            return null;
        }

        return AsnResult::create(
            asn: (int) $mapped['asn'],
            organization: $mapped['organization'],
        );
    }

    /**
     * Get the mapped header values from the request headers.
     *
     * @param  array  $headers  Request headers
     * @param  array  $mappings  Mapping of header keys to their corresponding request header names
     * @param  array  $mappingKeys  Keys to retrieve from the mappings
     */
    protected function getHeaderValues(array $headers, array $mappings, array $mappingKeys): array
    {
        $mapped = [];

        foreach ($mappingKeys as $key) {
            $headerKey = $mappings[$key] ?? null;

            if (isset($headers[$headerKey])) {
                $mapped[$key] = is_array($headers[$headerKey]) ? Arr::first($headers[$headerKey]) : $headers[$headerKey];
            }
        }

        return $mapped;
    }
}
