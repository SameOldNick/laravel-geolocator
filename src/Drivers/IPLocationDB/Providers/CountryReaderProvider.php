<?php

namespace SameOldNick\Geolocator\Drivers\IPLocationDB\Providers;

class CountryReaderProvider extends AbstractReaderProvider
{
    /**
     * {@inheritDoc}
     */
    protected function getEdition(): string
    {
        return 'country';
    }
}
