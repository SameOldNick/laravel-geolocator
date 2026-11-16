<?php

namespace SameOldNick\Geolocator\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use SameOldNick\Geolocator\Support\CountryHelper;

readonly class CityResult implements Arrayable
{
    /**
     * Constructor
     *
     * @param  string  $countryCode  ISO country code (e.g., "US")
     * @param  string  $countryName  Full country name (e.g., "United States")
     * @param  string|null  $city  City name
     * @param  string|null  $state1  First-level subdivision (e.g., state, province)
     * @param  string|null  $state2  Second-level subdivision (e.g., county, district)
     * @param  float|null  $latitude  Latitude coordinate
     * @param  float|null  $longitude  Longitude coordinate
     * @param  string|null  $timezone  Timezone identifier (e.g., "America/New_York")
     */
    public function __construct(
        public string $countryCode,
        public string $countryName,
        public ?string $city,
        public ?string $state1,
        public ?string $state2,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $timezone,
    ) {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'countryCode' => $this->countryCode,
            'countryName' => $this->countryName,
            'city' => $this->city,
            'state1' => $this->state1,
            'state2' => $this->state2,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timezone' => $this->timezone,
        ];
    }

    /**
     * String representation of the CityResult
     *
     * @return string City name or "Unknown City" if not available
     */
    public function __toString()
    {
        return $this->city ?? 'Unknown City';
    }

    /**
     * Create a CityResult from country code and other details
     *
     * @param  string  $countryCode  ISO country code (e.g., "US")
     * @param  string|null  $city  City name
     * @param  string|null  $state1  First-level subdivision (e.g., state, province)
     * @param  string|null  $state2  Second-level subdivision (e.g., county, district)
     * @param  float|null  $latitude  Latitude coordinate
     * @param  float|null  $longitude  Longitude coordinate
     * @param  string|null  $timezone  Timezone identifier (e.g., "America/New_York")
     * @return self|null Returns instance or null if $countryCode is invalid.
     */
    public static function create(string $countryCode, ?string $city, ?string $state1, ?string $state2, ?float $latitude, ?float $longitude, ?string $timezone): ?self
    {
        $normalizedCode = CountryHelper::normalizeCountryCode($countryCode);

        if (! CountryHelper::isCountryCodeValid($normalizedCode)) {
            return null;
        }

        return new self(
            countryCode: $normalizedCode,
            countryName: CountryHelper::getCountryName($normalizedCode) ?? $normalizedCode,
            city: $city,
            state1: $state1,
            state2: $state2,
            latitude: $latitude,
            longitude: $longitude,
            timezone: $timezone,
        );
    }
}
