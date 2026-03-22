<?php

namespace App\Observers;

use App\Models\MaintenanceRecord;
use App\Services\AccountingService;

class MaintenanceRecordObserver
{
    public function __construct(private AccountingService $accountingService) {}

    /**
     * Handle the MaintenanceRecord "updated" event.
     * Post expense entry when record is resolved.
     */
    public function updated(MaintenanceRecord $record): void
    {
        $originalStatus = $record->getOriginal('status');
        if ($originalStatus === null) {
            return;
        }

        if ($record->status === 'resolved' && $originalStatus !== 'resolved') {
            $this->accountingService->postMaintenanceRecord($record);
            return;
        }

        if ($originalStatus === 'resolved' && $record->status !== 'resolved') {
            $this->accountingService->voidMaintenanceRecord($record);
            return;
        }
    }

    /**
     * Handle the MaintenanceRecord "deleting" event.
     * Void the expense entry before deletion if it was resolved.
     */
    public function deleting(MaintenanceRecord $record): void
    {
        if ($record->status === 'resolved' && $record->cost > 0) {
            $this->accountingService->voidMaintenanceRecord($record);
        }
    }
}
