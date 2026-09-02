<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
        });

        DB::table('tenants')
            ->where(function ($q) {
                $q->where('tenant_type', 'individual')->orWhereNull('tenant_type');
            })
            ->orderBy('id')
            ->select('id', 'name')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    $parts = preg_split('/\s+/', trim((string) $row->name), 2);
                    DB::table('tenants')->where('id', $row->id)->update([
                        'first_name' => $parts[0] !== '' ? $parts[0] : null,
                        'last_name' => $parts[1] ?? null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
