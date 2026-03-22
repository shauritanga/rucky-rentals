<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Delete journal lines that reference Mortgage Interest account
        DB::table('journal_lines')->where('account_code', '6000')->delete();

        // Delete the Mortgage Interest account
        DB::table('accounts')->where('code', '6000')->delete();
    }

    public function down(): void
    {
        // Rollback not implemented
    }
};
