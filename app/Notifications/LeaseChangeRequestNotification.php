<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A manager asked to edit or delete a live lease: sent to the superusers. */
class LeaseChangeRequestNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [60, 300];
    }

    /** Plain values (not the model) so the message still goes out if the lease is gone by the time the worker runs. */
    public function __construct(
        public int $leaseId,
        public string $reference,
        public string $type, // 'edit' | 'delete'
        public string $tenant,
        public string $property,
        public string $unit,
        public string $submittedBy,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    private function verb(): string
    {
        return $this->type === 'delete' ? 'delete' : 'edit';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Lease {$this->verb()} request — {$this->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->submittedBy} asked to {$this->verb()} the lease {$this->reference} and needs your approval.")
            ->line("**Tenant:** {$this->tenant}")
            ->line("**Property:** {$this->property} — Unit {$this->unit}")
            ->action('Review in Approvals', url('/superuser?view=approvals'))
            ->line('The lease stays as it is until you decide.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'lease_change_request',
            'change'       => $this->type,
            'lease_id'     => $this->leaseId,
            'reference'    => $this->reference,
            'tenant'       => $this->tenant,
            'property'     => $this->property,
            'unit'         => $this->unit,
            'submitted_by' => $this->submittedBy,
        ];
    }
}
