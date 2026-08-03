<?php

use App\Models\Lease;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->string('lease_number')->nullable()->unique()->after('id');
        });

        Lease::withTrashed()->whereNull('lease_number')->orderBy('id')->each(function (Lease $lease) {
            $lease->update(['lease_number' => 'LEASE-' . str_pad((string) $lease->id, 4, '0', STR_PAD_LEFT)]);
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn('lease_number');
        });
    }
};
