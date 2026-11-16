<?php

namespace SameOldNick\Geolocator\Contracts;

use SameOldNick\Geolocator\DTOs\LocationResult;

interface ClientLocator
{
    /**
     * Lookup full location information of client IP address
     */
    public function lookup(): LocationResult;

    /**
     * Lookup country information of client IP address
     */
    public function lookupCountry(): LocationResult;

    /**
     * Lookup city information of client IP address
     */
    public function lookupCity(): LocationResult;

    /**
     * Lookup ASN information of client IP address
     */
    public function lookupAsn(): LocationResult;
}
