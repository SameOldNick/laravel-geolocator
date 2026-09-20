<?php

namespace SameOldNick\Geolocator\Drivers;

use Faker\Generator;
use Illuminate\Support\Arr;
use Illuminate\Support\Testing\Fakes\Fake;
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\DTOs\AsnResult;
use SameOldNick\Geolocator\DTOs\CityResult;
use SameOldNick\Geolocator\DTOs\CountryResult;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Support\CountryHelper;
use SameOldNick\Geolocator\Support\IPAddressHelper;

class FakeGeolocator implements Fake, GeolocatorContract
{
    /**
     * The faker
     */
    public readonly Generator $faker;

    /**
     * Mocked results for specific IP addresses
     *
     * @var array<string, LocationResult|null>
     */
    protected array $mockedResults = [];

    /**
     * Create a new class instance.
     */
    public function __construct(
        public readonly int $chanceOfEmpty = 10,
    ) {
        $this->faker = fake();
    }

    /**
     * Define a mock result for a specific IP address.
     */
    public function mock(string $ip, ?LocationResult $result = null): self
    {
        $this->mockedResults[$ip] = $result;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function lookup(string $ip): LocationResult
    {
        if (Arr::has($this->mockedResults, $ip)) {
            return $this->mockedResults[$ip] ?? $this->createEmptyResult($ip);
        }

        if ($this->shouldReturnEmpty() || $this->isPrivateIpAddress($ip)) {
            return $this->createEmptyResult($ip);
        }

        return new LocationResult(
            ipAddress: $ip,
            country: $this->createCountryResult(),
            city: $this->createCityResult(),
            asn: $this->createAsnResult(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCountry(string $ip): LocationResult
    {
        if (Arr::has($this->mockedResults, $ip)) {
            return $this->mockedResults[$ip] ?? $this->createEmptyResult($ip);
        }

        if ($this->shouldReturnEmpty() || $this->isPrivateIpAddress($ip)) {
            return $this->createEmptyResult($ip);
        }

        return new LocationResult(
            ipAddress: $ip,
            country: $this->createCountryResult(),
            city: null,
            asn: null,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCity(string $ip): LocationResult
    {
        if (Arr::has($this->mockedResults, $ip)) {
            return $this->mockedResults[$ip] ?? $this->createEmptyResult($ip);
        }

        if ($this->shouldReturnEmpty() || $this->isPrivateIpAddress($ip)) {
            return $this->createEmptyResult($ip);
        }

        return new LocationResult(
            ipAddress: $ip,
            country: null,
            city: $this->createCityResult(),
            asn: null,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function lookupAsn(string $ip): LocationResult
    {
        if (Arr::has($this->mockedResults, $ip)) {
            return $this->mockedResults[$ip] ?? $this->createEmptyResult($ip);
        }

        if ($this->shouldReturnEmpty() || $this->isPrivateIpAddress($ip)) {
            return $this->createEmptyResult($ip);
        }

        return new LocationResult(
            ipAddress: $ip,
            country: null,
            city: null,
            asn: $this->createAsnResult(),
        );
    }

    /**
     * Create a fake CountryResult
     */
    protected function createCountryResult(): CountryResult
    {
        $countryCode = $this->faker->randomKey($this->getCountries());

        return CountryResult::create($countryCode);
    }

    /**
     * Create a fake CityResult
     */
    protected function createCityResult(): CityResult
    {
        $countryCode = $this->faker->randomKey($this->getCountries());

        return CityResult::create(
            countryCode: $countryCode,
            city: $this->faker->city(),
            state1: $this->faker->state(),
            state2: null,
            latitude: $this->faker->latitude(),
            longitude: $this->faker->longitude(),
            timezone: $this->faker->timezone(),
        );
    }

    /**
     * Create a fake AsnResult
     */
    protected function createAsnResult(): AsnResult
    {
        return AsnResult::create(
            asn: $this->faker->numberBetween(1000, 99999),
            organization: $this->faker->company(),
        );
    }

    /**
     * Create an empty LocationResult
     */
    protected function createEmptyResult(string $ip): LocationResult
    {
        return new LocationResult(
            ipAddress: $ip,
            country: null,
            city: null,
            asn: null,
        );
    }

    /**
     * Determine if the given IP address is private
     */
    protected function isPrivateIpAddress(string $ip): bool
    {
        return IPAddressHelper::isPrivateIPAddress($ip);
    }

    /**
     * Get all mocked results
     */
    public function getMocks(): array
    {
        return $this->mockedResults;
    }

    /**
     * Determine if a null result should be returned
     */
    protected function shouldReturnEmpty(): bool
    {
        return $this->faker->boolean($this->chanceOfEmpty);
    }

    /**
     * Get the list of countries
     */
    protected function getCountries(): array
    {
        return CountryHelper::getCountries();
    }
}
