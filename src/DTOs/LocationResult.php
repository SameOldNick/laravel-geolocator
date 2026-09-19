<?php

namespace SameOldNick\Geolocator\DTOs;

use Illuminate\Contracts\Support\Arrayable;

class LocationResult implements Arrayable
{
    /**
     * Constructor
     *
     * @param  CountryResult|null  $country  Country information
     * @param  CityResult|null  $city  City information
     * @param  AsnResult|null  $asn  ASN information
     */
    public function __construct(
        public readonly string $ipAddress,
        public readonly ?CountryResult $country,
        public readonly ?CityResult $city,
        public readonly ?AsnResult $asn,
    ) {}

    /**
     * Check if any results are available
     */
    public function hasResults(): bool
    {
        return $this->country !== null || $this->city !== null || $this->asn !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'ipAddress' => $this->ipAddress,
            'country' => $this->country?->toArray(),
            'city' => $this->city?->toArray(),
            'asn' => $this->asn?->toArray(),
        ];
    }

    /**
     * String representation of the location result
     */
    public function __toString(): string
    {
        $parts = [];

        if ($this->city?->city) {
            $parts[] = $this->city->city;
        }

        if ($this->city?->state1) {
            $parts[] = $this->city->state1;
        }

        if ($this->country?->countryName) {
            $parts[] = $this->country->countryName;
        }

        return ! empty($parts) ? implode(', ', $parts) : 'Unknown Location';
    }

    /**
     * Creates LocationResult instance
     *
     * @param  string  $ipAddress  IP address
     * @param  CountryResult|null  $country  Country information
     * @param  CityResult|null  $city  City information
     * @param  AsnResult|null  $asn  ASN information
     */
    public static function create(string $ipAddress, ?CountryResult $country, ?CityResult $city, ?AsnResult $asn): self
    {
        return new self(
            ipAddress: $ipAddress,
            country: $country,
            city: $city,
            asn: $asn,
        );
    }
}
