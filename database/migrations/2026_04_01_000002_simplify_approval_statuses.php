<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Widen enum to include 'pending' alongside old values
        DB::statement("ALTER TABLE leases MODIFY COLUMN status ENUM('pending','pending_accountant','pending_pm','active','expiring','overdue','rejected','terminated') DEFAULT 'pending'");

        // Step 2: Migrate data
        DB::table('leases')
            ->whereIn('status', ['pending_accountant', 'pending_pm'])
            ->update(['status' => 'pending']);

        // Step 3: Narrow enum to remove old values
        DB::statement("ALTER TABLE leases MODIFY COLUMN status ENUM('pending','active','expiring','overdue','rejected','terminated') DEFAULT 'pending'");

        // Maintenance: collapse pending_manager → submitted
        DB::table('maintenance_records')
            ->where('workflow_status', 'pending_manager')
            ->update(['workflow_status' => 'submitted']);

        DB::statement("ALTER TABLE maintenance_records MODIFY COLUMN workflow_status ENUM('submitted','approved','in_progress','resolved','rejected') DEFAULT 'submitted'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE leases MODIFY COLUMN status ENUM('pending','pending_accountant','pending_pm','active','expiring','overdue','rejected','terminated') DEFAULT 'pending_accountant'");

        DB::table('leases')
            ->where('status', 'pending')
            ->update(['status' => 'pending_accountant']);

        DB::statement("ALTER TABLE maintenance_records MODIFY COLUMN workflow_status ENUM('submitted','pending_manager','approved','in_progress','resolved','rejected') DEFAULT 'submitted'");
    }
};
