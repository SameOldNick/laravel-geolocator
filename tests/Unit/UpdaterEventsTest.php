<?php

namespace SameOldNick\Geolocator\Tests\Unit;

use Illuminate\Support\Facades\Event;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Updater;
use SameOldNick\Geolocator\Events\DatabaseFileUpdated;
use SameOldNick\Geolocator\Events\DatabaseFileUpdateFailed;
use SameOldNick\Geolocator\Events\DatabaseUpdatesCompleted;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * @internal
 */
class UpdaterEventsTest extends TestCase
{
    /**
     * Ensure successful file updates dispatch per-file success events and a final completion event.
     */
    public function test_it_dispatches_updated_and_completed_events_when_updates_succeed(): void
    {
        Event::fake([
            DatabaseFileUpdated::class,
            DatabaseFileUpdateFailed::class,
            DatabaseUpdatesCompleted::class,
        ]);

        $config = [
            'editions' => [
                'country' => [
                    'ipv4' => 'storage/app/geolocation/country-ipv4.mmdb',
                    'ipv6' => 'storage/app/geolocation/country-ipv6.mmdb',
                ],
            ],
            'update' => [
                'urls' => [
                    'country' => [
                        'ipv4' => ['https://example.com/country-ipv4.mmdb'],
                        'ipv6' => ['https://example.com/country-ipv6.mmdb'],
                    ],
                ],
            ],
        ];

        $updater = new class($config, [true, true]) extends Updater
        {
            /** @var array<int, bool> */
            protected array $results;

            /**
             * @param  array<int, bool>  $results
             */
            public function __construct(array $config, array $results)
            {
                parent::__construct($config);

                $this->results = $results;
            }

            /**
             * @param  array<int, string>  $urls
             */
            protected function updateDatabase(string $localPath, array $urls, ?callable $callback = null): bool
            {
                return array_shift($this->results) ?? false;
            }
        };

        $updater->update();

        Event::assertDispatched(DatabaseFileUpdated::class, function (DatabaseFileUpdated $event) {
            return $event->edition === 'country' &&
                in_array($event->ipVersion, ['ipv4', 'ipv6'], true);
        });

        Event::assertNotDispatched(DatabaseFileUpdateFailed::class);

        Event::assertDispatched(DatabaseUpdatesCompleted::class, function (DatabaseUpdatesCompleted $event) {
            return $event->total === 2
                && count($event->successful) === 2
                && count($event->failed) === 0
                && $event->hasFailures() === false;
        });
    }

    /**
     * Ensure failed and skipped file updates dispatch per-file failure events and final completion.
     */
    public function test_it_dispatches_failed_and_completed_events_when_updates_fail_or_are_missing_urls(): void
    {
        Event::fake([
            DatabaseFileUpdated::class,
            DatabaseFileUpdateFailed::class,
            DatabaseUpdatesCompleted::class,
        ]);

        $config = [
            'editions' => [
                'country' => [
                    'ipv4' => 'storage/app/geolocation/country-ipv4.mmdb',
                    'ipv6' => 'storage/app/geolocation/country-ipv6.mmdb',
                ],
            ],
            'update' => [
                'urls' => [
                    'country' => [
                        'ipv4' => [],
                        'ipv6' => ['https://example.com/country-ipv6.mmdb'],
                    ],
                ],
            ],
        ];

        $updater = new class($config, [false]) extends Updater
        {
            /** @var array<int, bool> */
            protected array $results;

            /**
             * @param  array<int, bool>  $results
             */
            public function __construct(array $config, array $results)
            {
                parent::__construct($config);

                $this->results = $results;
            }

            /**
             * @param  array<int, string>  $urls
             */
            protected function updateDatabase(string $localPath, array $urls, ?callable $callback = null): bool
            {
                return array_shift($this->results) ?? false;
            }
        };

        $updater->update();

        Event::assertNotDispatched(DatabaseFileUpdated::class);

        Event::assertDispatched(DatabaseFileUpdateFailed::class, function (DatabaseFileUpdateFailed $event) {
            return $event->ipVersion === 'ipv4'
                && $event->reason === 'No update URLs configured.';
        });

        Event::assertDispatched(DatabaseFileUpdateFailed::class, function (DatabaseFileUpdateFailed $event) {
            return $event->ipVersion === 'ipv6'
                && $event->reason === 'All download attempts failed.';
        });

        Event::assertDispatched(DatabaseUpdatesCompleted::class, function (DatabaseUpdatesCompleted $event) {
            return $event->total === 2
                && count($event->successful) === 0
                && count($event->failed) === 2
                && $event->hasFailures() === true;
        });
    }
}
