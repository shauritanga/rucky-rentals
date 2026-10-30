<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\LeaseInstallment;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\FloorScheduleReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloorScheduleReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_rows_are_grouped_by_floor_with_vat_wht_and_totals(): void
    {
        [$property, , $installment] = $this->scenario();

        $report = app(FloorScheduleReport::class)->build($property->id, null, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));

        $this->assertCount(1, $report['sections']);
        $section = $report['sections'][0];
        $this->assertSame('3rd Floor', $section['label']);
        $row = $section['rows'][0];

        // Rent 1,000 and service charge 300 a month for 4 months; VAT 18%, WHT 10% on rent and 5% on service charge.
        $this->assertSame(4, $row['terms']);
        $this->assertEquals(1000.00, $row['rent_per_month']);
        $this->assertEquals(300.00, $row['sc_per_month']);
        $this->assertEquals(4000.00, $row['total_rent']);
        $this->assertEquals(1200.00, $row['total_sc']);
        $this->assertEquals(5200.00, $row['excl']);
        $this->assertEquals(936.00, $row['vat']);
        $this->assertEquals(6136.00, $row['incl']);
        $this->assertEquals(400.00, $row['wht_rent']);
        $this->assertEquals(60.00, $row['wht_sc']);
        $this->assertEquals(460.00, $row['wht_total']);
        $this->assertEquals(5676.00, $row['net_after_wht']);
        $this->assertEquals(0.00, $row['paid']);
        $this->assertEquals(6136.00, $row['balance']);
        $this->assertEquals(6136.00, $section['totals']['incl']);
    }

    public function test_payments_receipts_and_wht_paid_reduce_the_balance(): void
    {
        [$property, $lease, $installment, $tenant, $unit] = $this->scenario();

        $invoice = Invoice::create([
            'invoice_number' => 'T/01/2026', 'type' => 'proforma', 'property_id' => $property->id, 'lease_id' => $lease->id,
            'tenant_name' => $tenant->name, 'unit_ref' => $unit->unit_number, 'issued_date' => '2026-01-01', 'due_date' => '2026-01-01',
            'status' => 'partially_paid', 'approval_status' => 'approved', 'currency' => 'TZS',
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Rent', 'item_type' => 'rent', 'quantity' => 1, 'unit_price' => 4000, 'total' => 4000]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'SC', 'item_type' => 'service_charge', 'quantity' => 1, 'unit_price' => 1200, 'total' => 1200]);
        $installment->update(['invoice_id' => $invoice->id]);

        $payment = Payment::create([
            'property_id' => $property->id, 'invoice_id' => $invoice->id, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id,
            'month' => 'Jan 2026', 'amount' => 3000, 'amount_in_base' => 3000, 'status' => 'paid', 'paid_date' => '2026-02-01',
            'currency' => 'TZS', 'exchange_rate' => 1, 'breakdown_rent' => 2000, 'breakdown_service_charge' => 600,
            'wht_confirmed' => true,
        ]);
        $receipt = Receipt::create([
            'receipt_number' => 'EFD-992', 'payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'tenant_id' => $tenant->id,
            'property_id' => $property->id, 'amount' => 3000, 'currency' => 'TZS', 'issued_at' => '2026-02-01',
        ]);
        $payment->update(['receipt_id' => $receipt->id]);

        $row = app(FloorScheduleReport::class)->build($property->id, null, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'))['sections'][0]['rows'][0];

        $this->assertEquals(3000.00, $row['paid']);
        $this->assertEquals(3136.00, $row['balance']);
        $this->assertEquals(230.00, $row['wht_paid']); // 10% of 2,000 + 5% of 600
        $this->assertSame('EFD-992', $row['efd']);
    }

    public function test_the_page_filters_by_floor_and_remarks_can_be_saved_and_exported(): void
    {
        [$property, , $installment] = $this->scenario();
        $superuser = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);
        $acting = fn () => $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id]);

        $acting()->get('/reports/floor-schedule?floor=7')->assertOk()->assertInertia(fn ($page) => $page->has('report.sections', 0));
        $acting()->get('/reports/floor-schedule?floor=3')->assertOk()->assertInertia(fn ($page) => $page->has('report.sections', 1));

        $acting()->patch("/reports/floor-schedule/installments/{$installment->id}/remark", ['remark' => 'Clear to 14th Dec 2026'])->assertRedirect();
        $this->assertSame('Clear to 14th Dec 2026', $installment->fresh()->remark);

        $csv = $acting()->get('/reports/floor-schedule/export?format=csv');
        $csv->assertOk();
        $this->assertStringContainsString('Clear to 14th Dec 2026', $csv->streamedContent());

        $acting()->get('/reports/floor-schedule/export?format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    /** One tenant, one unit on the 3rd floor, one 4-month installment (rent 1,000 + service charge 300 a month). */
    private function scenario(): array
    {
        $property = Property::create(['name' => 'Schedule Tower', 'code' => 'SCH', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0, 'country' => 'Tanzania']);
        $unit = Unit::create([
            'property_id' => $property->id, 'unit_number' => 'F3.01', 'floor' => '3', 'type' => 'Office Suite', 'size_sqft' => 100, 'size_sqm' => 9.29,
            'rate_per_sqm' => 107.64, 'service_charge_per_sqm' => 0, 'currency' => 'TZS', 'rent' => 1000, 'status' => 'occupied', 'deposit' => 0,
            'service_charge' => 300, 'electricity_type' => 'direct', 'approval_status' => 'approved',
        ]);
        $tenant = Tenant::create(['property_id' => $property->id, 'name' => 'Schedule Tenant', 'email' => 's@example.com', 'phone' => '0700000000', 'initials' => 'ST', 'tenant_type' => 'individual']);
        $lease = Lease::create([
            'lease_number' => 'L-SCH-1', 'property_id' => $property->id, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id,
            'start_date' => '2026-01-01', 'end_date' => '2027-01-01', 'duration_months' => 12, 'payment_cycle' => 4, 'currency' => 'TZS',
            'monthly_rent' => 1000, 'service_charge' => 300, 'deposit' => 0, 'status' => 'active', 'wht_rate' => 10, 'service_charge_rate' => 5, 'vat_rate' => 18,
        ]);
        $installment = LeaseInstallment::create([
            'property_id' => $property->id, 'lease_id' => $lease->id, 'sequence' => 1, 'period_start' => '2026-01-01', 'period_end' => '2026-04-30',
            'due_date' => '2026-01-01', 'amount' => 5200, 'currency' => 'TZS', 'status' => 'unpaid', 'paid_amount' => 0,
        ]);

        return [$property, $lease, $installment, $tenant, $unit];
    }
}
