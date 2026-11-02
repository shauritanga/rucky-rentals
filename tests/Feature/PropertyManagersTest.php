<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyManagersTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_property_can_have_several_managers_and_one_can_be_removed(): void
    {
        $superuser = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);
        $property = Property::create(['name' => 'Many Managers Tower', 'code' => 'MMT', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0, 'country' => 'Tanzania']);
        $a = User::factory()->create(['role' => 'manager', 'status' => 'active']);
        $b = User::factory()->create(['role' => 'manager', 'status' => 'active']);

        $this->actingAs($superuser)->patch("/superuser/properties/{$property->id}/assign-manager", ['manager_user_id' => $a->id])->assertSessionHasNoErrors();
        $this->actingAs($superuser)->patch("/superuser/properties/{$property->id}/assign-manager", ['manager_user_id' => $b->id])->assertSessionHasNoErrors();

        // Adding the second did not remove the first.
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $property->managers()->pluck('id')->all());
        $this->assertSame($property->id, $a->fresh()->property_id);
        $this->assertSame($property->id, $b->fresh()->property_id);
        $this->assertSame($a->id, $property->fresh()->manager_user_id);

        // Removing the first leaves the second, who becomes the primary contact.
        $this->actingAs($superuser)->patch("/superuser/properties/{$property->id}/unassign-manager", ['manager_user_id' => $a->id])->assertSessionHasNoErrors();

        $this->assertSame([$b->id], $property->managers()->pluck('id')->all());
        $this->assertNull($a->fresh()->property_id);
        $this->assertSame($b->id, $property->fresh()->manager_user_id);
    }

    public function test_a_manager_who_is_not_on_the_property_cannot_be_removed_from_it(): void
    {
        $superuser = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);
        $property = Property::create(['name' => 'Tower A', 'code' => 'TWA', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0, 'country' => 'Tanzania']);
        $other = User::factory()->create(['role' => 'manager', 'status' => 'active', 'property_id' => null]);

        $this->actingAs($superuser)->patch("/superuser/properties/{$property->id}/unassign-manager", ['manager_user_id' => $other->id])->assertNotFound();
    }
}
