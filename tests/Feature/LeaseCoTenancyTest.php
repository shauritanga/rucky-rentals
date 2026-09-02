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

    public function test_second_active_lease_can_be_created_for_an_already_occupied_unit(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createUnit($property->id);
        $tenantA = $this->createTenant($property->id, 'a@example.com');
        $tenantB = $this->createTenant($property->id, 'b@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->post('/leases', $this->leasePayload($tenantA->id, $unit->id))
            ->assertRedirect();

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->post('/leases', $this->leasePayload($tenantB->id, $unit->id))
            ->assertRedirect();

        $this->assertSame(2, Lease::where('unit_id', $unit->id)->where('status', 'active')->count());
        $this->assertSame('occupied', $unit->fresh()->status);
    }

    public function test_deleting_one_of_two_active_leases_keeps_the_unit_occupied(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createUnit($property->id);
        $tenantA = $this->createTenant($property->id, 'a@example.com');
        $tenantB = $this->createTenant($property->id, 'b@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', $this->leasePayload($tenantA->id, $unit->id));
        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', $this->leasePayload($tenantB->id, $unit->id));

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

    public function test_moving_a_lease_to_a_new_unit_only_vacates_old_unit_if_no_other_lease_remains(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unitA = $this->createUnit($property->id, 'A.01');
        $unitB = $this->createUnit($property->id, 'A.02');
        $tenantA = $this->createTenant($property->id, 'a@example.com');
        $tenantB = $this->createTenant($property->id, 'b@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', $this->leasePayload($tenantA->id, $unitA->id));
        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', $this->leasePayload($tenantB->id, $unitA->id));

        $leaseB = Lease::where('tenant_id', $tenantB->id)->firstOrFail();

        $editPayload = $this->leasePayload($tenantB->id, $unitB->id) + ['action' => 'edit'];

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->patch("/leases/{$leaseB->id}", $editPayload)
            ->assertRedirect();

        $this->assertSame('occupied', $unitA->fresh()->status, 'unitA still has tenantA active lease');
        $this->assertSame('occupied', $unitB->fresh()->status);
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

    private function leasePayload(int $tenantId, int $unitId): array
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
            'deposit' => 500,
        ];
    }
}
