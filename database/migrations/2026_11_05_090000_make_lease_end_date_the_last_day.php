<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A lease's end date is now its last day (6 Apr 2026 for 12 months ends 5 Apr 2027). Leases saved earlier used the
     * same date a year later; move those back one day. Only leases whose end date is exactly start + duration are
     * touched, so leases that were ended early or edited by hand keep their dates.
     */
    public function up(): void
    {
        DB::table('leases')->whereNotNull('end_date')->whereNotNull('duration_months')->orderBy('id')->each(function ($lease) {
            $start = Carbon::parse($lease->rent_start_date ?: $lease->start_date)->startOfDay();
            $oldEnd = $start->copy()->addMonths((int) $lease->duration_months);

            if (Carbon::parse($lease->end_date)->startOfDay()->equalTo($oldEnd)) {
                DB::table('leases')->where('id', $lease->id)->update(['end_date' => $oldEnd->subDay()->toDateString()]);
            }
        });
    }

    public function down(): void
    {
        // Not reversible: the old and new dates cannot be told apart afterwards.
    }
};
