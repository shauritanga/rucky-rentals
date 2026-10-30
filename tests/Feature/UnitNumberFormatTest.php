<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Support\FloorConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitNumberFormatTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->property = Property::create([
            'name' => 'Format Tower', 'code' => 'FMT', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0,
            'country' => 'Tanzania',
            'floor_config' => ['basements' => 1, 'has_parking_floor' => true, 'has_ground_floor' => true, 'has_mezzanine' => true, 'upper_floors' => 5],
        ]);
        $this->manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'must_change_password' => false, 'property_id' => $this->property->id]);
    }

    private function payload(string $number, string $floor): array
    {
        return [
            'unit_number' => $number, 'floor' => $floor, 'type' => 'Office Suite', 'size_sqm' => 10,
            'rate_per_sqm' => 100, 'service_charge_per_sqm' => 0, 'currency' => 'TZS', 'electricity_type' => 'direct',
        ];
    }

    public function test_prefixes_follow_the_floor(): void
    {
        $this->assertSame('F3.', FloorConfig::unitPrefix('3'));
        $this->assertSame('F12.', FloorConfig::unitPrefix('12'));
        $this->assertSame('M.', FloorConfig::unitPrefix('M'));
        $this->assertSame('P.', FloorConfig::unitPrefix('P'));
        $this->assertSame('G.', FloorConfig::unitPrefix('G'));
        $this->assertSame('B2.', FloorConfig::unitPrefix('B2'));
    }

    public function test_each_floor_type_accepts_its_own_prefix(): void
    {
        foreach ([['F3.01', '3'], ['M.02', 'M'], ['P.03', 'P'], ['G.04', 'G'], ['B1.05', 'B1']] as [$number, $floor]) {
            $this->actingAs($this->manager)->post('/units', $this->payload($number, $floor))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('units', ['property_id' => $this->property->id, 'unit_number' => $number, 'floor' => $floor]);
        }
    }

    public function test_prefix_must_match_the_selected_floor(): void
    {
        $this->actingAs($this->manager)->post('/units', $this->payload('F3.01', '4'))->assertSessionHasErrors('unit_number');
        $this->actingAs($this->manager)->post('/units', $this->payload('M.01', '3'))->assertSessionHasErrors('unit_number');
    }

    public function test_number_part_must_be_two_digits(): void
    {
        $this->actingAs($this->manager)->post('/units', $this->payload('F3.1', '3'))->assertSessionHasErrors('unit_number');
        $this->actingAs($this->manager)->post('/units', $this->payload('F3.001', '3'))->assertSessionHasErrors('unit_number');
        $this->actingAs($this->manager)->post('/units', $this->payload('F3.AB', '3'))->assertSessionHasErrors('unit_number');
    }

    public function test_duplicate_in_the_same_property_is_still_rejected(): void
    {
        $this->actingAs($this->manager)->post('/units', $this->payload('F3.01', '3'))->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post('/units', $this->payload('F3.01', '3'))->assertSessionHasErrors('unit_number');
        $this->assertSame(1, Unit::where('unit_number', 'F3.01')->count());
    }

    public function test_changing_an_existing_unit_to_another_floor_requires_the_new_prefix(): void
    {
        $this->actingAs($this->manager)->post('/units', $this->payload('F3.01', '3'));
        $unit = Unit::where('unit_number', 'F3.01')->firstOrFail();

        $this->actingAs($this->manager)->patch("/units/{$unit->id}", $this->payload('F3.01', '4') + ['status' => 'vacant'])->assertSessionHasErrors('unit_number');
        $this->actingAs($this->manager)->patch("/units/{$unit->id}", $this->payload('F4.01', '4') + ['status' => 'vacant'])->assertSessionHasNoErrors();
        $this->assertSame('F4.01', $unit->fresh()->unit_number);
    }
}
