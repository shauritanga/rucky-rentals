<?php

namespace App\Notifications;

use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaseApprovalRequestNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        public Lease $lease,
        public string $submittedBy
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ref      = $this->lease->lease_number ?? "Lease #{$this->lease->id}";
        $tenant   = $this->lease->tenant->name ?? 'Unknown Tenant';
        $property = $this->lease->unit->property->name ?? 'Unknown Property';
        $unit     = $this->lease->unit->unit_number ?? '—';
        $rent     = $this->lease->currency . ' ' . number_format((float) $this->lease->monthly_rent);

        return (new MailMessage)
            ->subject("New Lease Approval Request — {$ref}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A new lease has been submitted and requires your approval.")
            ->line("**Reference:** {$ref}")
            ->line("**Tenant:** {$tenant}")
            ->line("**Property:** {$property} — Unit {$unit}")
            ->line("**Monthly Rent:** {$rent}")
            ->line("**Submitted by:** {$this->submittedBy}")
            ->action('Review in Superuser Panel', url('/superuser'))
            ->line('Please log in to approve or reject this lease request.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'lease_approval_request',
            'lease_id'     => $this->lease->id,
            'reference'    => $this->lease->lease_number ?? "Lease #{$this->lease->id}",
            'tenant'       => $this->lease->tenant->name ?? '',
            'property'     => $this->lease->unit->property->name ?? '',
            'unit'         => $this->lease->unit->unit_number ?? '',
            'submitted_by' => $this->submittedBy,
        ];
    }
}
