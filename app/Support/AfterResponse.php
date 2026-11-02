<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Hands notifications to the queue. Every notification class is queued (see App\Notifications), so this
 * only writes a row to the jobs table; the worker (run every minute by the scheduler) sends the email and
 * the in-app notification. A queue problem must never break the action that triggered it, so it is logged.
 */
class AfterResponse
{
    /** Like Notification::send($to, $notification). */
    public static function send(mixed $to, object $notification): void
    {
        try {
            Notification::send($to, $notification);
        } catch (\Throwable $e) {
            Log::warning('Notification could not be queued: ' . $e->getMessage(), ['notification' => $notification::class]);
        }
    }

    /** Like $notifiable->notify($notification). */
    public static function notify(object $notifiable, object $notification): void
    {
        self::send($notifiable, $notification);
    }
}
