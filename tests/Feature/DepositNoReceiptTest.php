<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DepositNoReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function deposit(): array
    {
        $property = Property::create(['name' => 'Dep Tower', 'code' => 'DEP', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0, 'country' => 'Tanzania']);
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'property_id' => $property->id, 'must_change_password' => false]);
        $unit = Unit::create(['property_id' => $property->id, 'unit_number' => 'D1', 'floor' => 1, 'type' => 'Office', 'size_sqft' => 100, 'rent' => 1000, 'currency' => 'TZS', 'status' => 'occupied', 'approval_status' => 'approved', 'deposit' => 0, 'service_charge' => 0, 'electricity_type' => 'direct']);
        $tenant = Tenant::create(['property_id' => $property->id, 'name' => 'Dep Tenant', 'email' => 'd@example.com', 'phone' => '+255700000000', 'initials' => 'DT', 'tenant_type' => 'individual']);
        $invoice = Invoice::create([
            'property_id' => $property->id, 'invoice_number' => 'DEP/1', 'tenant_name' => 'Dep Tenant', 'unit_ref' => 'D1', 'type' => 'proforma', 'kind' => 'deposit', 'status' => 'proforma',
            'due_date' => now()->toDateString(), 'issued_date' => now()->toDateString(), 'currency' => 'TZS', 'approval_status' => 'approved',
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Security Deposit', 'item_type' => 'security_deposit', 'quantity' => 1, 'unit_price' => 5000, 'total' => 5000]);

        return [$property, $manager, $unit, $tenant, $invoice];
    }

    public function test_paying_a_deposit_in_full_issues_no_receipt_even_if_one_is_requested(): void
    {
        [$property, $manager, $unit, $tenant, $invoice] = $this->deposit();

        $this->actingAs($manager)->post('/payments', [
            'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'invoice_id' => $invoice->id, 'amount' => 5000, 'method' => 'cash',
            'status' => 'paid', 'month' => 'Nov 2026', 'paid_date' => now()->toDateString(), 'issue_receipt' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(0, Receipt::count());
        $this->assertNull(Payment::where('invoice_id', $invoice->id)->value('receipt_id'));

        $this->actingAs($manager)->get('/invoices')->assertInertia(fn (Assert $p) => $p
            ->has('invoices', 1, fn (Assert $i) => $i->where('receipt_status', 'na')->etc()));
    }

    public function test_the_receipt_button_refuses_a_deposit(): void
    {
        [$property, $manager, $unit, $tenant, $invoice] = $this->deposit();
        Payment::create(['property_id' => $property->id, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'invoice_id' => $invoice->id, 'amount' => 5000, 'currency' => 'TZS', 'status' => 'paid', 'month' => 'x', 'paid_date' => now()->toDateString()]);
        $invoice->update(['status' => 'paid']);

        $this->actingAs($manager)->post("/invoices/{$invoice->id}/receipt")->assertSessionHas('error', 'No receipt is issued for a security deposit.');
        $this->assertSame(0, Receipt::count());
    }
}
