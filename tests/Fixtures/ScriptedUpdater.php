<?php

namespace SameOldNick\Geolocator\Tests\Fixtures;

use SameOldNick\Geolocator\Drivers\IPLocationDB\Updater;

/**
 * An updater whose download step is scripted instead of hitting the network.
 *
 * Each call to updateDatabase() consumes the next outcome from the queue and
 * reports it as the download result, so the orchestration in update() can be
 * exercised without HTTP requests or filesystem writes.
 */
class ScriptedUpdater extends Updater
{
    /**
     * The download outcomes to report, in order.
     *
     * @var array<int, bool>
     */
    protected array $outcomes;

    /**
     * Create a new class instance.
     *
     * @param  array<int, bool>  $outcomes  True for a successful download, false for a failed one
     */
    public function __construct(array $outcomes)
    {
        parent::__construct();

        $this->outcomes = $outcomes;
    }

    /**
     * {@inheritDoc}
     */
    protected function updateDatabase(string $localPath, array $urls, ?callable $callback = null): bool
    {
        return array_shift($this->outcomes) ?? false;
    }
}
