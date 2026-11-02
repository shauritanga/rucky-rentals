<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Sends notifications (email + in-app) once the browser already has its response.
 *
 * Mail over SMTP takes a second or more per recipient. Sending it inside the request made every
 * "create lease / unit / ticket" wait for it. This runs after the response is flushed instead,
 * so it does not depend on a queue worker, and a mail failure can no longer break the action.
 */
class AfterResponse
{
    /** Like Notification::send($to, $notification), after the response. */
    public static function send(mixed $to, object $notification): void
    {
        app()->terminating(function () use ($to, $notification) {
            try {
                Notification::send($to, $notification);
            } catch (\Throwable $e) {
                Log::warning('Notification could not be delivered: ' . $e->getMessage(), ['notification' => $notification::class]);
            }
        });
    }

    /** Like $notifiable->notify($notification), after the response. */
    public static function notify(object $notifiable, object $notification): void
    {
        self::send($notifiable, $notification);
    }
}
