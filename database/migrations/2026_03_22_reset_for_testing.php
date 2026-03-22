<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Delete all journal lines and entries (they reference payments/leases)
            DB::table('journal_lines')->delete();
            DB::table('journal_entries')->delete();

            // Delete all payments
            DB::table('payments')->delete();

            // Delete all invoices and invoice items
            DB::table('invoice_items')->delete();
            DB::table('invoices')->delete();

            // Delete all leases
            DB::table('leases')->delete();

            // Reset all account balances to 0
            DB::table('accounts')->update([
                'balance' => 0,
                'ytd_activity' => 0,
            ]);
        });
    }

    public function down(): void
    {
        // Rollback not implemented
    }
};
