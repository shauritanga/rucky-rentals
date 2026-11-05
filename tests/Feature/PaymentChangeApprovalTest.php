<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentChangeRequest;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentChangeApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function world(string $kind = 'rent'): array
    {
        $property = Property::create(['name' => 'Pay Tower', 'code' => 'PAY', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0, 'country' => 'Tanzania']);
        $superuser = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'property_id' => $property->id, 'must_change_password' => false]);
        $unit = Unit::create(['property_id' => $property->id, 'unit_number' => 'P1', 'floor' => 1, 'type' => 'Office', 'size_sqft' => 100, 'rent' => 1000, 'currency' => 'TZS', 'status' => 'vacant', 'approval_status' => 'approved', 'deposit' => 0, 'service_charge' => 0, 'electricity_type' => 'direct']);
        $tenant = Tenant::create(['property_id' => $property->id, 'name' => 'Pay Tenant', 'email' => 'p@example.com', 'phone' => '+255700000000', 'initials' => 'PT', 'tenant_type' => 'individual']);
        $invoice = Invoice::create([
            'property_id' => $property->id, 'invoice_number' => 'PAY/1', 'tenant_name' => 'Pay Tenant', 'unit_ref' => 'P1', 'type' => 'proforma', 'kind' => $kind, 'status' => 'proforma',
            'due_date' => now()->toDateString(), 'issued_date' => now()->toDateString(), 'currency' => 'TZS', 'approval_status' => 'approved',
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => $kind === 'deposit' ? 'Security Deposit' : 'Rent', 'item_type' => $kind === 'deposit' ? 'security_deposit' : 'rent', 'quantity' => 1, 'unit_price' => 5000, 'total' => 5000]);

        return [$property, $superuser, $manager, $unit, $tenant, $invoice];
    }

    private function form(Tenant $tenant, Unit $unit, Invoice $invoice, array $over = []): array
    {
        return array_merge([
            'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'invoice_id' => $invoice->id, 'amount' => 5000, 'method' => 'Bank Transfer',
            'status' => 'paid', 'month' => 'Nov 2026', 'paid_date' => now()->toDateString(),
        ], $over);
    }

    private function recorded(Property $property, Unit $unit, Tenant $tenant, Invoice $invoice, float $amount = 5000): Payment
    {
        return Payment::create(['property_id' => $property->id, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'invoice_id' => $invoice->id, 'amount' => $amount, 'amount_in_base' => $amount, 'currency' => 'TZS', 'status' => 'paid', 'method' => 'Cash', 'month' => 'x', 'paid_date' => now()->toDateString()]);
    }

    public function test_a_managers_payment_is_only_a_request_until_a_superuser_approves_it(): void
    {
        Notification::fake();
        [$property, $superuser, $manager, $unit, $tenant, $invoice] = $this->world();

        $this->actingAs($manager)->post('/payments', $this->form($tenant, $unit, $invoice))->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(0, Payment::count());
        $this->assertSame('proforma', $invoice->fresh()->status);
        $request = PaymentChangeRequest::firstOrFail();
        $this->assertSame('create', $request->type);
        Notification::assertSentTo($superuser, \App\Notifications\PaymentChangeRequestNotification::class);

        $this->actingAs($superuser)->post("/superuser/payment-changes/{$request->id}/approve")->assertSessionHasNoErrors();

        $this->assertSame(1, Payment::count());
        $fresh = $invoice->fresh();
        $this->assertSame('invoice', $fresh->type);   // a fully paid proforma becomes a tax invoice
        $this->assertSame('paid', $fresh->status);
        $this->assertSame('approved', $request->fresh()->status);
        Notification::assertSentTo($manager, \App\Notifications\PaymentChangeDecisionNotification::class);
    }

    public function test_a_rejected_payment_records_nothing_and_needs_a_reason(): void
    {
        Notification::fake();
        [$property, $superuser, $manager, $unit, $tenant, $invoice] = $this->world();
        $this->actingAs($manager)->post('/payments', $this->form($tenant, $unit, $invoice));
        $request = PaymentChangeRequest::firstOrFail();

        $this->actingAs($superuser)->post("/superuser/payment-changes/{$request->id}/reject", [])->assertSessionHasErrors('message');
        $this->actingAs($superuser)->post("/superuser/payment-changes/{$request->id}/reject", ['message' => 'No bank statement seen'])->assertSessionHasNoErrors();

        $this->assertSame(0, Payment::count());
        $this->assertSame('proforma', $invoice->fresh()->status);
        $this->assertSame('rejected', $request->fresh()->status);

        $this->actingAs($manager)->get('/payments')->assertInertia(fn ($p) => $p
            ->has('paymentRequests', 1, fn ($r) => $r->where('status', 'rejected')->where('decision_note', 'No bank statement seen')->etc()));
    }

    public function test_a_managers_edit_waits_then_applies_and_the_invoice_status_follows(): void
    {
        Notification::fake();
        [$property, $superuser, $manager, $unit, $tenant, $invoice] = $this->world();
        $payment = $this->recorded($property, $unit, $tenant, $invoice, 5000);
        $invoice->update(['type' => 'invoice', 'status' => 'paid']);

        $this->actingAs($manager)->patch("/payments/{$payment->id}", ['amount' => 3000, 'method' => 'Cheque', 'paid_date' => now()->toDateString(), 'reference' => 'CHQ-9', 'notes' => 'Corrected'])
            ->assertSessionHasNoErrors();

        $this->assertEquals(5000, $payment->fresh()->amount);       // unchanged
        $request = PaymentChangeRequest::firstOrFail();
        $this->assertSame('edit', $request->type);

        // A second request while one is open is refused.
        $this->actingAs($manager)->patch("/payments/{$payment->id}", ['amount' => 2000])->assertSessionHasErrors();

        $this->actingAs($superuser)->post("/superuser/payment-changes/{$request->id}/approve")->assertSessionHasNoErrors();

        $fresh = $payment->fresh();
        $this->assertEquals(3000, $fresh->amount);
        $this->assertSame('Cheque', $fresh->method);
        $this->assertSame('CHQ-9', $fresh->reference);
        $this->assertSame('partially_paid', $invoice->fresh()->status);
    }

    public function test_a_managers_deletion_waits_then_removes_the_payment_and_reopens_the_invoice(): void
    {
        Notification::fake();
        [$property, $superuser, $manager, $unit, $tenant, $invoice] = $this->world();
        $payment = $this->recorded($property, $unit, $tenant, $invoice);
        $invoice->update(['type' => 'invoice', 'status' => 'paid']);

        $this->actingAs($manager)->delete("/payments/{$payment->id}")->assertSessionHasNoErrors();
        $this->assertNotNull(Payment::find($payment->id));
        $request = PaymentChangeRequest::firstOrFail();

        $this->actingAs($manager)->get('/payments')->assertInertia(fn ($p) => $p
            ->has('payments', 1, fn ($row) => $row->where('pending_change.type', 'delete')->etc()));

        $this->actingAs($superuser)->post("/superuser/payment-changes/{$request->id}/approve")->assertSessionHasNoErrors();

        $this->assertNull(Payment::find($payment->id));
        $this->assertContains($invoice->fresh()->status, ['unpaid', 'overdue']);
        $this->assertSame('approved', $request->fresh()->status);   // the history stays after the payment is gone
    }

    public function test_a_superusers_own_payment_actions_apply_immediately(): void
    {
        [$property, $superuser, $manager, $unit, $tenant, $invoice] = $this->world();
        $acting = fn () => $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id]);

        $acting()->post('/payments', $this->form($tenant, $unit, $invoice, ['amount' => 2000]))->assertSessionHasNoErrors();
        $payment = Payment::firstOrFail();
        $this->assertSame(0, PaymentChangeRequest::count());

        $acting()->patch("/payments/{$payment->id}", ['amount' => 2500])->assertSessionHasNoErrors();
        $this->assertEquals(2500, $payment->fresh()->amount);

        $acting()->delete("/payments/{$payment->id}")->assertSessionHasNoErrors();
        $this->assertNull(Payment::find($payment->id));
        $this->assertSame(0, PaymentChangeRequest::count());
    }

    public function test_a_payment_with_a_receipt_cannot_be_deleted_or_have_its_amount_changed(): void
    {
        [$property, $superuser, $manager, $unit, $tenant, $invoice] = $this->world();
        $payment = $this->recorded($property, $unit, $tenant, $invoice);
        $receipt = Receipt::create(['receipt_number' => 'R-1', 'payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'tenant_id' => $tenant->id, 'amount' => 5000, 'issued_at' => now()]);
        $payment->update(['receipt_id' => $receipt->id]);

        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])
            ->delete("/payments/{$payment->id}")->assertSessionHasErrors('payment');
        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])
            ->patch("/payments/{$payment->id}", ['amount' => 100])->assertSessionHasErrors('amount');
        $this->assertNotNull(Payment::find($payment->id));
        $this->assertEquals(5000, $payment->fresh()->amount);
    }

    public function test_marking_an_invoice_paid_by_hand_is_refused_and_records_nothing(): void
    {
        [$property, $superuser, $manager, $unit, $tenant, $invoice] = $this->world();
        $invoice->update(['type' => 'invoice', 'status' => 'unpaid']);

        $this->actingAs($manager)->patch("/invoices/{$invoice->id}", ['status' => 'paid'])->assertStatus(422);
        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])
            ->patch("/invoices/{$invoice->id}", ['status' => 'paid'])->assertStatus(422);

        $this->assertSame(0, Payment::count());
        $this->assertSame('unpaid', $invoice->fresh()->status);
    }
}
