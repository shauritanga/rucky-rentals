<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A manager asked to record, edit or delete a payment: sent to the superusers. */
class PaymentChangeRequestNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [60, 300];
    }

    /** Plain values (not models) so the message still goes out whatever happens to the records before the worker runs. */
    public function __construct(
        public int $requestId,
        public string $type, // create | edit | delete
        public string $tenant,
        public string $unit,
        public string $invoice,
        public string $amount,
        public string $property,
        public string $submittedBy,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    private function what(): string
    {
        return match ($this->type) {
            'edit' => 'a change to a payment',
            'delete' => 'the deletion of a payment',
            default => 'a new payment',
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment request — ' . $this->tenant)
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->submittedBy} submitted {$this->what()} and needs your approval.")
            ->line("**Tenant:** {$this->tenant}")
            ->line("**Property:** {$this->property} — Unit {$this->unit}")
            ->line("**Invoice:** {$this->invoice}")
            ->line("**Amount:** {$this->amount}")
            ->action('Review in Approvals', url('/superuser?view=approvals'))
            ->line('Nothing changes until you decide.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'payment_change_request',
            'change'       => $this->type,
            'request_id'   => $this->requestId,
            'tenant'       => $this->tenant,
            'unit'         => $this->unit,
            'invoice'      => $this->invoice,
            'amount'       => $this->amount,
            'property'     => $this->property,
            'submitted_by' => $this->submittedBy,
        ];
    }
}
