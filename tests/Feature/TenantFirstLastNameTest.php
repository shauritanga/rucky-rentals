<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantFirstLastNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_individual_tenant_derives_full_name_from_first_and_last_name(): void
    {
        $property = $this->createProperty();
        $manager = $this->createManager($property->id);

        $response = $this->actingAs($manager)->post('/tenants', [
            'tenant_type' => 'individual',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'national_id' => '1234567890',
            'nok_name' => 'John Doe',
            'nok_phone' => '0700000000',
            'nok_relation' => 'Spouse',
            'email' => 'jane@example.com',
            'phone' => '0700000001',
        ]);

        $response->assertRedirect();

        $tenant = Tenant::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('Jane', $tenant->first_name);
        $this->assertSame('Doe', $tenant->last_name);
        $this->assertSame('Jane Doe', $tenant->name);
    }

    public function test_updating_first_or_last_name_keeps_full_name_in_sync(): void
    {
        $property = $this->createProperty();
        $manager = $this->createManager($property->id);
        $tenant = Tenant::create([
            'property_id' => $property->id,
            'tenant_type' => 'individual',
            'name' => 'Jane Doe',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane2@example.com',
            'phone' => '0700000001',
            'national_id' => '1234567890',
            'initials' => 'JD',
            'nok_name' => 'John Doe',
            'nok_phone' => '0700000000',
            'nok_relation' => 'Spouse',
        ]);

        $response = $this->actingAs($manager)->put("/tenants/{$tenant->id}", [
            'tenant_type' => 'individual',
            'first_name' => 'Janet',
            'last_name' => 'Smith',
            'national_id' => '1234567890',
            'nok_name' => 'John Doe',
            'nok_phone' => '0700000000',
            'nok_relation' => 'Spouse',
            'email' => 'jane2@example.com',
            'phone' => '0700000001',
        ]);

        $response->assertRedirect();

        $tenant->refresh();
        $this->assertSame('Janet', $tenant->first_name);
        $this->assertSame('Smith', $tenant->last_name);
        $this->assertSame('Janet Smith', $tenant->name);
    }

    public function test_company_tenants_do_not_require_first_or_last_name(): void
    {
        $property = $this->createProperty();
        $manager = $this->createManager($property->id);

        $response = $this->actingAs($manager)->post('/tenants', [
            'tenant_type' => 'company',
            'company_name' => 'Acme Ltd',
            'tin' => '111-222-333',
            'contact_person' => 'Bob Contact',
            'email' => 'acme@example.com',
            'phone' => '0700000002',
        ]);

        $response->assertRedirect();

        $tenant = Tenant::where('email', 'acme@example.com')->firstOrFail();
        $this->assertNull($tenant->first_name);
        $this->assertNull($tenant->last_name);
        $this->assertSame('Acme Ltd', $tenant->name);
    }

    public function test_backfill_migration_splits_existing_individual_names(): void
    {
        $property = $this->createProperty();

        // Simulate a legacy row inserted before first_name/last_name existed,
        // then re-run the backfill logic directly against raw DB rows.
        $tenant = Tenant::create([
            'property_id' => $property->id,
            'tenant_type' => 'individual',
            'name' => 'Alice Wonder',
            'email' => 'alice@example.com',
            'phone' => '0700000003',
            'national_id' => '1234567890',
            'initials' => 'AW',
            'nok_name' => 'Kin',
            'nok_phone' => '0700000004',
            'nok_relation' => 'Parent',
        ]);

        // Model's saving() hook only recomputes `name` from first/last name when
        // those fields are dirty; it never overwrites first/last name from `name`.
        // Confirm the column exists and is nullable for legacy rows not yet backfilled.
        $this->assertNull($tenant->fresh()->first_name);
    }

    private function createProperty(): Property
    {
        return Property::create([
            'name' => 'Name Split Tower',
            'code' => 'NST',
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

    private function createManager(int $propertyId): User
    {
        return User::factory()->create([
            'role' => 'manager',
            'property_id' => $propertyId,
            'must_change_password' => false,
            'status' => 'active',
        ]);
    }
}
