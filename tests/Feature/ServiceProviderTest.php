<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use InvalidArgumentException;
use SameOldNick\Geolocator\Facades\Geolocator;
use SameOldNick\Geolocator\ServiceProvider;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * Covers the provider: the config it publishes and the defaults it merges.
 *
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

    /**
     * Ensure the package defaults are merged in, so the keys exist without publishing the file.
     */
    public function test_defaults_are_merged_without_publishing_the_config(): void
    {
        $defaults = $this->registerWithoutConsumerConfig();

        $this->assertSame($defaults['driver'], config('geolocator.driver'));
        $this->assertSame(
            $defaults['drivers']['iplocationdb']['editions']['country']['ipv4'],
            config('geolocator.drivers.iplocationdb.editions.country.ipv4'),
        );
    }

    /**
     * Ensure a lookup without a published config reaches the default driver rather than failing on
     * an unresolvable NULL driver.
     */
    public function test_a_lookup_without_a_published_config_reaches_the_default_driver(): void
    {
        $this->registerWithoutConsumerConfig();

        Geolocator::forgetDrivers();

        // The default editions now exist, so the failure is the database file those defaults point
        // at, not "Unable to resolve NULL driver".
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GeoLite2-Country.mmdb');

        Geolocator::lookupCountry('8.8.8.8');
    }

    /**
     * Empty the `geolocator` key the way an application that never published the config file has it,
     * then register the provider again so the merge is what fills the key back in.
     *
     * @return array<string, mixed> The package defaults, read straight from the config file.
     */
    protected function registerWithoutConsumerConfig(): array
    {
        config()->set('geolocator', []);

        (new ServiceProvider($this->app))->register();

        return require __DIR__.'/../../config/geolocator.php';
    }
}
