<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Property;
use App\Models\ScheduledMaintenance;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UnitPropertyScopedUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_unit_number_can_exist_in_different_properties(): void
    {
        $propertyA = $this->createProperty('Alpha Tower', 'ALP');
        $propertyB = $this->createProperty('Beta Tower', 'BET');

        $managerA = $this->createManager($propertyA);
        $managerB = $this->createManager($propertyB);

        $responseA = $this->actingAs($managerA)->post('/units', $this->unitPayload('A-101'));
        $responseB = $this->actingAs($managerB)->post('/units', $this->unitPayload('A-101'));

        $responseA->assertRedirect();
        $responseB->assertRedirect();

        $this->assertDatabaseHas('units', [
            'property_id' => $propertyA->id,
            'unit_number' => 'A-101',
            'status' => 'vacant',
        ]);

        $this->assertDatabaseHas('units', [
            'property_id' => $propertyB->id,
            'unit_number' => 'A-101',
            'status' => 'vacant',
        ]);
    }

    public function test_same_unit_number_still_must_be_unique_within_one_property(): void
    {
        $property = $this->createProperty('Gamma Tower', 'GAM');
        $manager = $this->createManager($property);

        Unit::create($this->storedUnitAttributes($property->id, 'A-101'));

        $response = $this->actingAs($manager)->from('/units')->post('/units', $this->unitPayload('A-101'));

        $response->assertRedirect('/units');
        $response->assertSessionHasErrors('unit_number');
        $this->assertSame(1, Unit::where('property_id', $property->id)->where('unit_number', 'A-101')->count());
    }

    public function test_document_upload_resolves_duplicate_unit_reference_within_active_property(): void
    {
        Storage::fake('public');

        $propertyA = $this->createProperty('Delta Tower', 'DEL');
        $propertyB = $this->createProperty('Echo Tower', 'ECH');
        $managerA = $this->createManager($propertyA);

        $unitA = Unit::create($this->storedUnitAttributes($propertyA->id, 'A-101'));
        Unit::create($this->storedUnitAttributes($propertyB->id, 'A-101'));

        $response = $this->actingAs($managerA)->post('/documents', [
            'file' => UploadedFile::fake()->create('lease.pdf', 100, 'application/pdf'),
            'document_type' => 'invoice',
            'unit_ref' => 'A-101',
            'description' => 'Scoped upload',
        ]);

        $response->assertRedirect();

        $document = Document::query()->firstOrFail();
        $this->assertSame($unitA->id, $document->unit_id);
        $this->assertSame('A-101', $document->unit_ref);
    }

    public function test_scheduled_maintenance_resolves_duplicate_unit_reference_within_active_property(): void
    {
        $propertyA = $this->createProperty('Foxtrot Tower', 'FOX');
        $propertyB = $this->createProperty('Hotel Tower', 'HOT');
        $managerA = $this->createManager($propertyA);

        $unitA = Unit::create($this->storedUnitAttributes($propertyA->id, 'A-101'));
        Unit::create($this->storedUnitAttributes($propertyB->id, 'A-101'));

        $response = $this->actingAs($managerA)->post('/scheduled-maintenance', [
            'title' => 'Quarterly inspection',
            'unit_ref' => 'A-101',
            'category' => 'General',
            'frequency' => 'quarterly',
            'next_due' => '2026-05-01',
            'assignee' => 'Facilities Team',
        ]);

        $response->assertRedirect();

        $task = ScheduledMaintenance::query()->firstOrFail();
        $this->assertSame($propertyA->id, $task->property_id);
        $this->assertSame($unitA->id, $task->unit_id);
        $this->assertSame('A-101', $task->unit_ref);
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
            'must_change_password' => false,
            'property_id' => $property->id,
        ]);
    }

    private function unitPayload(string $unitNumber): array
    {
        return [
            'unit_number' => $unitNumber,
            'floor' => '1',
            'type' => 'Office Suite',
            'size_sqm' => 10,
            'rate_per_sqm' => 100,
            'service_charge_per_sqm' => 0,
            'currency' => 'TZS',
            'electricity_type' => 'direct',
            'notes' => null,
        ];
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
}
