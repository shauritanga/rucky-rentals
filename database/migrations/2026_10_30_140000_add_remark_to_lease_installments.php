<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_installments', function (Blueprint $table) {
            // Free-text note shown in the Floor Tenant Schedule report ("Clear to 14th Dec 2026").
            $table->string('remark', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lease_installments', function (Blueprint $table) {
            $table->dropColumn('remark');
        });
    }
};
