<?php

namespace SameOldNick\Geolocator\Drivers\IPLocationDB\Providers;

use SameOldNick\Geolocator\Drivers\IPLocationDB\Contracts\Reader as ReaderContract;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Contracts\ReaderProvider;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Exceptions\MissingDatabaseConfiguration;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Reader;

abstract class AbstractReaderProvider implements ReaderProvider
{
    /**
     * The reader instance for v4 addresses, if one has been created.
     */
    protected ?ReaderContract $readerv4 = null;

    /**
     * The reader instance for v6 addresses, if one has been created.
     */
    protected ?ReaderContract $readerv6 = null;

    /**
     * Create a new reader provider instance.
     *
     * @param  string  $edition  The edition of the database (e.g., 'country', 'city', 'asn')
     * @param  string|null  $databasePathv4  The path to the database file for IPv4 addresses
     * @param  string|null  $databasePathv6  The path to the database file for IPv6 addresses
     */
    public function __construct(
        public readonly string $edition,
        public readonly ?string $databasePathv4,
        public readonly ?string $databasePathv6,
    ) {
        //
    }

    /**
     * Get database path for edition and IP address
     */
    protected function getDatabasePath(int $ipVersion): string
    {
        $path = $ipVersion === 6 ? $this->databasePathv6 : $this->databasePathv4;

        if ($path === null) {
            throw new MissingDatabaseConfiguration(
                edition: $this->edition,
                ipVersion: $ipVersion,
            );
        }

        return $path;
    }

    /**
     * Create a reader instance for the given edition and IP version
     *
     * @param  int  $ipVersion  The IP version (4 or 6)
     * @return Reader The reader instance
     */
    public function createReader(int $ipVersion): ReaderContract
    {
        $databasePath = $this->getDatabasePath($ipVersion);

        return new Reader($databasePath);
    }

    /**
     * {@inheritDoc}
     */
    public function getReader(int $ipVersion): ReaderContract
    {
        return $ipVersion === 6 ? $this->getReaderv6() : $this->getReaderv4();
    }

    /**
     * Get the reader instance for IPv4 addresses
     *
     * @return ReaderContract The reader instance for IPv4 addresses
     */
    public function getReaderv4(): ReaderContract
    {
        if ($this->readerv4 === null) {
            $this->readerv4 = $this->createReader(4);
        }

        return $this->readerv4;
    }

    /**
     * Get the reader instance for IPv6 addresses
     *
     * @return ReaderContract The reader instance for IPv6 addresses
     */
    public function getReaderv6(): ReaderContract
    {
        if ($this->readerv6 === null) {
            $this->readerv6 = $this->createReader(6);
        }

        return $this->readerv6;
    }
}
