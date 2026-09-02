<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Soft-deleting a lease is an UPDATE (sets deleted_at), which fires this
        // trigger. Without filtering deleted_at, a soft-deleted lease still whose
        // status column is still 'active' would be counted as occupying the unit.
        // The status list is also aligned to the app's real vocabulary
        // (active/expiring/overdue) instead of the unused 'pending' literal.
        DB::unprepared('
            CREATE OR REPLACE FUNCTION update_unit_on_lease_update()
            RETURNS TRIGGER AS $$
            BEGIN
                UPDATE units
                SET status = CASE
                    WHEN (
                        SELECT COUNT(*) FROM leases
                        WHERE unit_id = NEW.unit_id
                        AND deleted_at IS NULL
                        AND status IN (\'active\', \'expiring\', \'overdue\')
                    ) > 0
                    THEN \'occupied\'
                    ELSE \'vacant\'
                END
                WHERE id = NEW.unit_id;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared('
            CREATE OR REPLACE FUNCTION update_unit_on_lease_update()
            RETURNS TRIGGER AS $$
            BEGIN
                UPDATE units
                SET status = CASE
                    WHEN (SELECT COUNT(*) FROM leases WHERE unit_id = NEW.unit_id AND status IN (\'active\', \'pending\')) > 0
                    THEN \'occupied\'
                    ELSE \'vacant\'
                END
                WHERE id = NEW.unit_id;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');
    }
};
