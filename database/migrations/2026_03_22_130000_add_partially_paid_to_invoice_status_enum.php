<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('draft','proforma','unpaid','partially_paid','paid','overdue') NOT NULL DEFAULT 'unpaid'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE invoices SET status = 'unpaid' WHERE status = 'partially_paid'");
            DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('draft','proforma','unpaid','paid','overdue') NOT NULL DEFAULT 'unpaid'");
        }
    }
};
