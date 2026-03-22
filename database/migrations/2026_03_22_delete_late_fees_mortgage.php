<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Delete accounts and related journal entries
        DB::transaction(function () {
            // Find the account IDs for Late Fees and Mortgage Payable
            $lateFeesId = DB::table('accounts')->where('code', '4100')->value('id');
            $mortgageId = DB::table('accounts')->where('code', '2500')->value('id');

            // Delete journal lines that reference these accounts
            if ($lateFeesId) {
                DB::table('journal_lines')->where('account_code', '4100')->delete();
            }
            if ($mortgageId) {
                DB::table('journal_lines')->where('account_code', '2500')->delete();
            }

            // Delete the accounts
            DB::table('accounts')->whereIn('code', ['4100', '2500'])->delete();
        });
    }

    public function down(): void
    {
        // Rollback would require re-inserting accounts
        // Not implemented as this is a destructive cleanup
    }
};
