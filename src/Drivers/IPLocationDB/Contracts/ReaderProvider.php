<?php

namespace SameOldNick\Geolocator\Drivers\IPLocationDB\Contracts;

interface ReaderProvider
{
    /**
     * Get a Reader instance for the given IP version
     *
     * @param  int  $ipVersion  The IP version (4 or 6)
     */
    public function getReader(int $ipVersion): Reader;
}
