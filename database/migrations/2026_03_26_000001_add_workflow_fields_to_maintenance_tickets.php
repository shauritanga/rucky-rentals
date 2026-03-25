<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->string('reported_by')->nullable()->after('assignee');
            $table->enum('workflow_status', [
                'submitted', 'pending_manager', 'approved', 'in_progress', 'resolved'
            ])->default('submitted')->after('status');
        });

        // Backfill workflow_status from existing status values
        DB::statement("UPDATE maintenance_tickets SET workflow_status = CASE
            WHEN status = 'resolved'    THEN 'resolved'
            WHEN status = 'in-progress' THEN 'in_progress'
            ELSE 'submitted' END");
    }

    public function down(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropColumn(['reported_by', 'workflow_status']);
        });
    }
};
