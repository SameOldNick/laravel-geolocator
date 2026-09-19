<?php

namespace SameOldNick\Geolocator\Drivers\IPLocationDB;

use SameOldNick\Geolocator\Events\DatabaseFileUpdated;
use SameOldNick\Geolocator\Events\DatabaseFileUpdateFailed;
use SameOldNick\Geolocator\Events\DatabaseUpdatesCompleted;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class Updater
{
    /**
     * Constructor
     *
     * @param  array  $config  Configuration array
     */
    public function __construct(protected readonly array $config)
    {
        //
    }

    /**
     * Update the IP location databases
     *
     * @param  callable|null  $callback  Optional callback for progress reporting
     */
    public function update(?callable $callback = null): void
    {
        $editions = Arr::get($this->config, 'editions', []);
        $total = 0;

        /** @var array<int, array{edition: string, ipVersion: string, localPath: string}> */
        $successful = [];
        /** @var array<int, array{edition: string, ipVersion: string, localPath: string, reason?: string}> */
        $failed = [];

        foreach (array_keys($editions) as $edition) {
            foreach (['ipv4', 'ipv6'] as $ipVersion) {
                $total++;

                $localPath = $this->getDatabasePath($edition, $ipVersion);
                $urls = $this->getUpdateUrls($edition, $ipVersion);

                $this->callCallback(
                    $callback,
                    'info',
                    "Preparing to update {$edition} database ({$ipVersion}).",
                    ['edition' => $edition, 'ipVersion' => $ipVersion, 'localPath' => $localPath]
                );

                if (empty($urls)) {
                    $this->callCallback(
                        $callback,
                        'warning',
                        "No update URLs configured for {$edition} ({$ipVersion}). Skipping.",
                        ['edition' => $edition, 'ipVersion' => $ipVersion]
                    );

                    $failed[] = ['edition' => $edition, 'ipVersion' => $ipVersion, 'localPath' => $localPath, 'reason' => 'No update URLs configured.'];
                    DatabaseFileUpdateFailed::dispatch($edition, $ipVersion, $localPath, 'No update URLs configured.');

                    continue;
                }

                $this->callCallback(
                    $callback,
                    'info',
                    "Updating {$edition} database ({$ipVersion}) from remote sources.",
                    ['edition' => $edition, 'ipVersion' => $ipVersion, 'urls' => $urls]
                );

                if ($this->updateDatabase($localPath, $urls, $callback)) {
                    $successful[] = ['edition' => $edition, 'ipVersion' => $ipVersion, 'localPath' => $localPath];

                    DatabaseFileUpdated::dispatch($edition, $ipVersion, $localPath);
                } else {
                    $failed[] = ['edition' => $edition, 'ipVersion' => $ipVersion, 'localPath' => $localPath, 'reason' => 'All download attempts failed.'];

                    DatabaseFileUpdateFailed::dispatch($edition, $ipVersion, $localPath, 'All download attempts failed.');
                }

            }
        }

        DatabaseUpdatesCompleted::dispatch($total, $successful, $failed);

        $this->callCallback($callback, 'info', 'All database updates completed.', [
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
        ]);
    }

    /**
     * Update a specific database from given URLs
     *
     * @param  string  $localPath  Local path to store the database
     * @param  array  $urls  Array of URLs to download the database from
     * @param  callable|null  $callback  Optional callback for progress reporting
     * @return bool True if update was successful, false otherwise
     */
    protected function updateDatabase(string $localPath, array $urls, ?callable $callback = null): bool
    {
        foreach ($urls as $url) {
            $this->callCallback($callback, 'info', "Downloading database from {$url}", ['url' => $url]);

            $tempFile = tempnam(sys_get_temp_dir(), 'iplocationdb_update_');

            if ($tempFile === false) {
                $this->callCallback($callback, 'error', 'Failed to create temporary file for download.', []);

                continue;
            }

            $this->callCallback($callback, 'info', "Temporary file created at {$tempFile}", ['tempFile' => $tempFile]);

            try {
                $this->callCallback($callback, 'download:start', "Starting download from {$url}", ['url' => $url]);

                $response = Http::withOptions(
                    $this->getHttpOptions() + [
                        'sink' => $tempFile,
                        'progress' => function ($downloadTotal, $downloadedBytes) use ($callback, $url) {
                            $this->callCallback(
                                $callback,
                                'download:progress',
                                "Downloading from {$url}: {$downloadedBytes}/{$downloadTotal} bytes",
                                ['url' => $url, 'downloadedBytes' => $downloadedBytes, 'downloadTotal' => $downloadTotal]
                            );
                        },
                    ]
                )->get($url)->throw();

                $this->callCallback($callback, 'download:complete', "Download completed from {$url}", ['url' => $url, 'response' => $response]);

                if (filesize($tempFile) > 0) {
                    $directory = dirname($localPath);

                    if (! is_dir($directory)) {
                        if (! mkdir($directory, 0755, true)) {
                            $this->callCallback($callback, 'error', "Failed to create directory {$directory}", ['directory' => $directory]);

                            continue;
                        }

                        $this->callCallback($callback, 'info', "Created directory {$directory}", ['directory' => $directory]);
                    }

                    if (rename($tempFile, $localPath) === false) {
                        $this->callCallback($callback, 'error', "Failed to move downloaded file to {$localPath}", ['tempFile' => $tempFile, 'localPath' => $localPath]);

                        continue;
                    }

                    $this->callCallback($callback, 'info', "Database updated successfully at {$localPath}", ['localPath' => $localPath]);

                    return true;
                } else {
                    $this->callCallback($callback, 'warning', 'Downloaded file is empty. Trying next URL if available.', ['url' => $url]);
                }
            } catch (\Exception $e) {
                $this->callCallback($callback, 'error', "Failed to download from {$url}: ".$e->getMessage(), ['url' => $url, 'exception' => $e]);
            } finally {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        }

        $this->callCallback($callback, 'info', "Database update process completed for {$localPath}", ['localPath' => $localPath]);

        return false;
    }

    /**
     * Call the provided callback with given parameters
     *
     * @param  callable|null  $callback  Callback function
     * @param  string  $type  Type of message (info, warning, error, download:start, download:progress, download:complete)
     * @param  string  $message  Message content
     * @param  array  $context  Additional context
     */
    protected function callCallback(?callable $callback, string $type, string $message, array $context = []): void
    {
        if (is_callable($callback)) {
            $callback($type, $message, $context);
        }
    }

    /**
     * Get update URLs for a specific edition and IP version
     *
     * @param  string  $edition  Edition name
     * @param  string  $ipVersion  IP version ('ipv4' or 'ipv6')
     * @return array Array of update URLs
     */
    protected function getUpdateUrls(string $edition, string $ipVersion): array
    {
        return $this->config['update']['urls'][$edition][$ipVersion] ?? [];
    }

    /**
     * Get HTTP options for the update requests
     *
     * @return array Array of HTTP options
     */
    protected function getHttpOptions(): array
    {
        return $this->config['update']['options']['http'] ?? [];
    }

    /**
     * Get database path for edition and IP address
     */
    protected function getDatabasePath(string $edition, string $ipVersion): string
    {
        return $this->config['editions'][$edition][$ipVersion];
    }
}
