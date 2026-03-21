<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['superuser', 'manager', 'accountant', 'lease_manager', 'maintenance_staff', 'viewer'])
                ->default('manager')
                ->after('email');
            $table->foreignId('property_id')->nullable()->after('role')->constrained('properties')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
            $table->dropColumn('role');
        });
    }
};
