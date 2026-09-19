<?php

namespace SameOldNick\Geolocator\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DatabaseFileUpdateFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly string $edition,
        public readonly string $ipVersion,
        public readonly string $localPath,
        public readonly ?string $reason = null,
    ) {
        //
    }
}
