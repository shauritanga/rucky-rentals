<?php

namespace App\Notifications;

use App\Models\UnitClearance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClearanceReviewNotification extends Notification
{
    use Queueable;

    public function __construct(
        public UnitClearance $clearance,
        public string $submittedBy,
        public ?string $propertyName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Clearance Awaiting Your Review — {$this->clearance->clearance_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line('A move-out clearance has been completed and needs your review before the lease can be terminated.')
            ->line("**Clearance:** {$this->clearance->clearance_number}")
            ->line('**Tenant:** ' . ($this->clearance->tenant?->name ?? 'N/A'))
            ->line('**Unit:** ' . ($this->clearance->unit?->unit_number ?? 'N/A'))
            ->line('**Property:** ' . ($this->propertyName ?: 'Unassigned'));

        if ($this->clearance->termination_reason) {
            $mail->line('**Reason:** ' . $this->clearance->termination_reason);
        }

        return $mail
            ->line("**Submitted by:** {$this->submittedBy}")
            ->action('Review Clearance', url('/clearance'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'clearance_review_request',
            'clearance_id' => $this->clearance->id,
            'clearance_number' => $this->clearance->clearance_number,
            'tenant' => $this->clearance->tenant?->name,
            'unit_number' => $this->clearance->unit?->unit_number,
            'property' => $this->propertyName ?? '',
            'submitted_by' => $this->submittedBy,
            'termination_reason' => $this->clearance->termination_reason,
        ];
    }
}
