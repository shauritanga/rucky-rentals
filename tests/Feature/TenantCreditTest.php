<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentChangeRequest;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\TenantCredit;
use App\Models\Unit;
use App\Models\User;
use App\Services\TenantCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantCreditTest extends TestCase
{
    use RefreshDatabase;

    private function world(): array
    {
        $property = Property::create(['name' => 'Cred Tower', 'code' => 'CRD', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0, 'country' => 'Tanzania']);
        $superuser = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'property_id' => $property->id, 'must_change_password' => false]);
        $unit = Unit::create(['property_id' => $property->id, 'unit_number' => 'C1', 'floor' => 1, 'type' => 'Office', 'size_sqft' => 100, 'rent' => 1000, 'currency' => 'TZS', 'status' => 'occupied', 'approval_status' => 'approved', 'deposit' => 0, 'service_charge' => 0, 'electricity_type' => 'direct']);
        $tenant = Tenant::create(['property_id' => $property->id, 'name' => 'Cred Tenant', 'email' => 'c@example.com', 'phone' => '+255700000000', 'initials' => 'CT', 'tenant_type' => 'individual']);

        return [$property, $superuser, $manager, $unit, $tenant];
    }

    private function proforma(Property $property, string $number, float $amount = 1000): Invoice
    {
        $invoice = Invoice::create([
            'property_id' => $property->id, 'invoice_number' => $number, 'tenant_name' => 'Cred Tenant', 'unit_ref' => 'C1', 'type' => 'proforma', 'status' => 'proforma',
            'due_date' => now()->toDateString(), 'issued_date' => now()->toDateString(), 'currency' => 'TZS', 'approval_status' => 'approved',
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Rent', 'item_type' => 'rent', 'quantity' => 1, 'unit_price' => $amount, 'total' => $amount]);

        return $invoice;
    }

    private function pay(User $by, Property $property, Tenant $tenant, Unit $unit, Invoice $invoice, float $amount)
    {
        return $this->actingAs($by)->withSession(['superuser_viewing_property_id' => $property->id])->post('/payments', [
            'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'invoice_id' => $invoice->id, 'amount' => $amount, 'method' => 'Cash',
            'status' => 'paid', 'month' => 'Nov 2026', 'paid_date' => now()->toDateString(),
        ]);
    }

    private function balance(Tenant $tenant): float
    {
        return $tenant->fresh()->creditBalance();
    }

    public function test_an_overpayment_becomes_credit_and_follows_edits_and_deletes_of_the_payment(): void
    {
        [$property, $superuser, $manager, $unit, $tenant] = $this->world();
        $invoice = $this->proforma($property, 'CRD/1');

        $this->pay($superuser, $property, $tenant, $unit, $invoice, 1300)->assertSessionHasNoErrors();
        $this->assertEquals(300, $this->balance($tenant));
        $this->assertSame(1, TenantCredit::count());

        $payment = Payment::firstOrFail();
        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])->patch("/payments/{$payment->id}", ['amount' => 1100]);
        $this->assertEquals(100, $this->balance($tenant));
        $this->assertSame(1, TenantCredit::count());

        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])->delete("/payments/{$payment->id}");
        $this->assertEquals(0, $this->balance($tenant));
    }

    public function test_applying_credit_pays_the_next_proforma_without_new_cash_and_uses_up_the_credit(): void
    {
        [$property, $superuser, $manager, $unit, $tenant] = $this->world();
        $this->pay($superuser, $property, $tenant, $unit, $this->proforma($property, 'CRD/1'), 1300);
        $next = $this->proforma($property, 'CRD/2');

        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])
            ->post('/payments/credits/apply', ['invoice_id' => $next->id])->assertSessionHasNoErrors();

        $credit = Payment::where('invoice_id', $next->id)->firstOrFail();
        $this->assertSame('credit', $credit->source);
        $this->assertEquals(300, $credit->amount);
        $this->assertEquals(0, $this->balance($tenant));
        $this->assertEquals(300, $next->fresh()->creditApplied());

        // Applying again finds no credit left.
        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])
            ->post('/payments/credits/apply', ['invoice_id' => $next->id])->assertSessionHasErrors('amount');
    }

    public function test_credit_applied_is_not_counted_as_new_cash_collected_on_the_dashboard(): void
    {
        [$property, $superuser, $manager, $unit, $tenant] = $this->world();
        $this->pay($superuser, $property, $tenant, $unit, $this->proforma($property, 'CRD/1'), 1300);
        $next = $this->proforma($property, 'CRD/2');
        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])->post('/payments/credits/apply', ['invoice_id' => $next->id]);

        $this->actingAs($manager)->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p->where('stats.monthlyRevenue', 1300));
    }

    public function test_the_amount_is_clamped_and_another_tenants_invoice_is_refused(): void
    {
        [$property, $superuser, $manager, $unit, $tenant] = $this->world();
        $this->pay($superuser, $property, $tenant, $unit, $this->proforma($property, 'CRD/1'), 1300);   // credit 300
        $small = $this->proforma($property, 'CRD/2', 100);

        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])
            ->post('/payments/credits/apply', ['invoice_id' => $small->id, 'amount' => 999])->assertSessionHasNoErrors();
        $this->assertEquals(100, Payment::where('invoice_id', $small->id)->value('amount'));   // only what was owing
        $this->assertEquals(200, $this->balance($tenant));

        $other = Tenant::create(['property_id' => $property->id, 'name' => 'Other Tenant', 'email' => 'o@example.com', 'phone' => '+255700000001', 'initials' => 'OT', 'tenant_type' => 'individual']);
        $foreign = $this->proforma($property, 'CRD/3');
        $foreign->update(['tenant_name' => 'Other Tenant']);
        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])
            ->post('/payments/credits/apply', ['invoice_id' => $foreign->id])->assertSessionHasErrors();
        $this->assertSame(0, Payment::where('invoice_id', $foreign->id)->count());
    }

    public function test_a_managers_apply_and_refund_are_requests_a_superuser_decides(): void
    {
        Notification::fake();
        [$property, $superuser, $manager, $unit, $tenant] = $this->world();
        $this->pay($superuser, $property, $tenant, $unit, $this->proforma($property, 'CRD/1'), 1300);   // credit 300
        $next = $this->proforma($property, 'CRD/2');

        $this->actingAs($manager)->post('/payments/credits/apply', ['invoice_id' => $next->id])->assertSessionHasNoErrors();
        $this->assertSame(0, Payment::where('invoice_id', $next->id)->count());
        $this->assertEquals(300, $this->balance($tenant));
        $applyRequest = PaymentChangeRequest::where('type', 'credit')->firstOrFail();
        Notification::assertSentTo($superuser, \App\Notifications\PaymentChangeRequestNotification::class);

        $this->actingAs($manager)->post('/payments/credits/refund', ['tenant_id' => $tenant->id, 'amount' => 50, 'method' => 'Cash'])->assertSessionHasNoErrors();
        $refundRequest = PaymentChangeRequest::where('type', 'refund')->firstOrFail();
        $this->assertEquals(300, $this->balance($tenant));

        $this->actingAs($superuser)->post("/superuser/payment-changes/{$applyRequest->id}/approve")->assertSessionHasNoErrors();
        $this->assertEquals(300, Payment::where('invoice_id', $next->id)->value('amount'));
        $this->assertEquals(0, $this->balance($tenant));

        // Nothing left to refund now, so approving the refund is refused and the request stays open.
        $this->actingAs($superuser)->post("/superuser/payment-changes/{$refundRequest->id}/approve")->assertSessionHasErrors('amount');
        $this->assertSame('pending', $refundRequest->fresh()->status);
    }

    public function test_a_refund_lowers_the_credit_and_cannot_exceed_it(): void
    {
        [$property, $superuser, $manager, $unit, $tenant] = $this->world();
        $this->pay($superuser, $property, $tenant, $unit, $this->proforma($property, 'CRD/1'), 1300);
        $acting = fn () => $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id]);

        $acting()->post('/payments/credits/refund', ['tenant_id' => $tenant->id, 'amount' => 100, 'method' => 'Bank Transfer', 'reference' => 'REF-1'])->assertSessionHasNoErrors();
        $this->assertEquals(200, $this->balance($tenant));
        $acting()->post('/payments/credits/refund', ['tenant_id' => $tenant->id, 'amount' => 500])->assertSessionHasErrors('amount');
        $this->assertEquals(200, $this->balance($tenant));
    }

    public function test_existing_overpayments_are_found_by_the_sync_used_for_the_backfill(): void
    {
        [$property, $superuser, $manager, $unit, $tenant] = $this->world();
        $invoice = $this->proforma($property, 'CRD/1');
        Payment::create(['property_id' => $property->id, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'invoice_id' => $invoice->id, 'amount' => 1250, 'amount_in_base' => 1250, 'currency' => 'TZS', 'status' => 'paid', 'method' => 'Cash', 'month' => 'x', 'paid_date' => now()->toDateString()]);
        $this->assertEquals(0, $this->balance($tenant));

        app(TenantCreditService::class)->syncOverpayment($invoice->fresh(['items', 'lease']));

        $this->assertEquals(250, $this->balance($tenant));
    }

    public function test_the_proforma_document_and_email_show_the_credit_applied_and_the_balance_due(): void
    {
        [$property, $superuser, $manager, $unit, $tenant] = $this->world();
        $this->pay($superuser, $property, $tenant, $unit, $this->proforma($property, 'CRD/1'), 1300);   // credit 300
        $next = $this->proforma($property, 'CRD/2');
        $render = fn ($inv) => view('pdf.proforma-invoice', array_merge($inv->buildPdfViewData($inv->items), ['invoice' => $inv, 'items' => $inv->items, 'invoiceLabel' => 'PROFORMA INVOICE']))->render();
        $before = $render($next->fresh(['items']));
        $this->assertStringNotContainsString('credit applied', $before);

        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])->post('/payments/credits/apply', ['invoice_id' => $next->id]);

        $fresh = $next->fresh(['items']);
        $pdf = $render($fresh);
        $this->assertStringContainsString('Less: credit applied', $pdf);
        $this->assertStringContainsString('Balance due', $pdf);
        $this->assertStringContainsString('700.00', $pdf);

        $mail = (new \App\Mail\ProformaInvoiceMail($fresh, $fresh->items))->render();
        $this->assertStringContainsString('Less: credit applied', $mail);
        $this->assertStringContainsString('700.00', $mail);
    }
}
