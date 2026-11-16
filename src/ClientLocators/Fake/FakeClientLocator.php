<?php

namespace SameOldNick\Geolocator\ClientLocators\Fake;

use Illuminate\Support\Testing\Fakes\Fake;
use SameOldNick\Geolocator\Contracts\ClientLocator as ClientLocatorContract;
use SameOldNick\Geolocator\DTOs\LocationResult;

/**
 * In-memory client locator that returns a pinned result, installed with the facade's fake() method.
 *
 * A single-edition lookup returns the pinned result with the other editions cleared, matching what
 * the real client locators return.
 */
readonly class FakeClientLocator implements ClientLocatorContract, Fake
{
    /**
     * Create a new class instance.
     *
     * @param  LocationResult  $result  The result every lookup is answered with
     */
    public function __construct(public LocationResult $result)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function lookup(): LocationResult
    {
        return $this->result;
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCountry(): LocationResult
    {
        return new LocationResult(
            ipAddress: $this->result->ipAddress,
            country: $this->result->country,
            city: null,
            asn: null,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCity(): LocationResult
    {
        return new LocationResult(
            ipAddress: $this->result->ipAddress,
            country: null,
            city: $this->result->city,
            asn: null,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function lookupAsn(): LocationResult
    {
        return new LocationResult(
            ipAddress: $this->result->ipAddress,
            country: null,
            city: null,
            asn: $this->result->asn,
        );
    }
}
