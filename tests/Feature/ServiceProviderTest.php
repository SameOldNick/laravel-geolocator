<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use SameOldNick\Geolocator\ServiceProvider;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * @internal
 */
class ServiceProviderTest extends TestCase
{
    /**
     * Ensure the config is published to the same filename the package reads it back from.
     */
    public function test_config_is_published_to_the_config_path(): void
    {
        $paths = ServiceProvider::pathsToPublish(null, 'geolocator-config');

        $this->assertCount(1, $paths);

        $source = array_key_first($paths);

        $this->assertSame(realpath(__DIR__.'/../../config/geolocator.php'), realpath($source));
        $this->assertSame(config_path('geolocator.php'), $paths[$source]);
    }
}
