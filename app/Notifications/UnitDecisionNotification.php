<?php

namespace App\Notifications;

use App\Models\Unit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class UnitDecisionNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable, SerializesModels;

    /** Sent by the queue worker, so a slow or failing mail server never delays (or breaks) the action. */
    public int $tries = 3;

    /** If the record was deleted before the worker ran, there is nothing left to tell anyone about. */
    public bool $deleteWhenMissingModels = true;

    public function backoff(): array
    {
        return [60, 300];
    }

    public function __construct(
        public Unit $unit,
        public string $decision,
        public string $message,
        public string $actor,
    ) {
        $this->afterCommit(); // only after the database change is saved
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'unit_decision',
            'decision' => $this->decision,
            'unit_id' => $this->unit->id,
            'unit_number' => $this->unit->unit_number,
            'property' => $this->unit->property?->name,
            'message' => $this->message,
            'actor' => $this->actor,
        ];
    }
}
