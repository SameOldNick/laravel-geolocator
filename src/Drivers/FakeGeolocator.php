<?php

namespace SameOldNick\Geolocator\Drivers;

use Closure;
use Faker\Generator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\Fake;
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\DTOs\AsnResult;
use SameOldNick\Geolocator\DTOs\CityResult;
use SameOldNick\Geolocator\DTOs\CountryResult;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Support\CountryHelper;
use SameOldNick\Geolocator\Support\IPAddressHelper;

/**
 * In-memory geolocator that returns generated data, installed with the facade's fake() method.
 *
 * Results can be pinned with mock(): a key may be an exact address, a glob pattern or the "*" wildcard,
 * and the most specific match wins. Anything not pinned falls back to generated data, or to an empty
 * result when the address is private or the chance of an empty result is hit.
 */
class FakeGeolocator implements Fake, GeolocatorContract
{
    /**
     * The faker
     */
    public readonly Generator $faker;

    /**
     * Create a new class instance.
     *
     * @param  array<string, LocationResult|Closure|null>  $mockedResults  Results to return, keyed by exact address, glob pattern or the "*" wildcard
     * @param  int  $chanceOfEmpty  Percentage chance of an empty result for an unpinned public address
     */
    public function __construct(
        public array $mockedResults,
        public readonly int $chanceOfEmpty,
    ) {
        $this->faker = fake();
    }

    /**
     * Define a mock result for an exact address, a glob pattern or the "*" wildcard.
     *
     * An exact address takes precedence over a glob pattern, which takes precedence over a matching wildcard.
     *
     * @param  string  $ip  Address, glob pattern (e.g. "8.8.8.*") or the "*" wildcard to mock
     * @param  LocationResult|Closure|null  $result  Result to return on a match, a closure receiving the looked up
     *                                               address and the calling method name (lookup, lookupCountry,
     *                                               lookupCity or lookupAsn), or null to resolve to an empty result
     */
    public function mock(string $ip, LocationResult|Closure|null $result = null): self
    {
        $this->mockedResults[$ip] = $result;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function lookup(string $ip): LocationResult
    {
        if ($this->hasMockedResult($ip)) {
            return $this->getMockedResult($ip, __FUNCTION__);
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
        if ($this->hasMockedResult($ip)) {
            return $this->getMockedResult($ip, __FUNCTION__);
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
        if ($this->hasMockedResult($ip)) {
            return $this->getMockedResult($ip, __FUNCTION__);
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
        if ($this->hasMockedResult($ip)) {
            return $this->getMockedResult($ip, __FUNCTION__);
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
     * Find all mocked results matching the given IP address.
     *
     * @return array<string, LocationResult|Closure|null> Matched results, keyed by the pattern that matched
     */
    protected function findMockedResults(string $ip): array
    {
        return Arr::where($this->mockedResults, fn ($value, $key) => Str::is($key, $ip));
    }

    /**
     * Determine if a mocked result exists for the given IP address.
     */
    protected function hasMockedResult(string $ip): bool
    {
        $found = $this->findMockedResults($ip);

        return count($found) > 0;
    }

    /**
     * Get the most specific mocked result for the given IP address, or an empty result if none is defined.
     */
    protected function getMockedResult(string $ip, string $method): LocationResult
    {
        $results = $this->findMockedResults($ip);

        $sorted = Arr::sortDesc($results, fn ($value, $key) => match (true) {
            $key === '*' => 1, // Wildcard
            Str::contains($key, '*') => 2, // Glob pattern
            default => 3, // Specific IP address
        });

        return value(array_shift($sorted), $ip, $method) ?? $this->createEmptyResult($ip);
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
