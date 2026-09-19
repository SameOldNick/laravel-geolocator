<?php

namespace SameOldNick\Geolocator\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DatabaseUpdatesCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  int  $total  Total number of update attempts
     * @param  array<int, array{edition: string, ipVersion: string, localPath: string}>  $successful  List of successful updates (each item includes 'edition', 'ipVersion', 'localPath')
     * @param  array<int, array{edition: string, ipVersion: string, localPath: string, reason?: string}>  $failed  List of failed updates (each item includes 'edition', 'ipVersion', 'localPath', and optional 'reason')
     */
    public function __construct(
        public readonly int $total,
        public readonly array $successful,
        public readonly array $failed,
    ) {
        //
    }

    /**
     * Determine if any update failed.
     */
    public function hasFailures(): bool
    {
        return count($this->failed) > 0;
    }
}
