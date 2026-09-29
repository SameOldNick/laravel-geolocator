<?php

namespace SameOldNick\Geolocator\Drivers\Fake;

use Closure;
use Faker\Generator;
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
    use Concerns\MocksResults;

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
        array $mockedResults,
        public readonly int $chanceOfEmpty,
    ) {
        $this->mockedResults = $mockedResults;
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
        return $this->setMock($ip, $result);
    }

    /**
     * {@inheritDoc}
     */
    public function lookup(string $ip): LocationResult
    {
        if ($this->hasMockedResult($ip)) {
            return $this->getMockedResult($ip, __FUNCTION__, fn ($ip) => $this->createEmptyResult($ip));
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
            return $this->getMockedResult($ip, __FUNCTION__, fn ($ip) => $this->createEmptyResult($ip));
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
            return $this->getMockedResult($ip, __FUNCTION__, fn ($ip) => $this->createEmptyResult($ip));
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
            return $this->getMockedResult($ip, __FUNCTION__, fn ($ip) => $this->createEmptyResult($ip));
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
