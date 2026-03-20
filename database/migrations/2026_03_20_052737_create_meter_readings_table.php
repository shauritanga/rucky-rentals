<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('month'); // e.g. "2026-03"
            $table->decimal('prev_reading', 10, 2);
            $table->decimal('curr_reading', 10, 2);
            $table->decimal('gen_kwh', 8, 2)->default(0);
            $table->date('reading_date');
            $table->string('recorded_by')->default('James Mwangi');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meter_readings');
    }
};
