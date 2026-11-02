<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositRateOnProformaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_usd_lease_is_approved_without_any_rate_and_the_rate_is_typed_on_the_deposit_proforma(): void
    {
        [$superuser, $property, $manager, $lease] = $this->pendingLease('USD');

        // Approval asks for no rate and does not issue a deposit proforma that could not be priced.
        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])
            ->patch("/leases/{$lease->id}", ['action' => 'approve_superuser'])
            ->assertSessionHasNoErrors();

        $this->assertSame('active', $lease->fresh()->status);
        $this->assertSame(0, Invoice::where('lease_id', $lease->id)->where('kind', 'deposit')->count());

        // The proforma cannot be issued without a rate…
        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])
            ->post("/leases/{$lease->id}/deposit-invoice", [])
            ->assertSessionHasErrors('deposit_rate');

        // …and with one, the rate sits on the proforma, not on the lease.
        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])
            ->post("/leases/{$lease->id}/deposit-invoice", ['deposit_rate' => 2650])
            ->assertSessionHasNoErrors();

        $invoice = Invoice::where('lease_id', $lease->id)->where('kind', 'deposit')->firstOrFail();
        $this->assertEquals(2650.0, (float) $invoice->usd_rate);
        $this->assertEquals(1000 * 2650.0, (float) $invoice->total_in_base);
        $this->assertNull($lease->fresh()->exchange_rate);
    }

    public function test_a_tzs_lease_still_gets_its_deposit_proforma_on_approval(): void
    {
        [$superuser, $property, $manager, $lease] = $this->pendingLease('TZS');

        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])
            ->patch("/leases/{$lease->id}", ['action' => 'approve_superuser'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Invoice::where('lease_id', $lease->id)->where('kind', 'deposit')->count());
        $this->assertNull(Invoice::where('lease_id', $lease->id)->where('kind', 'deposit')->value('usd_rate'));
    }

    /** A manager submits a lease for a unit priced in $currency, with a deposit of 1,000. */
    private function pendingLease(string $currency): array
    {
        $property = Property::create(['name' => 'Rate Tower', 'code' => 'RAT', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0, 'country' => 'Tanzania']);
        $superuser = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'property_id' => $property->id, 'must_change_password' => false]);
        $unit = Unit::create([
            'property_id' => $property->id, 'unit_number' => 'F1.01', 'floor' => '1', 'type' => 'Office Suite', 'size_sqft' => 100, 'size_sqm' => 9.29,
            'rate_per_sqm' => 107.64, 'service_charge_per_sqm' => 0, 'currency' => $currency, 'rent' => 1000, 'status' => 'vacant', 'deposit' => 1000,
            'service_charge' => 0, 'electricity_type' => 'direct', 'approval_status' => 'approved',
        ]);
        $tenant = Tenant::create(['property_id' => $property->id, 'name' => 'Rate Tenant', 'email' => 'r@example.com', 'phone' => '0700000000', 'initials' => 'RT', 'tenant_type' => 'individual']);

        $this->actingAs($manager)->post('/leases', [
            'tenant_mode' => 'existing', 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'start_date' => '2026-04-01', 'end_date' => '2027-04-01',
            'duration_months' => 12, 'payment_cycle' => 3, 'monthly_rent' => 1000, 'deposit' => 1000,
        ])->assertSessionHasNoErrors();

        return [$superuser, $property, $manager, Lease::where('unit_id', $unit->id)->firstOrFail()];
    }
}
