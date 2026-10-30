<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->boolean('allows_co_tenants')->default(false);
            $table->unsignedTinyInteger('co_tenant_count')->nullable();
        });

        Schema::table('leases', function (Blueprint $table) {
            // Leases created together for one co-tenant unit share this id.
            $table->string('co_tenant_group', 36)->nullable()->index();
        });

        // A unit that already has overlapping leases is a co-tenant unit; its tenant
        // count is the most leases that ever ran at the same time. Leases are left as they are.
        $leases = DB::table('leases')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['rejected'])
            ->get(['unit_id', 'start_date', 'end_date'])
            ->groupBy('unit_id');

        foreach ($leases as $unitId => $rows) {
            $peak = 0;
            foreach ($rows as $row) {
                $overlap = $rows->filter(fn ($o) => $o->start_date <= $row->start_date && $o->end_date > $row->start_date)->count();
                $peak = max($peak, $overlap);
            }
            if ($peak >= 2) {
                DB::table('units')->where('id', $unitId)->update(['allows_co_tenants' => true, 'co_tenant_count' => $peak]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn('co_tenant_group');
        });
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['allows_co_tenants', 'co_tenant_count']);
        });
    }
};
