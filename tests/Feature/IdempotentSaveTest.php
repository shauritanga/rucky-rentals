<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotentSaveTest extends TestCase
{
    use RefreshDatabase;

    private function world(): array
    {
        $property = Property::create(['name' => 'Idem Tower', 'code' => 'IDM', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0, 'country' => 'Tanzania']);
        $superuser = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);
        $unit = Unit::create(['property_id' => $property->id, 'unit_number' => 'I1', 'floor' => 1, 'type' => 'Office', 'size_sqft' => 100, 'rent' => 1000, 'currency' => 'TZS', 'status' => 'vacant', 'approval_status' => 'approved', 'deposit' => 0, 'service_charge' => 0, 'electricity_type' => 'direct']);
        $tenant = Tenant::create(['property_id' => $property->id, 'name' => 'Idem Tenant', 'email' => 'i@example.com', 'phone' => '+255700000000', 'initials' => 'IT', 'tenant_type' => 'individual']);
        $invoice = Invoice::create(['property_id' => $property->id, 'invoice_number' => 'IDM/1', 'tenant_name' => 'Idem Tenant', 'unit_ref' => 'I1', 'type' => 'proforma', 'status' => 'proforma', 'due_date' => now()->toDateString(), 'issued_date' => now()->toDateString(), 'currency' => 'TZS', 'approval_status' => 'approved']);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Rent', 'item_type' => 'rent', 'quantity' => 1, 'unit_price' => 5000, 'total' => 5000]);

        return [$property, $superuser, $unit, $tenant, $invoice];
    }

    private function form($tenant, $unit, $invoice, array $over = []): array
    {
        return array_merge(['tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'invoice_id' => $invoice->id, 'amount' => 1000, 'method' => 'Cash', 'status' => 'paid', 'month' => 'Nov 2026', 'paid_date' => now()->toDateString()], $over);
    }

    public function test_repeating_a_save_with_the_same_key_does_not_record_a_second_payment(): void
    {
        [$property, $superuser, $unit, $tenant, $invoice] = $this->world();
        $acting = fn () => $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id]);

        $first = $acting()->withHeaders(['X-Idempotency-Key' => 'key-1'])->post('/payments', $this->form($tenant, $unit, $invoice));
        $first->assertSessionHas('success', 'Payment recorded.');

        // The connection dropped before the answer arrived: the browser presses the button again with the same key.
        $second = $acting()->withHeaders(['X-Idempotency-Key' => 'key-1'])->post('/payments', $this->form($tenant, $unit, $invoice));
        $second->assertRedirect();
        $second->assertSessionHas('success', 'Payment recorded.');

        $this->assertSame(1, Payment::count());
    }

    public function test_a_new_key_is_a_new_save_so_two_real_payments_are_both_recorded(): void
    {
        [$property, $superuser, $unit, $tenant, $invoice] = $this->world();
        $acting = fn () => $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id]);

        $acting()->withHeaders(['X-Idempotency-Key' => 'key-a'])->post('/payments', $this->form($tenant, $unit, $invoice));
        $acting()->withHeaders(['X-Idempotency-Key' => 'key-b'])->post('/payments', $this->form($tenant, $unit, $invoice));

        $this->assertSame(2, Payment::count());
    }

    public function test_a_failed_save_is_not_remembered_so_the_corrected_retry_runs(): void
    {
        [$property, $superuser, $unit, $tenant, $invoice] = $this->world();
        $acting = fn () => $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id]);

        $acting()->withHeaders(['X-Idempotency-Key' => 'key-9'])->post('/payments', $this->form($tenant, $unit, $invoice, ['amount' => '']))->assertSessionHasErrors('amount');
        $this->assertSame(0, Payment::count());

        $acting()->withHeaders(['X-Idempotency-Key' => 'key-9'])->post('/payments', $this->form($tenant, $unit, $invoice))->assertSessionHasNoErrors();
        $this->assertSame(1, Payment::count());
    }

    public function test_a_save_without_a_key_works_as_before(): void
    {
        [$property, $superuser, $unit, $tenant, $invoice] = $this->world();
        $acting = fn () => $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id]);

        $acting()->post('/payments', $this->form($tenant, $unit, $invoice));
        $acting()->post('/payments', $this->form($tenant, $unit, $invoice));

        $this->assertSame(2, Payment::count());
    }
}
