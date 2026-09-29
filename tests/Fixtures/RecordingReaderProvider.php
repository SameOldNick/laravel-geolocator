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
        public readonly string $edition = 'fake',
        public readonly Closure|Reader|null $createReader = null,
    ) {
        //
    }

    public function createReader(int $ipVersion): Reader
    {
        $path = $this->getDatabasePath($this->getEdition(), $ipVersion);

        $this->requestedPaths[] = $path;

        return value($this->createReader, $path) ?? new FakeReader($path);
    }

    /**
     * {@inheritDoc}
     */
    protected function getEdition(): string
    {
        return $this->edition;
    }
}
