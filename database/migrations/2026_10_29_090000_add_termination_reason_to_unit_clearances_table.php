<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_clearances', function (Blueprint $table) {
            $table->string('termination_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('unit_clearances', function (Blueprint $table) {
            $table->dropColumn('termination_reason');
        });
    }
};
