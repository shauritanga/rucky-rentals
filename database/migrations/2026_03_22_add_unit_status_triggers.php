<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Trigger function: When a lease is created, mark unit as occupied
        DB::unprepared('
            CREATE OR REPLACE FUNCTION update_unit_on_lease_insert()
            RETURNS TRIGGER AS $$
            BEGIN
                UPDATE units SET status = \'occupied\' WHERE id = NEW.unit_id;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::unprepared('
            CREATE TRIGGER update_unit_status_on_lease_create
            AFTER INSERT ON leases
            FOR EACH ROW
            EXECUTE FUNCTION update_unit_on_lease_insert();
        ');

        // Trigger function: When a lease is deleted, check if unit still has leases
        DB::unprepared('
            CREATE OR REPLACE FUNCTION update_unit_on_lease_delete()
            RETURNS TRIGGER AS $$
            BEGIN
                UPDATE units 
                SET status = CASE 
                    WHEN (SELECT COUNT(*) FROM leases WHERE unit_id = OLD.unit_id) > 0 
                    THEN \'occupied\' 
                    ELSE \'vacant\' 
                END
                WHERE id = OLD.unit_id;
                RETURN OLD;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::unprepared('
            CREATE TRIGGER update_unit_status_on_lease_delete
            AFTER DELETE ON leases
            FOR EACH ROW
            EXECUTE FUNCTION update_unit_on_lease_delete();
        ');

        // Trigger function: When a lease status changes
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

        DB::unprepared('
            CREATE TRIGGER update_unit_status_on_lease_update
            AFTER UPDATE ON leases
            FOR EACH ROW
            EXECUTE FUNCTION update_unit_on_lease_update();
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS update_unit_status_on_lease_create ON leases CASCADE');
        DB::unprepared('DROP TRIGGER IF EXISTS update_unit_status_on_lease_delete ON leases CASCADE');
        DB::unprepared('DROP TRIGGER IF EXISTS update_unit_status_on_lease_update ON leases CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS update_unit_on_lease_insert()');
        DB::unprepared('DROP FUNCTION IF EXISTS update_unit_on_lease_delete()');
        DB::unprepared('DROP FUNCTION IF EXISTS update_unit_on_lease_update()');
    }
};
