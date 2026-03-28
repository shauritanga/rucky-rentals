<?php

namespace App\Notifications;

use App\Models\MaintenanceRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceDecisionNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        public MaintenanceRecord $ticket,
        public string            $decision, // 'approved' | 'rejected'
        public string            $message,
        public string            $actor,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ref      = $this->ticket->ticket_number ?? ('TK #' . $this->ticket->id);
        $unit     = $this->ticket->unit?->unit_number ?? $this->ticket->unit_ref ?? 'N/A';
        $property = $this->ticket->unit?->property?->name
                 ?? ($this->ticket->property?->name ?? 'your property');

        if ($this->decision === 'approved') {
            return (new MailMessage)
                ->subject("Maintenance Ticket Approved — {$ref}")
                ->greeting("Hello {$notifiable->name},")
                ->line("The maintenance ticket **{$ref}** — **{$this->ticket->title}** for Unit **{$unit}** at **{$property}** has been **approved**.")
                ->line("**Note from {$this->actor}:** {$this->message}")
                ->line('You may now assign a technician and coordinate the repair.')
                ->action('View Ticket', url('/maintenance'));
        }

        return (new MailMessage)
            ->subject("Maintenance Ticket Rejected — {$ref}")
            ->greeting("Hello {$notifiable->name},")
            ->line("The maintenance ticket **{$ref}** — **{$this->ticket->title}** for Unit **{$unit}** at **{$property}** has been **rejected**.")
            ->line("**Reason from {$this->actor}:** {$this->message}")
            ->line('Please review the details and resubmit if necessary.')
            ->action('View Ticket', url('/maintenance'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'      => 'maintenance_decision',
            'decision'  => $this->decision,
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->ticket_number ?? ('TK #' . $this->ticket->id),
            'title'     => $this->ticket->title,
            'unit'      => $this->ticket->unit?->unit_number ?? $this->ticket->unit_ref,
            'property'  => $this->ticket->unit?->property?->name ?? $this->ticket->property?->name,
            'message'   => $this->message,
            'actor'     => $this->actor,
        ];
    }
}
