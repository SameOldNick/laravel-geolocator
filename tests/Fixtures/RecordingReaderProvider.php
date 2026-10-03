<?php

namespace SameOldNick\Geolocator\Tests\Fixtures;

use Closure;
use SameOldNick\Geolocator\Drivers\Fake\FakeReader;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Contracts\Reader;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Providers\AbstractReaderProvider;

class RecordingReaderProvider extends AbstractReaderProvider
{
    /**
     * The database paths the driver resolved through the provider, per IP version.
     *
     * @var array<int, string>
     */
    public array $requestedPaths = [];

    /**
     * Create a new recording reader provider.
     */
    public function __construct(
        string $edition = 'fake',
        ?string $databasePathv4 = null,
        ?string $databasePathv6 = null,
        public readonly Closure|Reader|null $createReader = null,
    ) {
        parent::__construct(
            $edition,
            $databasePathv4 ?? config('geolocator.drivers.iplocationdb.editions.fake.ipv4'),
            $databasePathv6 ?? config('geolocator.drivers.iplocationdb.editions.fake.ipv6'),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function createReader(int $ipVersion): Reader
    {
        $path = $this->getDatabasePath($ipVersion);

        $this->requestedPaths[] = $path;

        return value($this->createReader, $path, $ipVersion) ?? new FakeReader($path, $ipVersion);
    }
}
