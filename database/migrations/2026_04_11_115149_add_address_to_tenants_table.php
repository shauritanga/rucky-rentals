<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('address', 255)->nullable()->after('phone');
            $table->string('city', 120)->nullable()->after('address');
            $table->string('country', 120)->nullable()->default('Tanzania')->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['address', 'city', 'country']);
        });
    }
};
