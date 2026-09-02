<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaseTerminationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_lease_with_a_past_end_date_is_recorded_as_terminated_and_unit_stays_vacant(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createUnit($property->id);
        $tenant = $this->createTenant($property->id, 'a@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', [
            'tenant_mode' => 'existing',
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'start_date' => '2019-01-01',
            'end_date' => '2020-01-01',
            'duration_months' => 12,
            'payment_cycle' => 3,
            'monthly_rent' => 500,
            'deposit' => 500,
        ])->assertRedirect();

        $lease = Lease::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('terminated', $lease->status);
        $this->assertSame('vacant', $unit->fresh()->status);
        $this->assertSame(0, $lease->installments()->count());
    }

    public function test_terminating_an_active_lease_keeps_the_record_and_vacates_the_unit(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createUnit($property->id);
        $tenant = $this->createTenant($property->id, 'a@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', $this->leasePayload($tenant->id, $unit->id));
        $lease = Lease::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('active', $lease->status);

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->patch("/leases/{$lease->id}", ['action' => 'terminate'])
            ->assertRedirect();

        $lease->refresh();
        $this->assertSame('terminated', $lease->status);
        $this->assertDatabaseHas('leases', ['id' => $lease->id, 'deleted_at' => null]);
        $this->assertSame('vacant', $unit->fresh()->status);
    }

    public function test_terminating_one_of_two_co_tenant_leases_keeps_the_unit_occupied(): void
    {
        $property = $this->createProperty();
        $superuser = $this->createSuperuser();
        $unit = $this->createUnit($property->id);
        $tenantA = $this->createTenant($property->id, 'a@example.com');
        $tenantB = $this->createTenant($property->id, 'b@example.com');

        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', $this->leasePayload($tenantA->id, $unit->id));
        $this->actingAsPropertySuperuser($superuser, $property->id)->post('/leases', $this->leasePayload($tenantB->id, $unit->id));

        $leaseA = Lease::where('tenant_id', $tenantA->id)->firstOrFail();

        $this->actingAsPropertySuperuser($superuser, $property->id)
            ->patch("/leases/{$leaseA->id}", ['action' => 'terminate'])
            ->assertRedirect();

        $this->assertSame('occupied', $unit->fresh()->status);
    }

    private function createProperty(): Property
    {
        return Property::create([
            'name' => 'Termination Tower',
            'code' => 'TRM',
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
