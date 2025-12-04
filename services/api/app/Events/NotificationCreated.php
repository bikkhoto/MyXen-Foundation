<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Notification Created Event
 *
 * Fired when a new notification is created.
 * Triggers the dispatch of SendNotificationJob.
 */
class NotificationCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The notification instance.
     *
     * @var Notification
     */
    public Notification $notification;

    /**
     * Create a new event instance.
     *
     * @param Notification $notification
     */
    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }
}

