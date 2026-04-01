<?php

namespace App\Notifications;

use App\Models\MaintenanceRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceApprovalNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        public MaintenanceRecord $ticket,
        public string $stage  // 'submitted' | 'approved'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->stage) {
            'submitted' => "Maintenance ticket needs your review — {$this->ticket->ticket_number}",
            'approved'  => "Maintenance ticket approved — work can now proceed",
            default     => "Maintenance ticket update — {$this->ticket->ticket_number}",
        };

        $body = match ($this->stage) {
            'submitted' => 'A new maintenance ticket has been submitted and requires your review.',
            'approved'  => 'Your maintenance request has been fully approved. Work can now proceed.',
            default     => 'There has been an update to a maintenance ticket.',
        };

        return (new MailMessage)
            ->subject($subject)
            ->line($body)
            ->line("**Ticket:** {$this->ticket->ticket_number} — {$this->ticket->title}")
            ->line("**Unit:** {$this->ticket->unit_ref}  |  **Priority:** {$this->ticket->priority}")
            ->action('View Ticket', url('/maintenance'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id'     => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'title'         => $this->ticket->title,
            'stage'         => $this->stage,
            'unit_ref'      => $this->ticket->unit_ref,
            'priority'      => $this->ticket->priority,
        ];
    }
}
