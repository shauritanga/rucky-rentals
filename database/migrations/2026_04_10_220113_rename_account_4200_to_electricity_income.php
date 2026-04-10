<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Account 4200 may have been auto-created before the Electricity Income
 * template was added — resolveAccount() uses firstOrCreate() so it only
 * sets the name at creation time and never renames an existing row.
 *
 * This migration corrects the name, type, and category on every existing
 * account 4200 across all properties.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounts')
            ->where('code', '4200')
            ->update([
                'name'     => 'Electricity Income',
                'type'     => 'revenue',
                'category' => 'Operating Revenue',
            ]);
    }

    public function down(): void
    {
        // Best-effort rollback — restore the previous generic name
        DB::table('accounts')
            ->where('code', '4200')
            ->where('name', 'Electricity Income')
            ->update([
                'name'     => 'Other Income',
                'type'     => 'revenue',
                'category' => 'Operating Revenue',
            ]);
    }
};
