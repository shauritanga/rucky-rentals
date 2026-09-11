<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperuserPropertyEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_superuser_can_add_a_parking_floor_without_renaming_units(): void
    {
        $superuser = User::factory()->create([
            'role' => 'superuser',
            'must_change_password' => false,
            'status' => 'active',
        ]);

        $property = Property::create([
            'name' => 'Rucky Heights',
            'code' => 'BLD01',
            'address' => '1 Main Street',
            'city' => 'Dar es Salaam',
            'country' => 'Tanzania',
            'status' => 'active',
            'bank_name' => 'Test Bank',
            'bank_account' => '123456',
            'bank_account_name' => 'Rucky Heights',
            'swift_code' => 'TESTTZTZ',
            'floor_config' => ['basements' => 0, 'has_ground_floor' => true, 'has_mezzanine' => false, 'upper_floors' => 2],
        ]);
        $unit = Unit::create([
            'property_id' => $property->id,
            'unit_number' => 'A-101',
            'floor' => '1',
            'type' => 'Office',
            'size_sqft' => 1076,
            'size_sqm' => 100,
            'rent' => 1000,
            'currency' => 'TZS',
            'status' => 'vacant',
            'approval_status' => 'approved',
        ]);

        $this->actingAs($superuser)
            ->patch("/superuser/properties/{$property->id}", [
                'name' => 'Rucky Heights',
                'address' => '1 Main Street',
                'city' => 'Dar es Salaam',
                'country' => 'Tanzania',
                'bank_name' => 'Test Bank',
                'bank_account' => '123456',
                'bank_account_name' => 'Rucky Heights',
                'swift_code' => 'TESTTZTZ',
                'status' => 'active',
                'basements' => 0,
                'has_parking_floor' => true,
                'has_ground_floor' => true,
                'has_mezzanine' => false,
                'upper_floors' => 2,
            ])
            ->assertRedirect();

        $property->refresh();
        $this->assertTrue($property->floor_config['has_parking_floor']);
        $this->assertSame(['P', 'G', '1', '2'], array_column($property->floorList(), 'id'));
        $this->assertSame('A-101', $unit->fresh()->unit_number);
    }
}
