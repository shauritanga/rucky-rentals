<?php

namespace App\Notifications;

use App\Models\Unit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class UnitDecisionNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        public Unit $unit,
        public string $decision,
        public string $message,
        public string $actor,
    ) {}

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
