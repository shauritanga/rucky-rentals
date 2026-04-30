<?php

namespace App\Notifications;

use App\Models\Unit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UnitApprovalRequestNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        public Unit $unit,
        public string $submittedBy,
        public ?string $propertyName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Unit Approval Request — {$this->unit->unit_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line('A new unit has been created and requires your approval.')
            ->line("**Unit:** {$this->unit->unit_number}")
            ->line("**Type:** {$this->unit->type}")
            ->line('**Property:** ' . ($this->propertyName ?: 'Unassigned'))
            ->line("**Monthly Rent:** {$this->unit->currency} " . number_format((float) $this->unit->rent, 2))
            ->line("**Submitted by:** {$this->submittedBy}")
            ->action('Review in Superuser Panel', url('/superuser?view=approvals'))
            ->line('Approve this request to make the unit available for live workflows.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'unit_approval_request',
            'unit_id' => $this->unit->id,
            'unit_number' => $this->unit->unit_number,
            'property' => $this->propertyName ?? '',
            'submitted_by' => $this->submittedBy,
            'unit_type' => $this->unit->type,
        ];
    }
}
