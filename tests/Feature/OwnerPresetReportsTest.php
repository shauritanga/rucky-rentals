<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerPresetReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_rent_report_lists_invoices_with_a_balance_only(): void
    {
        [$user, $property, $lease, $tenant, $unit] = $this->world();
        $owing = $this->invoice($property, $lease, $tenant, $unit, 'OWING/01/2026', 'proforma');
        $paidUp = $this->invoice($property, $lease, $tenant, $unit, 'PAID/01/2026', 'proforma');
        $this->pay($property, $paidUp, $tenant, $unit, 6136, false); // full amount
        $this->pay($property, $owing, $tenant, $unit, 3000, false);   // part payment

        $rows = $this->runPreset($user, $property, 'unpaid-rent');

        $this->assertCount(1, $rows);
        $this->assertSame('OWING/01/2026', $rows[0]['invoices__invoice_number']);
        $this->assertEquals(4000.00, $rows[0]['invoices__rent_amount']);
        $this->assertEquals(1200.00, $rows[0]['invoices__service_charge_amount']);
        $this->assertEquals(6136.00, $rows[0]['invoices__total_due']);
        $this->assertEquals(3000.00, $rows[0]['invoices__amount_paid']);
        $this->assertEquals(3136.00, $rows[0]['invoices__balance_due']);
    }

    public function test_wht_outstanding_and_wht_paid_reports(): void
    {
        [$user, $property, $lease, $tenant, $unit] = $this->world();
        $unconfirmed = $this->invoice($property, $lease, $tenant, $unit, 'NOWHT/01/2026', 'proforma');
        $confirmed = $this->invoice($property, $lease, $tenant, $unit, 'WHT/01/2026', 'proforma');
        $this->pay($property, $unconfirmed, $tenant, $unit, 3000, false);
        $this->pay($property, $confirmed, $tenant, $unit, 6136, true, 'TRA-77');

        // Paid but WHT not confirmed: the full expected 460 (10% of 4,000 + 5% of 1,200) is outstanding.
        $outstanding = $this->runPreset($user, $property, 'wht-outstanding');
        $this->assertCount(1, $outstanding);
        $this->assertSame('NOWHT/01/2026', $outstanding[0]['invoices__invoice_number']);
        $this->assertEquals(460.00, $outstanding[0]['invoices__wht_due']);
        $this->assertEquals(460.00, $outstanding[0]['invoices__wht_outstanding']);

        // WHT confirmed on a 4,000 + 1,200 payment: 400 + 60 = 460, with its reference.
        $paid = $this->runPreset($user, $property, 'wht-paid');
        $this->assertCount(1, $paid);
        $this->assertSame('TRA-77', $paid[0]['payments__wht_reference']);
        $this->assertEquals(460.00, $paid[0]['payments__wht_amount']);
    }

    private function runPreset(User $user, Property $property, string $presetKey): array
    {
        $presets = collect($this->actingAs($user)->withSession(['superuser_viewing_property_id' => $property->id])
            ->get('/reports/builder')->original->getData()['page']['props']['presets']);
        $config = $presets->firstWhere('key', $presetKey)['config'];

        $response = $this->actingAs($user)->withSession(['superuser_viewing_property_id' => $property->id])
            ->postJson('/reports/builder/preview', ['config' => $config]);
        $response->assertOk();

        return $response->json('rows');
    }

    private function world(): array
    {
        $user = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);
        $property = Property::create(['name' => 'Owner Tower', 'code' => 'OWN', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0, 'country' => 'Tanzania']);
        $unit = Unit::create([
            'property_id' => $property->id, 'unit_number' => 'F3.01', 'floor' => '3', 'type' => 'Office Suite', 'size_sqft' => 100, 'size_sqm' => 9.29,
            'rate_per_sqm' => 107.64, 'service_charge_per_sqm' => 0, 'currency' => 'TZS', 'rent' => 1000, 'status' => 'occupied', 'deposit' => 0,
            'service_charge' => 300, 'electricity_type' => 'direct', 'approval_status' => 'approved',
        ]);
        $tenant = Tenant::create(['property_id' => $property->id, 'name' => 'Owner Tenant', 'email' => 'o@example.com', 'phone' => '0700000000', 'initials' => 'OT', 'tenant_type' => 'individual']);
        $lease = Lease::create([
            'lease_number' => 'L-OWN-1', 'property_id' => $property->id, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id,
            'start_date' => '2026-01-01', 'end_date' => '2027-01-01', 'duration_months' => 12, 'payment_cycle' => 4, 'currency' => 'TZS',
            'monthly_rent' => 1000, 'service_charge' => 300, 'deposit' => 0, 'status' => 'active', 'wht_rate' => 10, 'service_charge_rate' => 5, 'vat_rate' => 18,
        ]);

        return [$user, $property, $lease, $tenant, $unit];
    }

    /** Rent 4,000 + service charge 1,200 + 18% VAT = 6,136. */
    private function invoice(Property $property, Lease $lease, Tenant $tenant, Unit $unit, string $number, string $type): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => $number, 'type' => $type, 'property_id' => $property->id, 'lease_id' => $lease->id,
            'tenant_name' => $tenant->name, 'unit_ref' => $unit->unit_number, 'issued_date' => '2026-01-01', 'due_date' => '2026-01-01',
            'status' => 'proforma', 'approval_status' => 'approved', 'currency' => 'TZS',
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Rent', 'item_type' => 'rent', 'quantity' => 1, 'unit_price' => 4000, 'total' => 4000]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'SC', 'item_type' => 'service_charge', 'quantity' => 1, 'unit_price' => 1200, 'total' => 1200]);

        return $invoice;
    }

    private function pay(Property $property, Invoice $invoice, Tenant $tenant, Unit $unit, float $amount, bool $whtConfirmed, ?string $whtReference = null): Payment
    {
        $ratio = $amount / 6136;

        return Payment::create([
            'property_id' => $property->id, 'invoice_id' => $invoice->id, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id,
            'month' => 'Jan 2026', 'amount' => $amount, 'amount_in_base' => $amount, 'status' => 'paid', 'paid_date' => '2026-02-01',
            'currency' => 'TZS', 'exchange_rate' => 1, 'breakdown_rent' => round(4000 * $ratio, 2), 'breakdown_service_charge' => round(1200 * $ratio, 2),
            'wht_confirmed' => $whtConfirmed, 'wht_reference' => $whtReference,
        ]);
    }
}
