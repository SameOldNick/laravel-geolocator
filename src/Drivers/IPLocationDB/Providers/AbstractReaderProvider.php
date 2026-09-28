<?php

namespace SameOldNick\Geolocator\Drivers\IPLocationDB\Providers;

use SameOldNick\Geolocator\Contracts\ReaderProvider;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Reader;

abstract class AbstractReaderProvider implements ReaderProvider
{
    /**
     * The reader instance for this provider, if one has been created.
     */
    protected ?Reader $reader = null;

    /**
     * Get database path for edition and IP address
     */
    protected function getDatabasePath(string $edition, int $ipVersion): string
    {
        $ipVersion = $ipVersion === 6 ? 'ipv6' : 'ipv4';

        return $this->getConfig("editions.$edition.$ipVersion");
    }

    /**
     * Get the edition name for this provider
     */
    abstract protected function getEdition(): string;

    /**
     * Get a configuration value for the IPLocationDB driver
     */
    protected function getConfig(string $key, $default = null): mixed
    {
        return config("geolocator.drivers.iplocationdb.$key", $default);
    }

    /**
     * Create a reader instance for the given edition and IP version
     *
     * @param  int  $ipVersion  The IP version (4 or 6)
     * @return Reader The reader instance
     */
    public function createReader(int $ipVersion): Reader
    {
        $databasePath = $this->getDatabasePath($this->getEdition(), $ipVersion);

        return new Reader($databasePath);
    }

    /**
     * {@inheritDoc}
     */
    public function getReader(int $ipVersion): Reader
    {
        if ($this->reader === null) {
            $this->reader = $this->createReader($ipVersion);
        }

        return $this->reader;
    }
}
