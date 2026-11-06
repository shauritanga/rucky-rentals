<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The superuser decided a manager's payment request: sent to the property's managers. */
class PaymentChangeDecisionNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [60, 300];
    }

    public function __construct(
        public int $requestId,
        public string $type,     // create | edit | delete
        public string $decision, // approved | rejected
        public string $tenant,
        public string $invoice,
        public string $amount,
        public string $message,
        public string $actor,
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
            'edit' => 'change to the payment',
            'delete' => 'deletion of the payment',
            'credit' => 'use of tenant credit',
            'refund' => 'refund of tenant credit',
            default => 'payment',
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payment request {$this->decision} — {$this->tenant}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your request for the {$this->what()} of {$this->amount} ({$this->invoice}) was **{$this->decision}** by {$this->actor}.")
            ->line("**Note:** {$this->message}")
            ->action('View Payments', url('/payments'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'payment_change_decision',
            'change'     => $this->type,
            'decision'   => $this->decision,
            'request_id' => $this->requestId,
            'tenant'     => $this->tenant,
            'invoice'    => $this->invoice,
            'amount'     => $this->amount,
            'message'    => $this->message,
            'actor'      => $this->actor,
        ];
    }
}
