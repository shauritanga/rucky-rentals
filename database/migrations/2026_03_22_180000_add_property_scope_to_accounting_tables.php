<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique('accounts_code_unique');
            $table->unique(['property_id', 'code']);
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('source_type')->nullable()->after('status');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
        });

        $defaultPropertyId = DB::table('properties')->orderBy('id')->value('id');

        if (!empty($defaultPropertyId)) {
            DB::table('accounts')->whereNull('property_id')->update(['property_id' => $defaultPropertyId]);
            DB::table('journal_entries')->whereNull('property_id')->update(['property_id' => $defaultPropertyId]);
        }
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['source_id', 'source_type']);
            $table->dropConstrainedForeignId('property_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique('accounts_property_id_code_unique');
            $table->unique('code');
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
