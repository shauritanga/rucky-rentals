<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('country');
            $table->string('bank_name', 120)->nullable()->after('phone');
            $table->string('bank_account', 60)->nullable()->after('bank_name');
            $table->string('bank_account_name', 120)->nullable()->after('bank_account');
            $table->string('swift_code', 20)->nullable()->after('bank_account_name');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['phone', 'bank_name', 'bank_account', 'bank_account_name', 'swift_code']);
        });
    }
};
