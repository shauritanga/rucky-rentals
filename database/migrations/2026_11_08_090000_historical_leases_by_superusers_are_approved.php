<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** A historical lease recorded by a superuser is approved by default; earlier ones were logged as merely "recorded". */
    public function up(): void
    {
        $superuserNames = DB::table('users')->where('role', 'superuser')->pluck('name')->all();

        DB::table('leases')->where('status', 'terminated')->whereNotNull('approval_log')->orderBy('id')->each(function ($lease) use ($superuserNames) {
            // The log is stored as JSON text inside JSON (the model encodes it twice); read it either way.
            $log = json_decode($lease->approval_log, true);
            $doubleEncoded = is_string($log);
            if ($doubleEncoded) {
                $log = json_decode($log, true);
            }
            if (!is_array($log)) {
                return;
            }

            $changed = false;
            foreach ($log as &$entry) {
                if (($entry['action'] ?? null) === 'recorded' && in_array($entry['by'] ?? null, $superuserNames, true)) {
                    $entry['action'] = 'approved';
                    $entry['by'] = $entry['by'] . ' (Superuser)';
                    $entry['text'] = 'Historical lease recorded and approved by superuser.';
                    $changed = true;
                }
            }
            unset($entry);

            if ($changed) {
                $encoded = json_encode($log);
                DB::table('leases')->where('id', $lease->id)->update(['approval_log' => $doubleEncoded ? json_encode($encoded) : $encoded]);
            }
        });
    }

    public function down(): void
    {
        // Wording of a log entry only; nothing to restore.
    }
};
