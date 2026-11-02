<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantWithoutEmailTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $property = Property::create(['name' => 'Mail Tower', 'code' => 'MAI', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0, 'country' => 'Tanzania']);

        return User::factory()->create(['role' => 'manager', 'status' => 'active', 'property_id' => $property->id, 'must_change_password' => false]);
    }

    private function company(string $name, array $extra = []): array
    {
        return array_merge([
            'tenant_type' => 'company', 'company_name' => $name, 'contact_person' => 'Pat', 'tin' => '123456789',
            'phone' => '+255712345678',
        ], $extra);
    }

    public function test_a_tenant_can_be_saved_without_an_email_and_two_of_them_coexist(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->post('/tenants', $this->company('Alpha Ltd'))->assertSessionHasNoErrors();
        $this->actingAs($manager)->post('/tenants', $this->company('Beta Ltd', ['email' => '']))->assertSessionHasNoErrors();

        $this->assertSame(2, Tenant::whereNull('email')->count());
    }

    public function test_a_real_email_must_still_be_valid_and_unique(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager)->post('/tenants', $this->company('Alpha Ltd', ['email' => 'a@example.com']))->assertSessionHasNoErrors();

        $this->actingAs($manager)->post('/tenants', $this->company('Beta Ltd', ['email' => 'a@example.com']))->assertSessionHasErrors('email');
        $this->actingAs($manager)->post('/tenants', $this->company('Gamma Ltd', ['email' => 'not-an-email']))->assertSessionHasErrors('email');
    }

    public function test_adding_the_email_later_fills_it_on_that_tenants_open_invoices(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager)->post('/tenants', $this->company('Alpha Ltd'))->assertSessionHasNoErrors();
        $tenant = Tenant::where('name', 'Alpha Ltd')->firstOrFail();

        $unit = \App\Models\Unit::create([
            'property_id' => $manager->property_id, 'unit_number' => 'F1.01', 'floor' => '1', 'type' => 'Office Suite', 'size_sqft' => 100, 'size_sqm' => 9.29,
            'rate_per_sqm' => 100, 'service_charge_per_sqm' => 0, 'currency' => 'TZS', 'rent' => 1000, 'status' => 'occupied', 'deposit' => 0,
            'service_charge' => 0, 'electricity_type' => 'direct', 'approval_status' => 'approved',
        ]);
        $lease = \App\Models\Lease::create([
            'property_id' => $manager->property_id, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'start_date' => '2026-04-01', 'end_date' => '2027-04-01',
            'monthly_rent' => 1000, 'deposit' => 0, 'status' => 'active', 'currency' => 'TZS', 'payment_cycle' => 3, 'duration_months' => 12,
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'T/01/2026', 'type' => 'proforma', 'status' => 'proforma', 'property_id' => $manager->property_id, 'lease_id' => $lease->id,
            'tenant_name' => 'Alpha Ltd', 'tenant_email' => null, 'unit_ref' => 'F1.01', 'issued_date' => '2026-04-01', 'due_date' => '2026-04-01',
            'currency' => 'TZS', 'exchange_rate' => 1, 'total_in_base' => 1000,
        ]);

        $this->actingAs($manager)->put("/tenants/{$tenant->id}", $this->company('Alpha Ltd', ['email' => 'alpha@example.com']))->assertSessionHasNoErrors();

        $this->assertSame('alpha@example.com', $tenant->fresh()->email);
        $this->assertSame('alpha@example.com', $invoice->fresh()->tenant_email);
    }
}
