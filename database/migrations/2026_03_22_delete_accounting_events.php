<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Clear accounting events for a completely fresh slate
        DB::table('accounting_events')->delete();
        DB::statement('ALTER SEQUENCE accounting_events_id_seq RESTART WITH 1');
    }

    public function down(): void
    {
        // Cannot rollback data deletion - this is intentional
    }
};
