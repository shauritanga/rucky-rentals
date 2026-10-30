<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function superuser(): User
    {
        return User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);
    }

    private function property(string $name = 'Empty Plaza', string $code = 'BLD90'): Property
    {
        return Property::create([
            'name' => $name, 'code' => $code, 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0,
            'country' => 'Tanzania', 'floor_config' => ['basements' => 0, 'upper_floors' => 3],
        ]);
    }

    public function test_superuser_can_delete_an_unused_property(): void
    {
        $property = $this->property();

        $this->actingAs($this->superuser())->delete("/superuser/properties/{$property->id}")
            ->assertRedirect()->assertSessionHas('success');

        $this->assertNull(Property::find($property->id));
    }

    public function test_property_with_units_cannot_be_deleted(): void
    {
        $property = $this->property();
        Unit::create([
            'property_id' => $property->id, 'unit_number' => 'A.01', 'floor' => '1', 'type' => 'Office Suite',
            'size_sqft' => 100, 'size_sqm' => 9.29, 'rate_per_sqm' => 10, 'service_charge_per_sqm' => 0,
            'currency' => 'TZS', 'rent' => 1000, 'status' => 'vacant', 'deposit' => 0, 'service_charge' => 0,
            'electricity_type' => 'direct', 'approval_status' => 'approved',
        ]);

        $this->actingAs($this->superuser())->delete("/superuser/properties/{$property->id}")
            ->assertRedirect()->assertSessionHas('error');

        $this->assertNotNull(Property::find($property->id));
    }

    public function test_property_with_tenants_cannot_be_deleted(): void
    {
        $property = $this->property();
        Tenant::create(['property_id' => $property->id, 'name' => 'T', 'email' => 't@example.com', 'phone' => '1', 'initials' => 'T', 'color' => 'x', 'text_color' => 'y']);

        $this->actingAs($this->superuser())->delete("/superuser/properties/{$property->id}")
            ->assertRedirect()->assertSessionHas('error');

        $this->assertNotNull(Property::find($property->id));
    }

    public function test_non_superuser_cannot_delete_a_property(): void
    {
        $property = $this->property();
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'must_change_password' => false, 'property_id' => $property->id]);

        $this->actingAs($manager)->delete("/superuser/properties/{$property->id}")->assertForbidden();

        $this->assertNotNull(Property::find($property->id));
    }

    public function test_property_form_no_longer_requires_or_stores_a_phone(): void
    {
        $this->actingAs($this->superuser())->post('/superuser/properties', [
            'name' => 'No Phone Tower', 'address' => 'Samora Ave', 'city' => 'Dar es Salaam', 'country' => 'Tanzania',
            'bank_name' => 'DTB', 'bank_account' => '123', 'bank_account_name' => 'Mwamba', 'swift_code' => 'DTKETZTZ',
            'status' => 'active', 'upper_floors' => 4, 'phone' => '+255700000000',
        ])->assertRedirect();

        $this->assertNull(Property::where('name', 'No Phone Tower')->value('phone'));
    }
}
