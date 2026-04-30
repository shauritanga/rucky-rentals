<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('requested_by_user_id')->nullable()->after('property_id')->constrained('users')->nullOnDelete();
            $table->string('approval_status', 20)->nullable()->after('status');
            $table->timestamp('approval_requested_at')->nullable()->after('approval_status');
            $table->timestamp('approval_decided_at')->nullable()->after('approval_requested_at');
            $table->foreignId('approval_decided_by')->nullable()->after('approval_decided_at')->constrained('users')->nullOnDelete();
            $table->text('approval_note')->nullable()->after('approval_decided_by');
            $table->timestamp('sent_to_tenant_at')->nullable()->after('approval_note');
            $table->foreignId('sent_to_tenant_by')->nullable()->after('sent_to_tenant_at')->constrained('users')->nullOnDelete();
        });

        DB::table('invoices')
            ->where('type', 'proforma')
            ->where('status', 'draft')
            ->update([
                'approval_status' => 'draft',
            ]);

        DB::table('invoices')
            ->where('type', 'proforma')
            ->where('status', 'proforma')
            ->update([
                'approval_status' => 'approved',
                'approval_requested_at' => DB::raw('created_at'),
                'approval_decided_at' => DB::raw('created_at'),
            ]);

        DB::table('invoices')
            ->where('type', 'proforma')
            ->where('status', 'proforma')
            ->whereNotNull('tenant_email')
            ->update([
                'sent_to_tenant_at' => DB::raw('created_at'),
            ]);

        DB::table('invoices')
            ->where('type', 'invoice')
            ->update([
                'approval_status' => 'approved',
                'approval_requested_at' => DB::raw('created_at'),
                'approval_decided_at' => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sent_to_tenant_by');
            $table->dropColumn('sent_to_tenant_at');
            $table->dropColumn('approval_note');
            $table->dropConstrainedForeignId('approval_decided_by');
            $table->dropColumn('approval_decided_at');
            $table->dropColumn('approval_requested_at');
            $table->dropColumn('approval_status');
            $table->dropConstrainedForeignId('requested_by_user_id');
        });
    }
};
