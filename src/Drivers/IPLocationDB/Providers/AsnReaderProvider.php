<?php

namespace SameOldNick\Geolocator\Drivers\IPLocationDB\Providers;

class AsnReaderProvider extends AbstractReaderProvider
{
    /**
     * {@inheritDoc}
     */
    protected function getEdition(): string
    {
        return 'asn';
    }
}
