<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Sign-in is email + password only; the email-code (OTP) step and its table are gone. */
    public function up(): void
    {
        Schema::dropIfExists('login_otps');

        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->where('key', 'require_2fa')->delete();
        }
    }

    public function down(): void
    {
        // The OTP feature was removed on purpose; nothing to restore.
    }
};
