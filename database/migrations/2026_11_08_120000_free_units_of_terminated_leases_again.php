<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Recording payments on a historical lease marked its unit occupied again; free the units that only have terminated leases. */
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
        // Not reversible.
    }
};
