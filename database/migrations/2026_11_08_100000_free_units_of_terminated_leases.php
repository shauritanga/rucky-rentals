<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Recording a historical (terminated) lease used to mark its unit occupied. Free units that show occupied only because
     * of terminated leases: they have a terminated lease and no active, expiring, overdue or pending one.
     */
    public function up(): void
    {
        $live = ['active', 'expiring', 'overdue', 'pending_accountant', 'pending_pm'];

        DB::table('units')
            ->where('status', 'occupied')
            ->whereIn('id', DB::table('leases')->whereNull('deleted_at')->where('status', 'terminated')->select('unit_id'))
            ->whereNotIn('id', DB::table('leases')->whereNull('deleted_at')->whereIn('status', $live)->select('unit_id'))
            ->update(['status' => 'vacant']);
    }

    public function down(): void
    {
        // Not reversible: which units were wrongly occupied cannot be told apart afterwards.
    }
};
