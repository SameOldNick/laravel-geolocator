<?php

namespace SameOldNick\Geolocator\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use SameOldNick\Geolocator\Support\CountryHelper;

class CountryResult implements Arrayable
{
    /**
     * Constructor
     *
     * @param  string  $countryCode  ISO country code (e.g., "US")
     * @param  string  $countryName  Full country name (e.g., "United States")
     */
    public function __construct(
        public readonly string $countryCode,
        public readonly string $countryName,
    ) {
        //
    }

    /**
     * Get country coordinates
     *
     * @return array{latitude: float, longitude: float}|null Country details or null if unavailable
     */
    public function getCoordinates(): ?array
    {
        $countryDetails = CountryHelper::getCountryCoordinates($this->countryCode);

        return $countryDetails;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'countryCode' => $this->countryCode,
            'countryName' => $this->countryName,
        ];
    }

    /**
     * String representation of the CountryResult
     */
    public function __toString(): string
    {
        return $this->countryCode;
    }

    /**
     * Creates CountryResult instance
     *
     * @param  string  $countryCode  ISO country code (e.g., "US")
     * @param  string|null  $countryName  Full country name (e.g., "United States")
     * @return self|null Returns instance or null if $countryCode is invalid.
     */
    public static function create(string $countryCode, ?string $countryName = null): ?self
    {
        $normalizedCode = CountryHelper::normalizeCountryCode($countryCode);

        if (! CountryHelper::isCountryCodeValid($normalizedCode)) {
            return null;
        }

        return new self(
            countryCode: $normalizedCode,
            countryName: $countryName ?? CountryHelper::getCountryName($normalizedCode) ?? $normalizedCode,
        );
    }
}
