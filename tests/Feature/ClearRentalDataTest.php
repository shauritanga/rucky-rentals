<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClearRentalDataTest extends TestCase
{
    use RefreshDatabase;

    private function seedRentalData(): array
    {
        $property = Property::create(['name' => 'Wipe Tower', 'code' => 'WIP', 'status' => 'active', 'unit_count' => 3, 'occupied_units' => 2, 'country' => 'Tanzania']);
        $user = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);
        $unit = Unit::create(['property_id' => $property->id, 'unit_number' => 'W1', 'floor' => 1, 'type' => 'Office', 'size_sqft' => 100, 'rent' => 1000, 'currency' => 'TZS', 'status' => 'occupied', 'approval_status' => 'approved', 'deposit' => 0, 'service_charge' => 0, 'electricity_type' => 'direct']);
        $tenant = Tenant::create(['property_id' => $property->id, 'name' => 'Wipe Tenant', 'email' => 'w@example.com', 'phone' => '+255700000000', 'initials' => 'WT', 'tenant_type' => 'individual']);
        $lease = Lease::create(['property_id' => $property->id, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'duration_months' => 12, 'payment_cycle' => 3, 'monthly_rent' => 1000, 'deposit' => 0, 'status' => 'active', 'currency' => 'TZS']);
        $invoice = Invoice::create(['property_id' => $property->id, 'lease_id' => $lease->id, 'invoice_number' => 'W/1', 'tenant_name' => 'Wipe Tenant', 'unit_ref' => 'W1', 'type' => 'proforma', 'status' => 'proforma', 'due_date' => now()->toDateString(), 'issued_date' => now()->toDateString(), 'currency' => 'TZS', 'approval_status' => 'approved']);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Rent', 'item_type' => 'rent', 'quantity' => 1, 'unit_price' => 1000, 'total' => 1000]);
        Payment::create(['property_id' => $property->id, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'invoice_id' => $invoice->id, 'amount' => 1500, 'amount_in_base' => 1500, 'currency' => 'TZS', 'status' => 'paid', 'method' => 'Cash', 'month' => 'x', 'paid_date' => now()->toDateString()]);
        SystemSetting::set('company_name', 'Keep Me Ltd');

        return [$property, $user];
    }

    public function test_it_empties_the_rental_data_and_keeps_properties_users_and_settings(): void
    {
        [$property, $user] = $this->seedRentalData();
        DB::table('audit_logs')->insert(['user_name' => 'x', 'action' => 'test', 'resource' => 'r', 'result' => 'success', 'category' => 'auth', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('rentals:clear-data --skip-backup')
            ->expectsQuestion('Type DELETE ALL RENTAL DATA to continue', 'DELETE ALL RENTAL DATA')
            ->assertSuccessful();

        foreach (['units', 'tenants', 'leases', 'invoices', 'invoice_items', 'payments', 'tenant_credits'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "$table should be empty");
        }
        $this->assertSame(1, Property::count());
        $this->assertSame(0, (int) Property::first()->unit_count);
        $this->assertNotNull(User::find($user->id));
        $this->assertSame('Keep Me Ltd', SystemSetting::get('company_name'));
        $this->assertSame(1, DB::table('audit_logs')->count());
    }

    public function test_a_wrong_confirmation_deletes_nothing(): void
    {
        $this->seedRentalData();

        $this->artisan('rentals:clear-data --skip-backup')
            ->expectsQuestion('Type DELETE ALL RENTAL DATA to continue', 'yes')
            ->assertFailed();

        $this->assertSame(1, Unit::count());
        $this->assertSame(1, Payment::count());
    }
}
