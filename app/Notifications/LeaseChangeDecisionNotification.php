<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The superuser decided a manager's edit or delete request on a live lease: sent to the property's managers. */
class LeaseChangeDecisionNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [60, 300];
    }

    public function __construct(
        public int $leaseId,
        public string $reference,
        public string $type,     // 'edit' | 'delete'
        public string $decision, // 'approved' | 'rejected'
        public string $message,
        public string $actor,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $what = $this->type === 'delete' ? 'deletion' : 'edit';

        return (new MailMessage)
            ->subject("Lease {$what} {$this->decision} — {$this->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your request for the {$what} of lease {$this->reference} was **{$this->decision}** by {$this->actor}.")
            ->line("**Note:** {$this->message}")
            ->action('View Leases', url('/leases'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'      => 'lease_change_decision',
            'change'    => $this->type,
            'decision'  => $this->decision,
            'lease_id'  => $this->leaseId,
            'reference' => $this->reference,
            'message'   => $this->message,
            'actor'     => $this->actor,
        ];
    }
}
