<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('
                UPDATE payments
                SET property_id = (
                    SELECT property_id FROM units WHERE units.id = payments.unit_id
                )
                WHERE property_id IS NULL
            ');
        } else {
            DB::statement('UPDATE payments p SET property_id = u.property_id FROM units u WHERE p.unit_id = u.id');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
