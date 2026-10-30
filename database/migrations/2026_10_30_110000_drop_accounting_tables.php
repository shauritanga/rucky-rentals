<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The accounting module (chart of accounts, journal entries and lines, accounting events)
 * was removed. Databases that already have its tables drop them here; fresh installs never
 * create them. Order matters because journal_lines references journal_entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_events');
        Schema::dropIfExists('accounts');

        // The "Accounting" module no longer exists: take it out of saved role permissions.
        if (Schema::hasTable('system_settings')) {
            $row = DB::table('system_settings')->where('key', 'role_permissions')->first();
            $perms = $row ? json_decode((string) $row->value, true) : null;
            if (is_array($perms)) {
                foreach ($perms as $role => $modules) {
                    if (is_array($modules)) {
                        $perms[$role] = array_values(array_filter($modules, fn ($m) => $m !== 'Accounting'));
                    }
                }
                DB::table('system_settings')->where('key', 'role_permissions')->update(['value' => json_encode($perms)]);
            }
        }

        // ... and from per-user permission overrides.
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'permissions')) {
            foreach (DB::table('users')->whereNotNull('permissions')->get(['id', 'permissions']) as $user) {
                $perms = json_decode((string) $user->permissions, true);
                if (is_array($perms) && array_key_exists('accounting', $perms)) {
                    unset($perms['accounting']);
                    DB::table('users')->where('id', $user->id)->update(['permissions' => json_encode($perms)]);
                }
            }
        }
    }

    public function down(): void
    {
        // The accounting module no longer exists; its tables are not recreated.
    }
};
