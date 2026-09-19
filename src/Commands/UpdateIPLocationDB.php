<?php

namespace SameOldNick\Geolocator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Number;
use SameOldNick\Geolocator\Drivers\IPLocationDB\Updater;
use Symfony\Component\Console\Helper\ProgressBar;

class UpdateIPLocationDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geolocation:update-iplocationdb
                            {--verbose|v : Enable verbose output during the update process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update IP Location DB geolocation databases';

    /**
     * Progress bar
     */
    protected ?ProgressBar $progress = null;

    /**
     * Execute the console command.
     */
    public function handle(Updater $updater)
    {
        $this->info('Starting IP Location DB update...');

        $updater->update(function (string $type, string $message, array $context) {
            if (in_array($type, ['info', 'warning', 'error'])) {
                $this->handleLog($type, $message, $context);
            } elseif (str_starts_with($type, 'download:')) {
                $this->handleDownload($type, $message, $context);
            }
        });

        $this->info('IP Location DB update completed.');
    }

    /**
     * Handle logging messages.
     */
    protected function handleLog(string $type, string $message, array $context = []): void
    {
        switch ($type) {
            case 'info':
                $this->info($message);

                break;
            case 'warning':
                $this->warn($message);

                break;
            case 'error':
                $this->error($message);

                break;
            default:
                $this->line($message);

                break;
        }

        if ($this->option('verbose') && ! empty($context)) {
            $this->table([], $context);
        }
    }

    /**
     * Handle download progress.
     */
    protected function handleDownload(string $type, string $message, array $context = []): void
    {
        if ($type === 'download:start') {
            $this->info($message);

            $this->progress = $this->output->createProgressBar();
            $this->progress->setFormat(' %current% [%bar%] %downloaded%/%total% (%percent:3s%%)');

            $this->progress->start();
        } elseif ($type === 'download:progress') {
            if (! $this->progress) {
                return;
            }

            if ($this->progress->getMaxSteps() !== $context['downloadTotal'] && $context['downloadTotal'] > 0) {
                $this->progress->setMaxSteps($context['downloadTotal']);
            }

            $downloadedBytes = $context['downloadedBytes'] ?? 1;
            $totalBytes = $context['downloadTotal'] ?? 0;

            $this->progress->setProgress($downloadedBytes);
            $this->progress->setMessage($this->formatBytes($downloadedBytes), 'downloaded');
            $this->progress->setMessage($this->formatBytes($totalBytes), 'total');
        } elseif ($type === 'download:complete') {
            if ($this->progress) {
                $this->progress->finish();
                $this->newLine(2);

                $this->progress = null;
            }

            $this->info($message);
        }
    }

    /**
     * Format bytes into human-readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        return Number::fileSize($bytes, 2);
    }
}
