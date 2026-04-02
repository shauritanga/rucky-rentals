<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('unit_id')->constrained('tenants')->nullOnDelete();
            $table->string('document_type', 30)->nullable()->after('tag');
        });

        DB::table('documents')->where('tag', 'lease')->update(['document_type' => 'lease_agreement']);
        DB::table('documents')->where('tag', 'id')->update(['document_type' => 'deposit']);
        DB::table('documents')->where('tag', 'notice')->update(['document_type' => 'handover']);
        DB::table('documents')->where('tag', 'other')->update(['document_type' => 'invoice']);
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('document_type');
        });
    }
};
