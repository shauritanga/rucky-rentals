<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('approval_status', 20)
                ->default('approved')
                ->after('status');
            $table->foreignId('requested_by_user_id')
                ->nullable()
                ->after('property_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approval_requested_at')
                ->nullable()
                ->after('updated_at');
            $table->timestamp('approval_decided_at')
                ->nullable()
                ->after('approval_requested_at');
            $table->text('approval_note')
                ->nullable()
                ->after('approval_decided_at');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by_user_id');
            $table->dropColumn([
                'approval_status',
                'approval_requested_at',
                'approval_decided_at',
                'approval_note',
            ]);
        });
    }
};
