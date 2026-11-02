<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A tenant can be saved before they have an email ("Email to be updated"). The unique index stays: many NULLs are allowed. */
    public function up(): void
    {
        Schema::table('tenants', function ($table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('tenants')->whereNull('email')->exists()) {
            return; // cannot make it required again while tenants still have no email
        }
        Schema::table('tenants', function ($table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
