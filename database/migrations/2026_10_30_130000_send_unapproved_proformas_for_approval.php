<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Electricity proformas used to be saved with no approval status, so they showed as
     * "Pending Approval" but never reached the superuser's approvals list. Put the ones
     * not yet sent into that list.
     */
    public function up(): void
    {
        DB::table('invoices')
            ->where('type', 'proforma')
            ->where('status', 'proforma')
            ->whereNull('approval_status')
            ->whereNull('sent_to_tenant_at')
            ->update(['approval_status' => 'pending_approval', 'approval_requested_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // Data fix only; nothing to undo.
    }
};
