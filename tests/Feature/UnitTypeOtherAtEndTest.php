<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use App\Support\UnitTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitTypeOtherAtEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_unit_types_have_other_at_the_end(): void
    {
        $types = UnitTypes::merge(null);
        $this->assertNotEmpty($types);
        $this->assertSame('Other', end($types));
    }

    public function test_dynamic_types_are_always_placed_before_other_option(): void
    {
        $custom = ['Penthouse', 'Duplex'];
        $merged = UnitTypes::merge($custom);

        $this->assertSame('Other', end($merged));
        $this->assertContains('Penthouse', $merged);
        $this->assertContains('Duplex', $merged);

        // Verify Penthouse and Duplex appear before Other
        $otherIndex = array_search('Other', $merged, true);
        $penthouseIndex = array_search('Penthouse', $merged, true);
        $duplexIndex = array_search('Duplex', $merged, true);

        $this->assertLessThan($otherIndex, $penthouseIndex);
        $this->assertLessThan($otherIndex, $duplexIndex);
    }

    public function test_creating_dynamic_type_via_post_endpoint_maintains_other_at_end(): void
    {
        $property = Property::create([
            'name' => 'Grand Tower',
            'code' => 'GT01',
            'address' => 'Ocean Road',
            'city' => 'Dar es Salaam',
            'country' => 'Tanzania',
            'status' => 'active',
            'unit_count' => 0,
            'occupied_units' => 0,
        ]);

        $manager = User::factory()->create([
            'property_id' => $property->id,
            'role' => 'manager',
            'must_change_password' => false,
            'status' => 'active',
        ]);

        $response = $this->actingAs($manager)->post('/units/types', [
            'type' => 'Executive Suite',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('newUnitType', 'Executive Suite');

        $propertyTypes = $property->fresh()->unitTypeList();
        $this->assertSame('Other', end($propertyTypes));

        $otherIndex = array_search('Other', $propertyTypes, true);
        $execIndex = array_search('Executive Suite', $propertyTypes, true);

        $this->assertNotFalse($execIndex);
        $this->assertLessThan($otherIndex, $execIndex);
    }

    public function test_cannot_create_other_as_duplicate_dynamic_type(): void
    {
        $property = Property::create([
            'name' => 'Grand Tower',
            'code' => 'GT02',
            'address' => 'Ocean Road',
            'city' => 'Dar es Salaam',
            'country' => 'Tanzania',
            'status' => 'active',
            'unit_count' => 0,
            'occupied_units' => 0,
        ]);

        $manager = User::factory()->create([
            'property_id' => $property->id,
            'role' => 'manager',
            'must_change_password' => false,
            'status' => 'active',
        ]);

        $response = $this->actingAs($manager)->post('/units/types', [
            'type' => 'other',
        ]);

        $response->assertSessionHasErrors('type');
    }
}
