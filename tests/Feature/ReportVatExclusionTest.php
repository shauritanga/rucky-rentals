<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\MaintenanceRecord;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportVatExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_overview_excludes_vat_from_gross_revenue_and_net_revenue(): void
    {
        [$user, $tenant, $unit, $lease, $property] = $this->createLeaseContext();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-0001',
            'type' => 'invoice',
            'property_id' => $property->id,
            'lease_id' => $lease->id,
            'tenant_name' => $tenant->name,
            'tenant_email' => $tenant->email,
            'unit_ref' => $unit->unit_number,
            'issued_date' => '2026-04-05',
            'due_date' => '2026-04-15',
            'period' => '2026-04',
            'status' => 'paid',
            'currency' => 'TZS',
            'exchange_rate' => 1,
            'total_in_base' => 1510.40,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Rent',
            'item_type' => 'rent',
            'quantity' => 1,
            'unit_price' => 1000,
            'total' => 1000,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Service charge',
            'item_type' => 'service_charge',
            'quantity' => 1,
            'unit_price' => 180,
            'total' => 180,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Electricity — Submeter Charge',
            'item_type' => 'electricity_charge',
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Electricity — Submeter VAT',
            'item_type' => 'electricity_vat',
            'quantity' => 1,
            'unit_price' => 18,
            'total' => 18,
        ]);

        Payment::create([
            'property_id' => $property->id,
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'month' => 'Apr 2026',
            'amount' => 1510.40,
            'method' => 'Bank Transfer',
            'status' => 'paid',
            'paid_date' => '2026-04-10',
            'currency' => 'TZS',
            'exchange_rate' => 1,
            'amount_in_base' => 1510.40,
            'breakdown_rent' => 1000,
            'breakdown_service_charge' => 180,
            'breakdown_electricity' => 118,
        ]);

        MaintenanceRecord::create([
            'property_id' => $property->id,
            'ticket_number' => 'TK-001',
            'title' => 'HVAC repair',
            'unit_ref' => $unit->unit_number,
            'unit_id' => $unit->id,
            'category' => 'HVAC',
            'priority' => 'med',
            'status' => 'resolved',
            'reported_by' => $user->name,
            'cost' => 80,
            'reported_date' => '2026-04-12',
            'notes' => [],
        ]);

        $response = $this->actingAs($user)->get('/reports?period=custom&from=2026-04-01&to=2026-06-30');

        $response->assertOk();
        $page = $this->extractInertiaPage($response->getContent());

        $this->assertEquals(1280.0, data_get($page, 'props.report.kpis.revenue'));
        $this->assertEquals(80.0, data_get($page, 'props.report.kpis.totalExpenses'));
        $this->assertEquals(1200.0, data_get($page, 'props.report.kpis.noi'));
        $this->assertEquals(1280.0, data_get($page, 'props.report.monthlyRevenue.0.value'));
        $this->assertEquals(1000.0, data_get($page, 'props.report.monthlyRevenue.0.rent'));
        $this->assertEquals(180.0, data_get($page, 'props.report.monthlyRevenue.0.service_charge'));
        $this->assertEquals(100.0, data_get($page, 'props.report.monthlyRevenue.0.electricity'));
        $this->assertEquals(1280.0, data_get($page, 'props.report.topUnits.0.amount'));
        $this->assertEquals(1280.0, data_get($page, 'props.report.tenantSummary.0.invoiced'));
        $this->assertEquals(1280.0, data_get($page, 'props.report.tenantSummary.0.paid'));
        $this->assertEquals(0.0, data_get($page, 'props.report.tenantSummary.0.balance'));

        $csv = $this->actingAs($user)->get('/reports/export?type=overview&period=custom&from=2026-04-01&to=2026-06-30');

        $csv->assertOk();
        $streamed = $csv->streamedContent();
        $this->assertStringContainsString("\"Gross Revenue\",\"1,280.00\"", $streamed);
        $this->assertStringContainsString("\"Total Expenses\",80.00", $streamed);
        $this->assertStringContainsString("\"Net Revenue\",\"1,200.00\"", $streamed);
    }

    private function createLeaseContext(): array
    {
        $property = Property::create([
            'name' => 'Report Plaza',
            'code' => 'RPT-001',
            'status' => 'active',
            'unit_count' => 1,
            'occupied_units' => 1,
            'country' => 'Tanzania',
            'floor_config' => [
                'basements' => 0,
                'has_ground_floor' => true,
                'has_mezzanine' => false,
                'upper_floors' => 5,
            ],
        ]);

        $user = User::factory()->create([
            'role' => 'manager',
            'status' => 'active',
            'must_change_password' => false,
            'property_id' => $property->id,
        ]);

        $tenant = Tenant::create([
            'property_id' => $property->id,
            'name' => 'Tenant One',
            'email' => 'tenant.one@example.com',
            'phone' => '255700000001',
            'initials' => 'TO',
        ]);

        $unit = Unit::create([
            'property_id' => $property->id,
            'unit_number' => 'G.01',
            'floor' => 'G',
            'type' => 'Office Suite',
            'size_sqft' => 5382,
            'size_sqm' => 500,
            'rate_per_sqm' => 45000,
            'service_charge_per_sqm' => 4000,
            'currency' => 'TZS',
            'rent' => 22500000,
            'status' => 'occupied',
            'deposit' => 24500000,
            'service_charge' => 2000000,
            'electricity_type' => 'submeter',
        ]);

        $lease = Lease::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'duration_months' => 12,
            'payment_cycle' => 12,
            'currency' => 'TZS',
            'monthly_rent' => 1000,
            'deposit' => 0,
            'wht_rate' => 10,
            'service_charge_rate' => 5,
            'vat_rate' => 18,
            'status' => 'active',
        ]);

        return [$user, $tenant, $unit, $lease, $property];
    }

    private function extractInertiaPage(string $html): array
    {
        preg_match('/data-page="([^"]+)"/', $html, $matches);

        $this->assertNotEmpty($matches[1] ?? null, 'Inertia page payload not found in response HTML.');

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
    }
}
