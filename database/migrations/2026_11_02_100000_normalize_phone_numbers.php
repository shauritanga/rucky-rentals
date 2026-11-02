<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Store existing phone numbers in international form (+255712345678). */
    public function up(): void
    {
        foreach (['tenants' => ['phone', 'nok_phone'], 'users' => ['phone'], 'properties' => ['phone']] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                DB::table($table)->whereNotNull($column)->where($column, '!=', '')->orderBy('id')->each(function ($row) use ($table, $column) {
                    $normalized = PhoneNumber::normalize($row->{$column});
                    if ($normalized !== null && $normalized !== $row->{$column}) {
                        DB::table($table)->where('id', $row->id)->update([$column => $normalized]);
                    }
                });
            }
        }
    }

    public function down(): void
    {
        // Formatting only; nothing to undo.
    }
};
