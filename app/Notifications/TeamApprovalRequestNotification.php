<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamApprovalRequestNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        public User $member,
        public string $submittedBy,
        public ?string $propertyName = null,
    ) {}

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
