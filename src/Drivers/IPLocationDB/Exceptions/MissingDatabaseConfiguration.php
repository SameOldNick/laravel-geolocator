<?php

namespace SameOldNick\Geolocator\Drivers\IPLocationDB\Exceptions;

use RuntimeException;

class MissingDatabaseConfiguration extends RuntimeException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $edition, int $ipVersion)
    {
        parent::__construct(
            "Missing database configuration for edition '{$edition}' and IP version '{$ipVersion}'.",
        );
    }
}
