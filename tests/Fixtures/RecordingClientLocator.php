<?php

namespace SameOldNick\Geolocator\Tests\Fixtures;

use SameOldNick\Geolocator\Contracts\ClientLocator as ClientLocatorContract;
use SameOldNick\Geolocator\DTOs\LocationResult;

/**
 * A client locator that records every lookup it receives.
 *
 * Used to assert that the manager delegates to the active client locator and
 * returns its result untouched, without needing a request or a database.
 */
class RecordingClientLocator implements ClientLocatorContract
{
    /**
     * The methods called, in order.
     *
     * @var list<string>
     */
    public array $calls = [];

    /**
     * Create a new class instance.
     */
    public function __construct(public readonly LocationResult $result)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function lookup(): LocationResult
    {
        return $this->record(__FUNCTION__);
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCountry(): LocationResult
    {
        return $this->record(__FUNCTION__);
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCity(): LocationResult
    {
        return $this->record(__FUNCTION__);
    }

    /**
     * {@inheritDoc}
     */
    public function lookupAsn(): LocationResult
    {
        return $this->record(__FUNCTION__);
    }

    /**
     * Record a received call and return the canned result.
     */
    protected function record(string $method): LocationResult
    {
        $this->calls[] = $method;

        return $this->result;
    }
}
