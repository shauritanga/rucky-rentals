<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Clear remaining journal entries and lines for completely fresh slate
        DB::table('journal_lines')->delete();
        DB::table('journal_entries')->delete();
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER SEQUENCE journal_entries_id_seq RESTART WITH 1');
            DB::statement('ALTER SEQUENCE journal_lines_id_seq RESTART WITH 1');
        }
    }

    public function down(): void
    {
        // Cannot rollback data deletion - this is intentional
    }
};
