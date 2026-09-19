<?php

namespace SameOldNick\Geolocator\Listeners;

use App\Components\Notifications\Concerns\DeterminesNotifiables;
use App\Components\Notifications\Enums\NotificationTypes;
use App\Notifications\GeolocationDatabaseUpdated;
use SameOldNick\Geolocator\Events\DatabaseUpdatesCompleted;
use App\Roles;
use Illuminate\Support\Facades\Notification;

class NotifyGeolocationUpdateCompleted
{
    use DeterminesNotifiables;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(DatabaseUpdatesCompleted $event): void
    {
        $users = $this->getUsersToNotify(NotificationTypes::GeolocationDatabaseUpdated, overrideRoles: [Roles::Admin]);

        Notification::send(
            $users,
            new GeolocationDatabaseUpdated($event->total, $event->successful, $event->failed)
        );
    }
}
