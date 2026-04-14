<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Delete in order to respect foreign key constraints
        DB::table('payments')->delete();
        DB::table('invoice_items')->delete();
        DB::table('invoices')->delete();
        DB::table('lease_installments')->delete();
        DB::table('leases')->delete();

        // Reset auto-increment counters
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER SEQUENCE payments_id_seq RESTART WITH 1');
            DB::statement('ALTER SEQUENCE invoice_items_id_seq RESTART WITH 1');
            DB::statement('ALTER SEQUENCE invoices_id_seq RESTART WITH 1');
            DB::statement('ALTER SEQUENCE lease_installments_id_seq RESTART WITH 1');
            DB::statement('ALTER SEQUENCE leases_id_seq RESTART WITH 1');
        }
    }

    public function down(): void
    {
        // Cannot rollback data deletion - this is intentional
    }
};
