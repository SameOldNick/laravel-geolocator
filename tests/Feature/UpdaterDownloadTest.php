<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Updater;
use SameOldNick\Geolocator\Events\DatabaseFileUpdated;
use SameOldNick\Geolocator\Events\DatabaseFileUpdateFailed;
use SameOldNick\Geolocator\Events\DatabaseUpdatesCompleted;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * Covers the real download implementation, which the scripted updater double bypasses.
 *
 * @internal
 */
class UpdaterDownloadTest extends TestCase
{
    /**
     * Scratch directory the faked downloads are written into.
     */
    protected string $directory;

    /**
     * Prepare a scratch directory.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'geolocator-'.uniqid();
    }

    /**
     * Remove the scratch directory.
     */
    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    /**
     * Point the updater at a throwaway edition.
     *
     * @param  array<string, string>  $paths  Local path per IP version
     * @param  array<string, array<int, string>>  $urls  Download URLs per IP version
     */
    protected function configureEdition(array $paths, array $urls): void
    {
        config()->set('geolocator.drivers.iplocationdb.editions', ['city' => $paths]);
        config()->set('geolocator.drivers.iplocationdb.update.urls', ['city' => $urls]);
    }

    /**
     * Ensure downloads are written to disk, creating the target directory.
     */
    public function test_it_downloads_databases_and_creates_the_target_directory(): void
    {
        $ipv4 = $this->directory.'/nested/GeoLite2-City-ipv4.mmdb';
        $ipv6 = $this->directory.'/nested/GeoLite2-City-ipv6.mmdb';

        $this->configureEdition(
            ['ipv4' => $ipv4, 'ipv6' => $ipv6],
            ['ipv4' => ['https://example.com/ipv4.mmdb'], 'ipv6' => ['https://example.com/ipv6.mmdb']],
        );

        // A fresh response per request: a single faked response is consumed by the first sink write.
        Http::fake(fn () => Http::response('maxmind-bytes'));

        Event::fake([
            DatabaseFileUpdated::class,
            DatabaseFileUpdateFailed::class,
            DatabaseUpdatesCompleted::class,
        ]);

        $messages = [];

        (new Updater)->update(function (string $type, string $message, array $context) use (&$messages) {
            $messages[] = $type;
        });

        $this->assertFileExists($ipv4);
        $this->assertFileExists($ipv6);
        $this->assertSame('maxmind-bytes', file_get_contents($ipv4));
        $this->assertSame('maxmind-bytes', file_get_contents($ipv6));

        $this->assertContains('download:start', $messages);
        $this->assertContains('download:complete', $messages);

        Event::assertNotDispatched(DatabaseFileUpdateFailed::class);

        Event::assertDispatched(DatabaseFileUpdated::class, function (DatabaseFileUpdated $event) {
            return $event->edition === 'city' && $event->ipVersion === 'ipv4';
        });

        Event::assertDispatched(DatabaseUpdatesCompleted::class, function (DatabaseUpdatesCompleted $event) {
            return $event->total === 2
                && count($event->successful) === 2
                && $event->hasFailures() === false;
        });
    }

    /**
     * Ensure an empty download is rejected and the next URL is tried.
     */
    public function test_it_falls_back_to_the_next_url_when_a_download_is_empty(): void
    {
        $ipv4 = $this->directory.'/GeoLite2-City-ipv4.mmdb';

        $this->configureEdition(
            ['ipv4' => $ipv4, 'ipv6' => $this->directory.'/ipv6.mmdb'],
            ['ipv4' => ['https://example.com/empty.mmdb', 'https://example.com/good.mmdb'], 'ipv6' => []],
        );

        Http::fakeSequence()
            ->push('', 200)
            ->push('second-url-bytes', 200);

        Event::fake([
            DatabaseFileUpdated::class,
            DatabaseFileUpdateFailed::class,
            DatabaseUpdatesCompleted::class,
        ]);

        (new Updater)->update();

        $this->assertSame('second-url-bytes', file_get_contents($ipv4));

        Event::assertDispatched(DatabaseFileUpdated::class, function (DatabaseFileUpdated $event) {
            return $event->ipVersion === 'ipv4';
        });

        // The ipv6 edition has no URLs configured, so it is skipped and reported.
        Event::assertDispatched(DatabaseFileUpdateFailed::class, function (DatabaseFileUpdateFailed $event) {
            return $event->ipVersion === 'ipv6' && $event->reason === 'No update URLs configured.';
        });
    }

    /**
     * Ensure a failing download leaves no file behind and is reported.
     */
    public function test_it_reports_failure_when_every_url_fails(): void
    {
        $ipv4 = $this->directory.'/GeoLite2-City-ipv4.mmdb';

        $this->configureEdition(
            ['ipv4' => $ipv4, 'ipv6' => $this->directory.'/ipv6.mmdb'],
            ['ipv4' => ['https://example.com/broken.mmdb'], 'ipv6' => ['https://example.com/broken-v6.mmdb']],
        );

        Http::fake(['*' => Http::response('server error', 500)]);

        Event::fake([
            DatabaseFileUpdated::class,
            DatabaseFileUpdateFailed::class,
            DatabaseUpdatesCompleted::class,
        ]);

        (new Updater)->update();

        $this->assertFileDoesNotExist($ipv4);

        Event::assertNotDispatched(DatabaseFileUpdated::class);

        Event::assertDispatched(DatabaseFileUpdateFailed::class, function (DatabaseFileUpdateFailed $event) {
            return $event->reason === 'All download attempts failed.';
        });

        Event::assertDispatched(DatabaseUpdatesCompleted::class, function (DatabaseUpdatesCompleted $event) {
            return $event->total === 2
                && count($event->successful) === 0
                && count($event->failed) === 2
                && $event->hasFailures() === true;
        });
    }
}
