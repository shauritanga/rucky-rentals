<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('property_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete()
                ->after('property_name');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Property::class);
            $table->dropColumn('property_id');
        });
    }
};
