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
        public string $stage  // 'submitted' | 'pending_manager' | 'approved'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->stage) {
            'submitted'       => "Maintenance ticket needs your review — {$this->ticket->ticket_number}",
            'pending_manager' => "Maintenance ticket pending your approval — {$this->ticket->ticket_number}",
            'approved'        => "Maintenance ticket approved — work can now proceed",
            default           => "Maintenance ticket update — {$this->ticket->ticket_number}",
        };

        $body = match ($this->stage) {
            'submitted'       => 'A new maintenance ticket has been submitted and requires your review before work can begin.',
            'pending_manager' => 'The accountant has reviewed this ticket. It now awaits your final approval before work can begin.',
            'approved'        => 'Your maintenance request has been fully approved. Work can now proceed.',
            default           => 'There has been an update to a maintenance ticket.',
        };

        // Build cost summary
        $labour       = (float) ($this->ticket->cost ?? 0);
        $materials    = is_array($this->ticket->materials) ? $this->ticket->materials : [];
        $materialCost = array_reduce($materials, fn ($sum, $m) => $sum + (float)($m['qty'] ?? 0) * (float)($m['unit_price'] ?? 0), 0.0);
        $totalCost    = $labour + $materialCost;

        $mail = (new MailMessage)
            ->subject($subject)
            ->line($body)
            ->line('')
            ->line("**Ticket:** {$this->ticket->ticket_number} — {$this->ticket->title}")
            ->line("**Unit:** {$this->ticket->unit_ref} &nbsp;|&nbsp; **Category:** {$this->ticket->category} &nbsp;|&nbsp; **Priority:** " . strtoupper($this->ticket->priority))
            ->line("**Reported by:** {$this->ticket->reported_by} &nbsp;|&nbsp; **Assigned to:** " . ($this->ticket->assignee ?: 'Unassigned'));

        if ($this->ticket->description) {
            $mail->line('')
                 ->line("**Description:** {$this->ticket->description}");
        }

        // Cost breakdown — shown for submitted/pending_manager stages where approval is needed
        if (in_array($this->stage, ['submitted', 'pending_manager'])) {
            $mail->line('');
            $mail->line('---');
            $mail->line('**Estimated Cost Breakdown**');

            if ($labour > 0) {
                $mail->line('Labour: **TZS ' . number_format($labour, 2) . '**');
            }

            if (!empty($materials)) {
                $mail->line('Materials:');
                foreach ($materials as $m) {
                    $lineTotal = (float)($m['qty'] ?? 0) * (float)($m['unit_price'] ?? 0);
                    $mail->line("&nbsp;&nbsp;• {$m['name']} × {$m['qty']} {$m['unit']} @ TZS " . number_format((float)($m['unit_price'] ?? 0), 2) . ' = TZS ' . number_format($lineTotal, 2));
                }
                $mail->line('Materials Total: **TZS ' . number_format($materialCost, 2) . '**');
            }

            if ($totalCost > 0) {
                $mail->line('**Total Estimated Cost: TZS ' . number_format($totalCost, 2) . '**');
            } else {
                $mail->line('*No cost estimate provided by requestor.*');
            }

            $mail->line('---');
        }

        return $mail->action('Review & Approve Ticket', url('/maintenance'));
    }

    public function toArray(object $notifiable): array
    {
        $labour       = (float) ($this->ticket->cost ?? 0);
        $materials    = is_array($this->ticket->materials) ? $this->ticket->materials : [];
        $materialCost = array_reduce($materials, fn ($sum, $m) => $sum + (float)($m['qty'] ?? 0) * (float)($m['unit_price'] ?? 0), 0.0);
        $totalCost    = $labour + $materialCost;

        return [
            'ticket_id'         => $this->ticket->id,
            'ticket_number'     => $this->ticket->ticket_number,
            'title'             => $this->ticket->title,
            'stage'             => $this->stage,
            'unit_ref'          => $this->ticket->unit_ref,
            'category'          => $this->ticket->category,
            'priority'          => $this->ticket->priority,
            'reported_by'       => $this->ticket->reported_by,
            'assignee'          => $this->ticket->assignee,
            'description'       => $this->ticket->description,
            'labour_cost'       => $labour,
            'material_cost'     => round($materialCost, 2),
            'total_cost'        => round($totalCost, 2),
            'materials_count'   => count($materials),
        ];
    }
}
