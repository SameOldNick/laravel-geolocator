<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use SameOldNick\Geolocator\Commands\UpdateIPLocationDB;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Updater;
use SameOldNick\Geolocator\Events\DatabaseFileUpdateFailed;
use SameOldNick\Geolocator\Events\DatabaseUpdatesCompleted;
use SameOldNick\Geolocator\Tests\Fixtures\ScriptedUpdater;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * @internal
 */
class UpdateIPLocationDBCommandTest extends TestCase
{
    /**
     * Ensure the update command is registered with the console kernel.
     */
    public function test_command_is_registered(): void
    {
        $command = Artisan::all()['geolocator:update-iplocationdb'] ?? null;

        $this->assertInstanceOf(UpdateIPLocationDB::class, $command);
        $this->assertSame('Update IP Location DB geolocation databases', $command->getDescription());
    }

    /**
     * Ensure running the command drives the updater and reports success.
     */
    public function test_command_runs_the_updater(): void
    {
        Event::fake([DatabaseUpdatesCompleted::class]);

        config()->set('geolocator.drivers.iplocationdb.editions', [
            'country' => [
                'ipv4' => 'storage/app/geolocation/country-ipv4.mmdb',
                'ipv6' => 'storage/app/geolocation/country-ipv6.mmdb',
            ],
        ]);
        config()->set('geolocator.drivers.iplocationdb.update.urls', [
            'country' => [
                'ipv4' => ['https://example.com/country-ipv4.mmdb'],
                'ipv6' => ['https://example.com/country-ipv6.mmdb'],
            ],
        ]);

        $this->instance(Updater::class, new ScriptedUpdater([true, true]));

        $this->artisan('geolocator:update-iplocationdb')
            ->expectsOutputToContain('Starting IP Location DB update...')
            ->expectsOutputToContain('IP Location DB update completed successfully.')
            ->assertSuccessful();

        Event::assertDispatched(DatabaseUpdatesCompleted::class, function (DatabaseUpdatesCompleted $event) {
            return $event->total === 2
                && count($event->successful) === 2
                && count($event->failed) === 0
                && $event->hasFailures() === false;
        });
    }

    /**
     * Ensure failed downloads are reported through events without failing the command.
     */
    public function test_command_reports_failures_through_events(): void
    {
        Event::fake([
            DatabaseFileUpdateFailed::class,
            DatabaseUpdatesCompleted::class,
        ]);

        config()->set('geolocator.drivers.iplocationdb.editions', [
            'country' => [
                'ipv4' => 'storage/app/geolocation/country-ipv4.mmdb',
                'ipv6' => 'storage/app/geolocation/country-ipv6.mmdb',
            ],
        ]);
        config()->set('geolocator.drivers.iplocationdb.update.urls', [
            'country' => [
                'ipv4' => ['https://example.com/country-ipv4.mmdb'],
                'ipv6' => ['https://example.com/country-ipv6.mmdb'],
            ],
        ]);

        $this->instance(Updater::class, new ScriptedUpdater([false, false]));

        // Each failed edition is reported on the console as well as through its event.
        $this->artisan('geolocator:update-iplocationdb')
            ->expectsOutputToContain('Failed to update country database (ipv4).')
            ->expectsOutputToContain('Failed to update country database (ipv6).')
            ->expectsOutputToContain('IP Location DB update completed with some failures.')
            ->assertSuccessful();

        Event::assertDispatched(DatabaseFileUpdateFailed::class, function (DatabaseFileUpdateFailed $event) {
            return $event->edition === 'country'
                && $event->reason === 'All download attempts failed.';
        });

        Event::assertDispatched(DatabaseUpdatesCompleted::class, function (DatabaseUpdatesCompleted $event) {
            return $event->total === 2
                && count($event->successful) === 0
                && count($event->failed) === 2
                && $event->hasFailures() === true;
        });
    }

    /**
     * Ensure a failed edition is reported with its context when verbose.
     */
    public function test_command_verbose_option_shows_failure_context(): void
    {
        Event::fake([
            DatabaseFileUpdateFailed::class,
            DatabaseUpdatesCompleted::class,
        ]);

        config()->set('geolocator.drivers.iplocationdb.editions', [
            'country' => [
                'ipv4' => 'storage/app/geolocation/country-ipv4.mmdb',
                'ipv6' => 'storage/app/geolocation/country-ipv6.mmdb',
            ],
        ]);
        config()->set('geolocator.drivers.iplocationdb.update.urls', [
            'country' => [
                'ipv4' => ['https://example.com/country-ipv4.mmdb'],
                'ipv6' => ['https://example.com/country-ipv6.mmdb'],
            ],
        ]);

        $this->instance(Updater::class, new ScriptedUpdater([false, false]));

        $this->artisan('geolocator:update-iplocationdb', ['--verbose' => true])
            ->expectsOutputToContain('Failed to update country database (ipv4).')
            ->expectsOutputToContain('localPath')
            ->assertSuccessful();
    }

    /**
     * Ensure the verbose option renders the callback context as a table.
     */
    public function test_command_verbose_option_renders_the_callback_context(): void
    {
        Event::fake([DatabaseUpdatesCompleted::class]);

        config()->set('geolocator.drivers.iplocationdb.editions', [
            'country' => [
                'ipv4' => 'storage/app/geolocation/country-ipv4.mmdb',
                'ipv6' => 'storage/app/geolocation/country-ipv6.mmdb',
            ],
        ]);
        config()->set('geolocator.drivers.iplocationdb.update.urls', [
            'country' => [
                'ipv4' => ['https://example.com/country-ipv4.mmdb'],
                'ipv6' => ['https://example.com/country-ipv6.mmdb'],
            ],
        ]);

        $this->instance(Updater::class, new ScriptedUpdater([true, true]));

        $this->artisan('geolocator:update-iplocationdb', ['--verbose' => true])
            ->expectsOutputToContain('localPath')
            ->assertSuccessful();
    }
}
