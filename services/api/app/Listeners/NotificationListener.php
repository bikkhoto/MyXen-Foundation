<?php

namespace App\Listeners;

use App\Events\NotificationCreated;
use App\Jobs\SendNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Notification Listener
 *
 * Listens for NotificationCreated events and dispatches
 * the SendNotificationJob to process the notification.
 */
class NotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param NotificationCreated $event
     * @return void
     */
    public function handle(NotificationCreated $event): void
    {
        Log::info('NotificationCreated event received', [
            'notification_id' => $event->notification->id,
            'event_type' => $event->notification->event_type,
            'channel' => $event->notification->channel,
        ]);

        // Dispatch the SendNotificationJob
        SendNotificationJob::dispatch($event->notification);
    }
}

