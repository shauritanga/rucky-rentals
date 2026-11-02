<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaseCoTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_co_tenant_unit_takes_all_its_tenants_together_with_equal_shares(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createCoTenantUnit($property->id);
        $tenantA = $this->createTenant($property->id, 'a@example.com');
        $tenantB = $this->createTenant($property->id, 'b@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->post('/leases', $this->leasePayload($tenantA->id, $unit->id) + ['tenant_ids' => [$tenantA->id, $tenantB->id]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $leases = Lease::where('unit_id', $unit->id)->get();
        $this->assertCount(2, $leases);
        $this->assertSame(1, $leases->pluck('co_tenant_group')->unique()->count());
        $this->assertNotNull($leases->first()->co_tenant_group);
        $this->assertEqualsCanonicalizing([500.0, 500.0], $leases->pluck('monthly_rent')->map(fn ($v) => (float) $v)->all());
        $this->assertSame('occupied', $unit->fresh()->status);
        $this->assertSame(2, Lease::where('unit_id', $unit->id)->where('status', 'active')->count());
    }

    public function test_a_co_tenant_unit_refuses_a_lease_with_a_single_tenant(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createCoTenantUnit($property->id);
        $tenantA = $this->createTenant($property->id, 'a@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->post('/leases', $this->leasePayload($tenantA->id, $unit->id))
            ->assertSessionHasErrors('tenant_ids');

        $this->assertSame(0, Lease::count());
    }

    public function test_the_same_tenant_cannot_be_entered_twice_on_a_co_tenant_unit(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createCoTenantUnit($property->id);
        $tenantA = $this->createTenant($property->id, 'a@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->post('/leases', $this->leasePayload($tenantA->id, $unit->id) + ['tenant_ids' => [$tenantA->id, $tenantA->id]])
            ->assertSessionHasErrors('tenant_ids');
    }

    public function test_a_single_tenant_unit_refuses_a_second_lease_and_a_group(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createUnit($property->id);
        $tenantA = $this->createTenant($property->id, 'a@example.com');
        $tenantB = $this->createTenant($property->id, 'b@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', $this->leasePayload($tenantA->id, $unit->id))->assertSessionHasNoErrors();

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->post('/leases', $this->leasePayload($tenantB->id, $unit->id))
            ->assertSessionHasErrors('unit_id');

        $this->assertSame(1, Lease::where('unit_id', $unit->id)->count());
    }

    public function test_deleting_one_of_two_active_leases_keeps_the_unit_occupied(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createCoTenantUnit($property->id);
        $tenantA = $this->createTenant($property->id, 'a@example.com');
        $tenantB = $this->createTenant($property->id, 'b@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->post('/leases', $this->leasePayload($tenantA->id, $unit->id) + ['tenant_ids' => [$tenantA->id, $tenantB->id]]);

        $leaseA = Lease::where('tenant_id', $tenantA->id)->firstOrFail();
        $leaseB = Lease::where('tenant_id', $tenantB->id)->firstOrFail();

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->delete("/leases/{$leaseA->id}")
            ->assertRedirect();

        $this->assertSame('occupied', $unit->fresh()->status);
        $this->assertSoftDeleted('leases', ['id' => $leaseA->id]);
        $this->assertDatabaseHas('leases', ['id' => $leaseB->id, 'deleted_at' => null]);
    }

    public function test_deleting_the_last_active_lease_vacates_the_unit(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createUnit($property->id);
        $tenant = $this->createTenant($property->id, 'a@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', $this->leasePayload($tenant->id, $unit->id));
        $lease = Lease::where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->delete("/leases/{$lease->id}")
            ->assertRedirect();

        $this->assertSame('vacant', $unit->fresh()->status);
    }

    public function test_a_co_tenant_lease_cannot_be_moved_to_another_unit(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unitA = $this->createCoTenantUnit($property->id, 'A.01');
        $unitB = $this->createUnit($property->id, 'A.02');
        $tenantA = $this->createTenant($property->id, 'a@example.com');
        $tenantB = $this->createTenant($property->id, 'b@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->post('/leases', $this->leasePayload($tenantA->id, $unitA->id) + ['tenant_ids' => [$tenantA->id, $tenantB->id]]);

        $leaseB = Lease::where('tenant_id', $tenantB->id)->firstOrFail();

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->patch("/leases/{$leaseB->id}", $this->leasePayload($tenantB->id, $unitB->id) + ['action' => 'edit'])
            ->assertSessionHasErrors('unit_id');

        $this->assertSame($unitA->id, $leaseB->fresh()->unit_id);
    }

    public function test_a_unit_can_only_be_set_to_single_tenant_when_it_has_one_lease(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createCoTenantUnit($property->id);
        $tenantA = $this->createTenant($property->id, 'a@example.com');
        $tenantB = $this->createTenant($property->id, 'b@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->post('/leases', $this->leasePayload($tenantA->id, $unit->id) + ['tenant_ids' => [$tenantA->id, $tenantB->id]]);

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->put("/units/{$unit->id}", [
                'unit_number' => 'F1.01', 'floor' => '1', 'type' => 'Office Suite', 'size_sqm' => 9.29,
                'rate_per_sqm' => 107.64, 'service_charge_per_sqm' => 0, 'currency' => 'TZS',
                'status' => 'occupied', 'electricity_type' => 'direct', 'allows_co_tenants' => false,
            ])
            ->assertSessionHasErrors('allows_co_tenants');
    }

    public function test_a_managers_group_is_submitted_together_and_one_approval_activates_everyone(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'property_id' => $property->id, 'must_change_password' => false]);
        $unit = $this->createCoTenantUnit($property->id);
        $unit->update(['rent' => 1000, 'service_charge' => 300]);
        $tenantA = $this->createTenant($property->id, 'a@example.com');
        $tenantB = $this->createTenant($property->id, 'b@example.com');

        $this->actingAs($manager)
            ->post('/leases', $this->leasePayload($tenantA->id, $unit->id, 100) + ['tenant_ids' => [$tenantA->id, $tenantB->id]])
            ->assertSessionHasNoErrors();

        $leases = Lease::where('unit_id', $unit->id)->get();
        $this->assertCount(2, $leases);
        $this->assertEqualsCanonicalizing(['pending_accountant', 'pending_accountant'], $leases->pluck('status')->all());
        // One approval request for the group, not one per tenant.
        \Illuminate\Support\Facades\Notification::assertSentTimes(\App\Notifications\LeaseApprovalRequestNotification::class, User::where('role', 'superuser')->count());

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->patch("/leases/{$leases->first()->id}", ['action' => 'approve_superuser'])
            ->assertSessionHasNoErrors();

        $leases = Lease::where('unit_id', $unit->id)->get();
        $this->assertEqualsCanonicalizing(['active', 'active'], $leases->pluck('status')->all());
        foreach ($leases as $lease) {
            $this->assertEquals(500.0, (float) $lease->monthly_rent);
            $this->assertEquals(150.0, (float) $lease->monthly_service_charge);
            $this->assertGreaterThan(0, $lease->installments()->count());
            // Every installment is the equal share: (500 + 150) a month.
            $first = $lease->installments()->orderBy('sequence')->first();
            $this->assertEquals(650.0 * 3, (float) $first->amount);
            $this->assertSame(1, \App\Models\Invoice::where('lease_id', $lease->id)->where('kind', 'deposit')->count());
        }
    }

    public function test_rejecting_one_lease_of_a_group_rejects_them_all(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'property_id' => $property->id, 'must_change_password' => false]);
        $unit = $this->createCoTenantUnit($property->id);
        $tenantA = $this->createTenant($property->id, 'a@example.com');
        $tenantB = $this->createTenant($property->id, 'b@example.com');

        $this->actingAs($manager)->post('/leases', $this->leasePayload($tenantA->id, $unit->id) + ['tenant_ids' => [$tenantA->id, $tenantB->id]]);
        $lease = Lease::where('unit_id', $unit->id)->firstOrFail();

        $this->actingAsPropertySuperuser($superuser, $property->id)->patch("/leases/{$lease->id}", ['action' => 'reject', 'reason' => 'Check the terms']);
        $this->assertEqualsCanonicalizing(['rejected', 'rejected'], Lease::where('unit_id', $unit->id)->pluck('status')->all());

        $this->actingAs($manager)->patch("/leases/{$lease->id}", ['action' => 'resubmit']);
        $this->assertEqualsCanonicalizing(['pending_accountant', 'pending_accountant'], Lease::where('unit_id', $unit->id)->pluck('status')->all());
    }

    public function test_changing_the_terms_of_one_co_tenant_changes_them_for_the_whole_group(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createCoTenantUnit($property->id);
        $tenantA = $this->createTenant($property->id, 'a@example.com');
        $tenantB = $this->createTenant($property->id, 'b@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->post('/leases', $this->leasePayload($tenantA->id, $unit->id) + ['tenant_ids' => [$tenantA->id, $tenantB->id]]);
        $leaseA = Lease::where('tenant_id', $tenantA->id)->firstOrFail();

        $payload = $this->leasePayload($tenantA->id, $unit->id) + ['action' => 'edit'];
        $payload['end_date'] = '2028-04-01';
        $payload['duration_months'] = 24;
        $payload['payment_cycle'] = 6;

        $this->actingAsPropertySuperuser($superuser, $property->id)->patch("/leases/{$leaseA->id}", $payload)->assertSessionHasNoErrors();

        foreach (Lease::where('unit_id', $unit->id)->get() as $lease) {
            $this->assertSame('2028-04-01', substr((string) $lease->end_date, 0, 10));
            $this->assertSame(6, (int) $lease->payment_cycle);
        }
    }

    private function createCoTenantUnit(int $propertyId, string $unitNumber = 'A.01', int $count = 2): Unit
    {
        $unit = $this->createUnit($propertyId, $unitNumber);
        $unit->update(['allows_co_tenants' => true, 'co_tenant_count' => $count]);

        return $unit;
    }

    private function createProperty(): Property
    {
        return Property::create([
            'name' => 'Co-Tenancy Tower',
            'code' => 'CTT',
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
