<?php

namespace App\Notifications;

use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class LeaseDecisionNotification extends Notification implements ShouldBroadcast, ShouldQueue
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
        public Lease  $lease,
        public string $decision, // 'approved' | 'rejected'
        public string $message,
        public string $actor,
    ) {
        $this->afterCommit(); // only after the database change is saved
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ref      = $this->lease->lease_number ?? ('Lease #' . $this->lease->id);
        $unit     = $this->lease->unit?->unit_number ?? 'N/A';
        $property = $this->lease->unit?->property?->name ?? 'your property';
        $tenant   = $this->lease->tenant?->name ?? 'the tenant';

        if ($this->decision === 'approved') {
            return (new MailMessage)
                ->subject("Lease Approved — {$ref}")
                ->greeting("Hello {$notifiable->name},")
                ->line("The lease **{$ref}** for **{$tenant}** in Unit **{$unit}** at **{$property}** has been **approved** and is now active.")
                ->line("**Note from {$this->actor}:** {$this->message}")
                ->line('Payment installments have been generated. Please inform the tenant and coordinate move-in.')
                ->action('View Lease', url('/leases'));
        }

        return (new MailMessage)
            ->subject("Lease Rejected — {$ref}")
            ->greeting("Hello {$notifiable->name},")
            ->line("The lease **{$ref}** for **{$tenant}** in Unit **{$unit}** at **{$property}** has been **rejected**.")
            ->line("**Reason from {$this->actor}:** {$this->message}")
            ->line('You may revise the lease details and resubmit for approval.')
            ->action('View Lease', url('/leases'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'      => 'lease_decision',
            'decision'  => $this->decision,
            'lease_id'  => $this->lease->id,
            'reference' => $this->lease->lease_number ?? ('Lease #' . $this->lease->id),
            'unit'      => $this->lease->unit?->unit_number,
            'property'  => $this->lease->unit?->property?->name,
            'tenant'    => $this->lease->tenant?->name,
            'message'   => $this->message,
            'actor'     => $this->actor,
        ];
    }
}
