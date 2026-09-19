<?php

namespace SameOldNick\Geolocator\Support;

class CountryHelper
{
    /**
     * Cache for country data to avoid repeated file reads
     */
    protected static ?array $countriesCache = null;

    /**
     * Mappings for changed country codes
     */
    protected static array $mappings = [
        'UK' => 'GB', // Map UK to GB (United Kingdom)
        'EL' => 'GR', // Map EL to GR (Greece)
        'AN' => 'CW', // Map AN (Netherlands Antilles) to CW (Curacao)
        'CS' => 'RS', // Map CS (Czechoslovakia) to RS (Serbia)
    ];

    /**
     * Returns list of countries
     *
     * @return array<string, array{name: string, latitude: string, longitude: string}> Associative array of country codes and country details
     */
    public static function getCountries(): array
    {
        if (self::$countriesCache === null) {
            self::$countriesCache = include __DIR__.'/../../resources/data/countries.php';
        }

        return self::$countriesCache;
    }

    /**
     * Returns list of country codes
     *
     * @return string[] Array of country codes
     */
    public static function getCountryCodes(): array
    {
        return array_keys(self::getCountries());
    }

    /**
     * Validates if the given country code exists
     *
     * @param  string  $countryCode  ISO country code (e.g., "US")
     * @return bool True if valid, false otherwise
     */
    public static function isCountryCodeValid(string $countryCode): bool
    {
        $countries = self::getCountries();

        return array_key_exists($countryCode, $countries);
    }

    /**
     * Gets country details for a given country code
     *
     * @param  string  $countryCode  ISO country code (e.g., "US")
     * @return array{name: string, latitude: string, longitude: string}|null Country details or null if invalid code
     */
    public static function getCountry(string $countryCode): ?array
    {
        $countries = self::getCountries();

        return self::isCountryCodeValid($countryCode) ? $countries[$countryCode] : null;
    }

    /**
     * Gets the country name for a given country code
     *
     * @param  string  $countryCode  ISO country code (e.g., "US")
     * @return string|null Full country name or null if invalid code
     */
    public static function getCountryName(string $countryCode): ?string
    {
        $country = self::getCountry($countryCode);

        return $country['name'] ?? null;
    }

    /**
     * Gets the coordinates for a given country code
     *
     * @param  string  $countryCode  ISO country code (e.g., "US")
     * @return array{latitude: float, longitude: float}|null Associative array with latitude and longitude or null if invalid code
     */
    public static function getCountryCoordinates(string $countryCode): ?array
    {
        $country = self::getCountry($countryCode);

        if (! $country) {
            return null;
        }

        return [
            'latitude' => (float) $country['latitude'],
            'longitude' => (float) $country['longitude'],
        ];
    }

    /**
     * Normalizes a country code by applying mappings and formatting
     *
     * @param  string  $countryCode  Input country code (e.g., "uk", "EL")
     * @return string Normalized country code (e.g., "GB", "GR")
     */
    public static function normalizeCountryCode(string $countryCode): string
    {
        $countryCode = strtoupper(trim($countryCode));

        // Apply mappings if the country code exists in the mappings array
        return self::$mappings[$countryCode] ?? $countryCode;
    }
}
