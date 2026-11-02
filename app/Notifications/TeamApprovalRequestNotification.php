<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class TeamApprovalRequestNotification extends Notification implements ShouldBroadcast, ShouldQueue
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
        public User $member,
        public string $submittedBy,
        public ?string $propertyName = null,
    ) {
        $this->afterCommit(); // only after the database change is saved
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Team Approval Request — {$this->member->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line('A new team member request has been submitted and requires your approval.')
            ->line("**Name:** {$this->member->name}")
            ->line("**Email:** {$this->member->email}")
            ->line("**Role:** {$this->roleLabel($this->member->role)}")
            ->line('**Property:** ' . ($this->propertyName ?: 'Unassigned'))
            ->line("**Submitted by:** {$this->submittedBy}")
            ->action('Review in Superuser Panel', url('/superuser?view=approvals'))
            ->line('Approve this request to activate the account and send the welcome email.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'team_approval_request',
            'user_id' => $this->member->id,
            'name' => $this->member->name,
            'email' => $this->member->email,
            'role' => $this->member->role,
            'role_label' => $this->roleLabel($this->member->role),
            'property' => $this->propertyName ?? '',
            'submitted_by' => $this->submittedBy,
        ];
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'accountant' => 'Accountant',
            'lease_manager' => 'Lease Assistant',
            'maintenance_staff' => 'Maintenance Staff',
            'viewer' => 'Viewer',
            default => ucfirst(str_replace('_', ' ', $role)),
        };
    }
}
