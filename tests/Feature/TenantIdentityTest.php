<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIdentityTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->property = Property::create([
            'name' => 'Id Plaza', 'code' => 'BLD91', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0,
            'country' => 'Tanzania', 'floor_config' => ['basements' => 0, 'upper_floors' => 3],
        ]);
    }

    private function submit(array $overrides = [])
    {
        $user = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);

        return $this->actingAs($user)->withSession(['superuser_viewing_property_id' => $this->property->id])->post('/tenants', array_merge([
            'tenant_type' => 'individual', 'first_name' => 'Asha', 'middle_name' => 'Juma', 'last_name' => 'Mwinyi',
            'tin' => '123-456-789', 'id_type' => 'national', 'national_id' => '12345678-12345-12345-12',
            'nok_name' => 'Ali', 'nok_phone' => '0700000000', 'nok_relation' => 'Brother',
            'email' => 'asha@example.com', 'phone' => '0711000000', 'address' => 'P.O. Box 1',
        ], $overrides));
    }

    public function test_individual_with_valid_details_is_saved_normalised_with_middle_name(): void
    {
        $this->submit()->assertRedirect()->assertSessionHasNoErrors();

        $t = Tenant::where('email', 'asha@example.com')->firstOrFail();
        $this->assertSame('Asha Juma Mwinyi', $t->name);
        $this->assertSame('Juma', $t->middle_name);
        $this->assertSame('123456789', $t->tin);
        $this->assertSame('12345678123451234512', $t->national_id);
        $this->assertSame('national', $t->id_type);
    }

    public function test_middle_name_is_optional(): void
    {
        $this->submit(['middle_name' => null])->assertSessionHasNoErrors();
        $this->assertSame('Asha Mwinyi', Tenant::where('email', 'asha@example.com')->value('name'));
    }

    public function test_tin_is_required_for_individuals(): void
    {
        $this->submit(['tin' => ''])->assertSessionHasErrors('tin');
    }

    public function test_tin_must_be_nine_digits(): void
    {
        $this->submit(['tin' => '12-34'])->assertSessionHasErrors('tin');
    }

    public function test_id_number_must_match_the_selected_type(): void
    {
        $this->submit(['id_type' => 'national', 'national_id' => '1234'])->assertSessionHasErrors('national_id');
        $this->submit(['id_type' => 'driving_license', 'national_id' => '12345'])->assertSessionHasErrors('national_id');
        $this->submit(['id_type' => 'voter', 'national_id' => '1234-5678-9012', 'email' => 'v@example.com'])->assertSessionHasNoErrors();
        $this->submit(['id_type' => 'driving_license', 'national_id' => '1234567890', 'email' => 'd@example.com'])->assertSessionHasNoErrors();
    }

    public function test_foreign_passports_are_accepted_and_uppercased(): void
    {
        $this->submit(['id_type' => 'passport', 'national_id' => 'a1234567', 'email' => 'p@example.com'])->assertSessionHasNoErrors();
        $this->assertSame('A1234567', Tenant::where('email', 'p@example.com')->value('national_id'));

        $this->submit(['id_type' => 'passport', 'national_id' => 'AB-12', 'email' => 'q@example.com'])->assertSessionHasErrors('national_id');
    }

    public function test_id_type_is_required_for_individuals(): void
    {
        $this->submit(['id_type' => null])->assertSessionHasErrors('id_type');
    }

    public function test_company_needs_tin_but_no_id_and_keeps_incorporation_number(): void
    {
        $this->submit([
            'tenant_type' => 'company', 'company_name' => 'Twiga Ltd', 'contact_person' => 'Neema', 'registration_number' => 'IN-2020-001',
            'first_name' => null, 'last_name' => null, 'id_type' => null, 'national_id' => null,
            'nok_name' => null, 'nok_phone' => null, 'nok_relation' => null, 'email' => 'twiga@example.com',
        ])->assertSessionHasNoErrors();

        $t = Tenant::where('email', 'twiga@example.com')->firstOrFail();
        $this->assertSame('IN-2020-001', $t->registration_number);
        $this->assertSame('123456789', $t->tin);
        $this->assertNull($t->id_type);

        $this->submit([
            'tenant_type' => 'company', 'company_name' => 'No Tin Ltd', 'contact_person' => 'X', 'tin' => '',
            'first_name' => null, 'last_name' => null, 'id_type' => null, 'national_id' => null,
            'nok_name' => null, 'nok_phone' => null, 'nok_relation' => null, 'email' => 'notin@example.com',
        ])->assertSessionHasErrors('tin');
    }

    public function test_legacy_tenant_can_still_be_edited_without_changing_their_old_id(): void
    {
        $user = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);
        $t = Tenant::create([
            'property_id' => $this->property->id, 'name' => 'Old Timer', 'first_name' => 'Old', 'last_name' => 'Timer', 'tenant_type' => 'individual',
            'email' => 'old@example.com', 'phone' => '1', 'national_id' => 'KE-3456/78', 'id_type' => 'other', 'tin' => '999',
            'nok_name' => 'n', 'nok_phone' => '1', 'nok_relation' => 'r', 'initials' => 'OT', 'color' => 'x', 'text_color' => 'y',
        ]);

        $this->actingAs($user)->withSession(['superuser_viewing_property_id' => $this->property->id])->put("/tenants/{$t->id}", [
            'tenant_type' => 'individual', 'first_name' => 'Old', 'last_name' => 'Timer', 'id_type' => 'other', 'national_id' => 'KE-3456/78',
            'tin' => '999', 'nok_name' => 'n', 'nok_phone' => '1', 'nok_relation' => 'r', 'email' => 'old@example.com', 'phone' => '2',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2', $t->fresh()->phone);
    }
}
