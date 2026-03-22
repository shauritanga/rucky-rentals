<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Reset all unit statuses to 'vacant' since all leases were deleted
        DB::table('units')->update(['status' => 'vacant']);
    }

    public function down(): void
    {
        // Rollback not implemented
    }
};
