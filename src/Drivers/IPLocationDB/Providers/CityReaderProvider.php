<?php

namespace SameOldNick\Geolocator\Drivers\IPLocationDB\Providers;

class CityReaderProvider extends AbstractReaderProvider
{
    /**
     * {@inheritDoc}
     */
    protected function getEdition(): string
    {
        return 'city';
    }
}
