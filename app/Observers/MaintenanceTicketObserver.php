<?php

namespace App\Observers;

use App\Models\MaintenanceTicket;
use App\Services\AccountingService;

class MaintenanceTicketObserver
{
    public function __construct(private AccountingService $accountingService) {}

    /**
     * Handle the MaintenanceTicket "updated" event.
     * Post expense entry when ticket is resolved
     */
    public function updated(MaintenanceTicket $ticket): void
    {
        $original = $ticket->getOriginal();

        // If transitioning TO resolved, post the expense
        if ($ticket->status === 'resolved' && $original['status'] !== 'resolved') {
            $this->accountingService->postMaintenanceTicket($ticket);
            return;
        }

        // If transitioning FROM resolved to something else, void the entry
        if ($original['status'] === 'resolved' && $ticket->status !== 'resolved') {
            $this->accountingService->voidMaintenanceTicket($ticket);
            return;
        }
    }

    /**
     * Handle the MaintenanceTicket "deleting" event.
     * Void the expense entry before deletion if it was resolved
     */
    public function deleting(MaintenanceTicket $ticket): void
    {
        // Only void if it was posted (resolved status)
        if ($ticket->status === 'resolved' && $ticket->cost > 0) {
            $this->accountingService->voidMaintenanceTicket($ticket);
        }
    }
}
