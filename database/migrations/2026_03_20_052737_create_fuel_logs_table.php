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
        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->date('log_date');
            $table->decimal('litres', 8, 2);
            $table->decimal('price_per_litre', 8, 2);
            $table->decimal('total_cost', 10, 2);
            $table->string('supplier')->nullable();
            $table->integer('level_after'); // percentage
            $table->string('recorded_by')->default('James Mwangi');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_logs');
    }
};
