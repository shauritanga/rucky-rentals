<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitClearance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_clearance_snapshots_the_deposit_and_creates_a_scheduled_record(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createUnit($property->id);
        $tenant = $this->createTenant($property->id, 'a@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', $this->leasePayload($tenant->id, $unit->id, 500));
        $lease = $this->endLeaseTerm(Lease::where('tenant_id', $tenant->id)->firstOrFail());

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->post('/clearance', ['lease_id' => $lease->id])
            ->assertRedirect();

        $clearance = UnitClearance::where('lease_id', $lease->id)->firstOrFail();
        $this->assertSame('scheduled', $clearance->status);
        $this->assertEquals(500.0, (float) $clearance->deposit_amount);
        $this->assertEquals(500.0, (float) $clearance->refund_amount);
    }

    public function test_adding_damage_items_recomputes_deductions_on_the_server(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createUnit($property->id);
        $tenant = $this->createTenant($property->id, 'a@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', $this->leasePayload($tenant->id, $unit->id, 500));
        $lease = $this->endLeaseTerm(Lease::where('tenant_id', $tenant->id)->firstOrFail());
        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/clearance', ['lease_id' => $lease->id]);
        $clearance = UnitClearance::where('lease_id', $lease->id)->firstOrFail();

        $this->actingAsPropertySuperuser($superuser, $property->id)->post("/clearance/{$clearance->id}/items", [
            'category' => 'Walls', 'description' => 'Cracked wall', 'cost' => 150, 'responsible_party' => 'tenant',
        ])->assertRedirect();

        $this->actingAsPropertySuperuser($superuser, $property->id)->post("/clearance/{$clearance->id}/items", [
            'category' => 'Kitchen', 'description' => 'Broken tile', 'cost' => 100, 'responsible_party' => 'tenant',
        ])->assertRedirect();

        // A client could send any total in theory — recalculateTotals() must ignore it and sum the real rows.
        $this->actingAsPropertySuperuser($superuser, $property->id)->post("/clearance/{$clearance->id}/items", [
            'category' => 'Other', 'description' => 'Landlord wear and tear', 'cost' => 999, 'responsible_party' => 'landlord',
        ])->assertRedirect();

        $clearance->refresh();
        $this->assertEquals(250.0, (float) $clearance->total_deductions);
        $this->assertEquals(250.0, (float) ($clearance->deposit_amount - $clearance->refund_amount));
        $this->assertSame('in_progress', $clearance->status);
    }

    public function test_finalizing_a_clearance_terminates_the_lease_and_posts_a_balanced_gl_settlement(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createUnit($property->id);
        $tenant = $this->createTenant($property->id, 'a@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', $this->leasePayload($tenant->id, $unit->id, 500));
        $lease = $this->endLeaseTerm(Lease::where('tenant_id', $tenant->id)->firstOrFail());
        $this->assertSame('active', $lease->status);

        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/clearance', ['lease_id' => $lease->id]);
        $clearance = UnitClearance::where('lease_id', $lease->id)->firstOrFail();

        $this->actingAsPropertySuperuser($superuser, $property->id)->post("/clearance/{$clearance->id}/items", [
            'category' => 'Walls', 'description' => 'Cracked wall', 'cost' => 250, 'responsible_party' => 'tenant',
        ]);

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->post("/clearance/{$clearance->id}/finalize", ['confirm' => true])
            ->assertRedirect();

        $lease->refresh();
        $clearance->refresh();
        $this->assertSame('terminated', $lease->status);
        $this->assertSame('vacant', $unit->fresh()->status);
        $this->assertSame('completed', $clearance->status);
        $this->assertEquals(250.0, (float) $clearance->total_deductions);
        $this->assertEquals(250.0, (float) $clearance->refund_amount);
        $this->assertEquals(0.0, (float) $clearance->shortfall_amount);

        $entry = JournalEntry::where('reference', 'DEP-REF-' . $lease->id)
            ->where('status', 'posted')
            ->with('lines')
            ->firstOrFail();

        $debit = (float) $entry->lines->sum('debit');
        $credit = (float) $entry->lines->sum('credit');
        $this->assertEquals(500.0, $debit);
        $this->assertEquals(500.0, $credit);
        $this->assertEquals(250.0, (float) $entry->lines->where('account_code', '1000')->sum('credit'));
        $this->assertEquals(250.0, (float) $entry->lines->where('account_code', '4110')->sum('credit'));

        // The original deposit-received entry must be voided, not left posted alongside the settlement.
        $original = JournalEntry::where('reference', 'DEP-' . $lease->id)->first();
        $this->assertSame('void', $original->status);
    }

    public function test_shortfall_when_damage_exceeds_deposit_floors_the_refund_at_zero(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createUnit($property->id);
        $tenant = $this->createTenant($property->id, 'a@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', $this->leasePayload($tenant->id, $unit->id, 500));
        $lease = $this->endLeaseTerm(Lease::where('tenant_id', $tenant->id)->firstOrFail());
        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/clearance', ['lease_id' => $lease->id]);
        $clearance = UnitClearance::where('lease_id', $lease->id)->firstOrFail();

        $this->actingAsPropertySuperuser($superuser, $property->id)->post("/clearance/{$clearance->id}/items", [
            'category' => 'Walls', 'description' => 'Major damage', 'cost' => 700, 'responsible_party' => 'tenant',
        ]);

        $this->actingAsPropertySuperuser($superuser, $property->id)->post("/clearance/{$clearance->id}/finalize", ['confirm' => true]);

        $clearance->refresh();
        $this->assertEquals(0.0, (float) $clearance->refund_amount);
        $this->assertEquals(200.0, (float) $clearance->shortfall_amount);

        $entry = JournalEntry::where('reference', 'DEP-REF-' . $lease->id)->where('status', 'posted')->with('lines')->firstOrFail();
        $this->assertEquals(0.0, (float) $entry->lines->where('account_code', '1000')->sum('credit'));
        $this->assertEquals(500.0, (float) $entry->lines->where('account_code', '4110')->sum('credit'));
    }

    public function test_plain_terminate_is_blocked_when_the_lease_has_a_deposit_on_file(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createUnit($property->id);
        $tenant = $this->createTenant($property->id, 'a@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', $this->leasePayload($tenant->id, $unit->id, 500));
        $lease = Lease::where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->patch("/leases/{$lease->id}", ['action' => 'terminate'])
            ->assertStatus(422);

        $this->assertSame('active', $lease->fresh()->status);
    }

    public function test_clearance_is_blocked_while_the_lease_term_has_not_ended(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createUnit($property->id);
        $tenant = $this->createTenant($property->id, 'a@example.com');

        // leasePayload's end_date (2027-04-01) is in the future — lease term hasn't ended yet.
        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', $this->leasePayload($tenant->id, $unit->id, 500));
        $lease = Lease::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('active', $lease->status);

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->post('/clearance', ['lease_id' => $lease->id])
            ->assertStatus(422);

        $this->assertSame(0, UnitClearance::where('lease_id', $lease->id)->count());
    }

    public function test_only_manager_or_superuser_can_finalize_a_clearance(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createUnit($property->id);
        $tenant = $this->createTenant($property->id, 'a@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', $this->leasePayload($tenant->id, $unit->id, 500));
        $lease = $this->endLeaseTerm(Lease::where('tenant_id', $tenant->id)->firstOrFail());
        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/clearance', ['lease_id' => $lease->id]);
        $clearance = UnitClearance::where('lease_id', $lease->id)->firstOrFail();

        $viewer = User::factory()->create(['role' => 'viewer', 'status' => 'active', 'must_change_password' => false, 'property_id' => $property->id]);
        $this->actingAs($viewer)->post("/clearance/{$clearance->id}/finalize", ['confirm' => true])->assertStatus(403);

        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'must_change_password' => false, 'property_id' => $property->id]);
        $this->actingAs($manager)->post("/clearance/{$clearance->id}/finalize", ['confirm' => true])->assertRedirect();

        $this->assertSame('completed', $clearance->fresh()->status);
    }

    private function createProperty(): Property
    {
        return Property::create([
            'name' => 'Clearance Tower',
            'code' => 'CLR',
            'status' => 'active',
            'unit_count' => 0,
            'occupied_units' => 0,
            'country' => 'Tanzania',
            'floor_config' => [
                'basements' => 0,
                'has_ground_floor' => false,
                'has_mezzanine' => false,
                'upper_floors' => 7,
            ],
        ]);
    }

    private function createSuperuser(): User
    {
        return User::factory()->create([
            'role' => 'superuser',
            'status' => 'active',
            'must_change_password' => false,
        ]);
    }

    private function actingAsPropertySuperuser(User $superuser, int $propertyId)
    {
        return $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $propertyId]);
    }

    /**
     * Simulate time having passed since the lease was created, so its term has
     * naturally ended — clearance is only eligible once end_date is reached.
     */
    private function endLeaseTerm(Lease $lease): Lease
    {
        $lease->update(['end_date' => now()->subDay()->toDateString()]);

        return $lease->fresh();
    }

    private function createUnit(int $propertyId, string $unitNumber = 'A.01'): Unit
    {
        return Unit::create([
            'property_id' => $propertyId,
            'unit_number' => $unitNumber,
            'floor' => '1',
            'type' => 'Office Suite',
            'size_sqft' => 100,
            'size_sqm' => 9.29,
            'rate_per_sqm' => 107.64,
            'service_charge_per_sqm' => 0,
            'currency' => 'TZS',
            'rent' => 1000,
            'status' => 'vacant',
            'deposit' => 0,
            'service_charge' => 0,
            'electricity_type' => 'direct',
            'approval_status' => 'approved',
        ]);
    }

    private function createTenant(int $propertyId, string $email): Tenant
    {
        return Tenant::create([
            'property_id' => $propertyId,
            'name' => 'Test Tenant',
            'first_name' => 'Test',
            'last_name' => 'Tenant',
            'email' => $email,
            'phone' => '0700000000',
            'national_id' => '1234567890',
            'initials' => 'TT',
            'tenant_type' => 'individual',
            'nok_name' => 'Kin Name',
            'nok_phone' => '0700000001',
            'nok_relation' => 'Sibling',
        ]);
    }

    private function leasePayload(int $tenantId, int $unitId, float $deposit = 0): array
    {
        return [
            'tenant_mode' => 'existing',
            'tenant_id' => $tenantId,
            'unit_id' => $unitId,
            'start_date' => '2026-04-01',
            'end_date' => '2027-04-01',
            'duration_months' => 12,
            'payment_cycle' => 3,
            'monthly_rent' => 500,
            'deposit' => $deposit,
        ];
    }
}
