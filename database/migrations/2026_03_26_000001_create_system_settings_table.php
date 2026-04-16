<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default values
        $defaults = [
            'company_name'         => 'Mwamba Properties Ltd',
            'company_registration' => 'CPR-2021-00482',
            'vat_number'           => 'P051234567M',
            'default_currency'     => 'TZS',
            'default_country'      => 'Tanzania',
            'support_email'        => 'support@rukyrentals.co.tz',
            'min_lease_months'     => '12',
            'deposit_multiplier'   => '2',
            'late_fee_days'        => '7',
            'late_fee_percent'     => '5',
            'expiry_warning_days'  => '60',
            'auto_renew'           => 'no',
            // Notification toggles
            'notif_new_property'   => '1',
            'notif_manager_changes'=> '1',
            'notif_failed_logins'  => '1',
            'notif_lease_approved' => '0',
            'notif_overdue_rent'   => '1',
            'notif_system_errors'  => '1',
            // Security toggles
            'require_2fa'          => '1',
            'allow_sso'            => '0',
            'session_timeout'      => '30',
            'audit_logging'        => '1',
            'failed_login_alerts'  => '1',
            // Role permissions JSON
            'role_permissions'     => json_encode([
                'manager'   => ['Dashboard','Units','Tenants','Leases','Payments','Invoices','Reports'],
                'accountant'=> ['Dashboard','Tenants','Leases','Payments','Accounting','Reports'],
                'viewer'    => ['Dashboard','Units','Tenants','Reports'],
            ]),
        ];

        foreach ($defaults as $key => $value) {
            DB::table('system_settings')->insertOrIgnore([
                'key'        => $key,
                'value'      => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
