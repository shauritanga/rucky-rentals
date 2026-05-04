<?php

namespace Tests\Feature;

use App\Models\MaintenanceRecord;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MaintenanceStaffGlobalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_superuser_can_create_global_maintenance_staff(): void
    {
        Mail::fake();

        $superuser = User::factory()->create([
            'role' => 'superuser',
            'status' => 'active',
            'must_change_password' => false,
        ]);

        $property = $this->createProperty('Alpha Tower', 'ALP');

        $response = $this->actingAs($superuser)->post('/superuser/managers', [
            'name' => 'Global Technician',
            'email' => 'tech@example.com',
            'phone' => '255700000100',
            'role' => 'maintenance_staff',
            'property_id' => $property->id,
            'twoFA' => 'yes',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'tech@example.com',
            'role' => 'maintenance_staff',
            'property_id' => null,
        ]);
    }

    public function test_manager_cannot_create_maintenance_staff_from_team_module(): void
    {
        $property = $this->createProperty('Beta Tower', 'BET');
        $manager = $this->createManager($property);

        $response = $this->actingAs($manager)->post('/team', [
            'name' => 'Blocked Technician',
            'email' => 'blocked@example.com',
            'phone' => '255700000101',
            'role' => 'maintenance_staff',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
    }

    public function test_global_maintenance_staff_can_filter_maintenance_by_property(): void
    {
        $propertyA = $this->createProperty('Gamma Tower', 'GAM');
        $propertyB = $this->createProperty('Delta Tower', 'DEL');
        $unitA = Unit::create($this->storedUnitAttributes($propertyA->id, 'A-101'));
        $unitB = Unit::create($this->storedUnitAttributes($propertyB->id, 'B-101'));

        $staff = User::factory()->create([
            'role' => 'maintenance_staff',
            'status' => 'active',
            'property_id' => null,
            'must_change_password' => false,
        ]);

        MaintenanceRecord::create($this->ticketAttributes($propertyA->id, $unitA->id, 'A-101', 'Alpha leak'));
        MaintenanceRecord::create($this->ticketAttributes($propertyB->id, $unitB->id, 'B-101', 'Beta light'));

        $this->actingAs($staff)
            ->get('/maintenance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Maintenance/Index')
                ->has('properties', 2)
                ->has('tickets', 2)
                ->has('units', 2)
                ->where('selectedPropertyId', null)
            );

        $this->actingAs($staff)
            ->get('/maintenance?property_id=' . $propertyA->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Maintenance/Index')
                ->where('selectedPropertyId', $propertyA->id)
                ->has('tickets', 1)
                ->where('tickets.0.title', 'Alpha leak')
                ->has('units', 1)
                ->where('units.0.unit_number', 'A-101')
            );
    }

    public function test_global_maintenance_staff_must_choose_property_when_creating_ticket(): void
    {
        $staff = User::factory()->create([
            'role' => 'maintenance_staff',
            'status' => 'active',
            'property_id' => null,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($staff)->post('/maintenance', [
            'title' => 'Common area repair',
            'description' => 'Needs inspection',
            'unit_ref' => 'Common',
            'category' => 'General',
            'priority' => 'med',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, MaintenanceRecord::count());
    }

    private function createProperty(string $name, string $code): Property
    {
        return Property::create([
            'name' => $name,
            'code' => $code,
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

    private function createManager(Property $property): User
    {
        return User::factory()->create([
            'role' => 'manager',
            'status' => 'active',
            'property_id' => $property->id,
            'must_change_password' => false,
        ]);
    }

    private function storedUnitAttributes(int $propertyId, string $unitNumber): array
    {
        return [
            'property_id' => $propertyId,
            'unit_number' => $unitNumber,
            'floor' => '1',
            'type' => 'Shop',
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
        ];
    }

    private function ticketAttributes(int $propertyId, int $unitId, string $unitRef, string $title): array
    {
        return [
            'property_id' => $propertyId,
            'unit_id' => $unitId,
            'ticket_number' => 'TK-' . strtoupper(substr(md5($title), 0, 6)),
            'title' => $title,
            'description' => null,
            'unit_ref' => $unitRef,
            'category' => 'General',
            'priority' => 'med',
            'status' => 'open',
            'workflow_status' => 'submitted',
            'reported_by' => 'System',
            'reported_date' => '2026-05-01',
            'notes' => [],
        ];
    }
}
