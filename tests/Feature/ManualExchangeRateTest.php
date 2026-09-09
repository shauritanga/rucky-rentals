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

class ManualExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    private function setupPropertyManagerAndUnit(): array
    {
        $property = Property::create([
            'name' => 'Test Property',
            'code' => 'TP01',
            'address' => 'Street 1',
            'city' => 'Dar es Salaam',
            'country' => 'Tanzania',
            'status' => 'active',
            'unit_count' => 0,
            'occupied_units' => 0,
        ]);
        $manager = User::factory()->create([
            'property_id' => $property->id,
            'role' => 'manager',
            'must_change_password' => false,
            'status' => 'active',
        ]);
        $tenant = Tenant::create([
            'property_id' => $property->id,
            'name' => 'Test Tenant',
            'initials' => 'TT',
            'email' => 'tenant@example.com',
            'phone' => '+255700000000',
        ]);
        $unit = Unit::create([
            'property_id' => $property->id,
            'unit_number' => 'A-101',
            'floor' => 1,
            'approval_status' => 'approved',
            'status' => 'occupied',
            'type' => 'residential',
            'size_sqft' => 100,
            'rent' => 1000,
            'deposit' => 1000,
        ]);

        return [$property, $manager, $tenant, $unit];
    }

    public function test_can_create_invoice_with_manual_exchange_rate_up_to_four_decimals(): void
    {
        [$property, $manager, $tenant, $unit] = $this->setupPropertyManagerAndUnit();

        $fxRate = 2650.1234;
        $unitPrice = 1000.00;

        $response = $this->actingAs($manager)->post('/invoices', [
            'type' => 'proforma',
            'tenant_name' => $tenant->name,
            'tenant_email' => 'tenant@example.com',
            'unit_ref' => 'A-101',
            'issued_date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'period' => 'May 2026',
            'currency' => 'USD',
            'exchange_rate' => $fxRate,
            'items' => [
                [
                    'description' => 'Rent May',
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                ],
            ],
        ]);

        $response->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('USD', $invoice->currency);
        $this->assertEquals(2650.1234, (float) $invoice->exchange_rate);
        $this->assertEquals(round(1000.00 * 2650.1234, 2), (float) $invoice->total_in_base);
    }

    public function test_can_update_editable_proforma_with_manual_exchange_rate_up_to_four_decimals(): void
    {
        [$property, $manager, $tenant, $unit] = $this->setupPropertyManagerAndUnit();

        $invoice = Invoice::create([
            'invoice_number' => 'PF-TEST-001',
            'property_id' => $property->id,
            'requested_by_user_id' => $manager->id,
            'tenant_name' => $tenant->name,
            'tenant_email' => 'tenant@example.com',
            'unit_ref' => 'A-101',
            'issued_date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'period' => 'May 2026',
            'type' => 'proforma',
            'status' => 'proforma',
            'approval_status' => 'rejected',
            'currency' => 'USD',
            'exchange_rate' => 2600.0000,
            'total_in_base' => 2600000.00,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Rent',
            'quantity' => 1,
            'unit_price' => 1000,
            'total' => 1000,
        ]);

        $newFxRate = 2655.8765;

        $response = $this->actingAs($manager)->put("/invoices/{$invoice->id}", [
            'tenant_name' => $tenant->name,
            'tenant_email' => 'tenant@example.com',
            'unit_ref' => 'A-101',
            'issued_date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'period' => 'May 2026',
            'currency' => 'USD',
            'exchange_rate' => $newFxRate,
            'items' => [
                [
                    'description' => 'Rent May Revised',
                    'quantity' => 1,
                    'unit_price' => 1200,
                ],
            ],
        ]);

        $response->assertRedirect();

        $updated = $invoice->fresh();
        $this->assertEquals(2655.8765, (float) $updated->exchange_rate);
        $this->assertEquals(round(1200.00 * 2655.8765, 2), (float) $updated->total_in_base);
    }

    public function test_can_record_payment_with_manual_exchange_rate_up_to_four_decimals(): void
    {
        [$property, $manager, $tenant, $unit] = $this->setupPropertyManagerAndUnit();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'unit_id' => $unit->id,
            'unit_ref' => 'A-101',
            'type' => 'tax_invoice',
            'status' => 'unpaid',
            'approval_status' => 'approved',
            'currency' => 'USD',
            'exchange_rate' => 2650.1234,
            'total_in_base' => 2650123.40,
            'issued_date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'period' => 'May 2026',
        ]);

        $payFxRate = 2652.4567;
        $payAmount = 500.00;

        $response = $this->actingAs($manager)->post('/payments', [
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'amount' => $payAmount,
            'currency' => 'USD',
            'exchange_rate' => $payFxRate,
            'method' => 'Bank Transfer',
            'status' => 'paid',
            'paid_date' => '2026-05-02',
            'month' => 'May 2026',
            'reference' => 'TXN-USD-001',
        ]);

        $response->assertRedirect();

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('USD', $payment->currency);
        $this->assertEquals(2652.4567, (float) $payment->exchange_rate);
        $this->assertEquals(round(500.00 * 2652.4567, 2), (float) $payment->amount_in_base);
    }

    public function test_can_create_lease_with_manual_exchange_rate_up_to_four_decimals(): void
    {
        [$property, $manager, $tenant, $unit] = $this->setupPropertyManagerAndUnit();
        $unit->update(['currency' => 'USD']);

        $leaseFxRate = 2650.1234;

        $response = $this->actingAs($manager)->post('/leases', [
            'tenant_mode' => 'existing',
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'start_date' => '2026-06-01',
            'end_date' => '2027-06-01',
            'duration_months' => 12,
            'payment_cycle' => 3,
            'monthly_rent' => 1500,
            'deposit' => 1500,
            'exchange_rate' => $leaseFxRate,
            'terms' => 'Test USD lease terms',
        ]);

        $response->assertRedirect();

        $lease = Lease::query()->firstOrFail();
        $this->assertSame('USD', $lease->currency);
        $this->assertEquals(2650.1234, (float) $lease->exchange_rate);
    }

    public function test_can_update_lease_with_manual_exchange_rate_up_to_four_decimals(): void
    {
        [$property, $manager, $tenant, $unit] = $this->setupPropertyManagerAndUnit();
        $unit->update(['currency' => 'USD']);

        $lease = Lease::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'lease_number' => 'L-TEST-001',
            'start_date' => '2026-06-01',
            'end_date' => '2027-06-01',
            'duration_months' => 12,
            'payment_cycle' => 3,
            'monthly_rent' => 1500,
            'deposit' => 1500,
            'currency' => 'USD',
            'exchange_rate' => 2600.0000,
            'status' => 'pending_accountant',
        ]);

        $newRate = 2655.8765;

        $response = $this->actingAs($manager)->patch("/leases/{$lease->id}", [
            'action' => 'edit',
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'start_date' => '2026-06-01',
            'end_date' => '2027-06-01',
            'duration_months' => 12,
            'payment_cycle' => 3,
            'monthly_rent' => 1600,
            'deposit' => 1600,
            'exchange_rate' => $newRate,
            'terms' => 'Updated terms',
        ]);

        $response->assertRedirect();

        $updated = $lease->fresh();
        $this->assertEquals(2655.8765, (float) $updated->exchange_rate);
    }

    public function test_can_create_unit_with_manual_exchange_rate_up_to_four_decimals(): void
    {
        [$property, $manager] = $this->setupPropertyManagerAndUnit();

        $unitRate = 2651.9876;

        $response = $this->actingAs($manager)->post('/units', [
            'unit_number' => 'B-201',
            'floor' => '1',
            'type' => 'Office Suite',
            'size_sqm' => 50,
            'rate_per_sqm' => 25,
            'currency' => 'USD',
            'exchange_rate' => $unitRate,
            'electricity_type' => 'direct',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $unit = Unit::where('unit_number', 'B-201')->firstOrFail();
        $this->assertSame('USD', $unit->currency);
        $this->assertEquals(2651.9876, (float) $unit->exchange_rate);
    }

    public function test_can_update_unit_with_manual_exchange_rate_up_to_four_decimals(): void
    {
        [$property, $manager, $tenant, $unit] = $this->setupPropertyManagerAndUnit();

        $newRate = 2653.4321;

        $response = $this->actingAs($manager)->patch("/units/{$unit->id}", [
            'unit_number' => 'A-101',
            'floor' => '1',
            'type' => 'Office Suite',
            'size_sqm' => 100,
            'rate_per_sqm' => 20,
            'currency' => 'USD',
            'exchange_rate' => $newRate,
            'status' => 'vacant',
            'electricity_type' => 'direct',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $updated = $unit->fresh();
        $this->assertSame('USD', $updated->currency);
        $this->assertEquals(2653.4321, (float) $updated->exchange_rate);
    }

    public function test_lease_inherits_exchange_rate_from_unit_when_not_specified(): void
    {
        [$property, $manager, $tenant, $unit] = $this->setupPropertyManagerAndUnit();
        $unit->update([
            'currency' => 'USD',
            'exchange_rate' => 2654.3210,
        ]);

        $response = $this->actingAs($manager)->post('/leases', [
            'tenant_mode' => 'existing',
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'start_date' => '2026-07-01',
            'end_date' => '2027-07-01',
            'duration_months' => 12,
            'payment_cycle' => 3,
            'monthly_rent' => 2000,
            'deposit' => 2000,
            // exchange_rate omitted on purpose to test inheritance
        ]);

        $response->assertRedirect();

        $lease = Lease::query()->firstOrFail();
        $this->assertSame('USD', $lease->currency);
        $this->assertEquals(2654.3210, (float) $lease->exchange_rate);
    }
}
