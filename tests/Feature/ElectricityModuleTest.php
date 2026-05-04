<?php

namespace Tests\Feature;

use App\Models\ElectricitySale;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ElectricityModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_reading_creates_generator_only_proforma_invoice(): void
    {
        ['user' => $user, 'unit' => $unit] = $this->createLeaseContext('direct');

        $response = $this->actingAs($user)->post('/electricity/readings', [
            'unit_id' => $unit->id,
            'reading_date' => '2026-04-01',
            'prev_reading' => 100,
            'curr_reading' => 180,
        ]);

        $response->assertRedirect();

        $reading = MeterReading::with('invoice.items')->firstOrFail();

        $this->assertSame('2026-04', $reading->month);
        $this->assertEquals(80.00, $reading->gen_kwh);
        $this->assertSame('proforma', $reading->invoice->status);
        $this->assertSame('2026-04-01', $reading->invoice->period);
        $this->assertCount(2, $reading->invoice->items);
        $this->assertSame(
            ['Electricity — Generator Charge', 'Electricity — Generator VAT'],
            $reading->invoice->items->pluck('description')->all()
        );
        $this->assertEquals(112000.00, $reading->invoice->items->firstWhere('description', 'Electricity — Generator Charge')->total);
        $this->assertEquals(20160.00, $reading->invoice->items->firstWhere('description', 'Electricity — Generator VAT')->total);
        $this->assertEquals(132160.00, $reading->invoice->items->sum('total'));
    }

    public function test_saving_same_direct_date_updates_existing_proforma_invoice(): void
    {
        ['user' => $user, 'unit' => $unit] = $this->createLeaseContext('direct');

        $this->actingAs($user)->post('/electricity/readings', [
            'unit_id' => $unit->id,
            'reading_date' => '2026-04-01',
            'prev_reading' => 100,
            'curr_reading' => 180,
        ]);

        $invoiceId = Invoice::value('id');

        $this->actingAs($user)->post('/electricity/readings', [
            'unit_id' => $unit->id,
            'reading_date' => '2026-04-01',
            'prev_reading' => 100,
            'curr_reading' => 190,
        ]);

        $this->assertSame(1, Invoice::count());
        $this->assertSame($invoiceId, Invoice::value('id'));
        $this->assertEquals(148680.00, Invoice::firstOrFail()->items()->sum('total'));
    }

    public function test_saving_different_direct_dates_creates_separate_proforma_invoices(): void
    {
        ['user' => $user, 'unit' => $unit] = $this->createLeaseContext('direct');

        $this->actingAs($user)->post('/electricity/readings', [
            'unit_id' => $unit->id,
            'reading_date' => '2026-04-01',
            'prev_reading' => 100,
            'curr_reading' => 180,
        ]);

        $this->actingAs($user)->post('/electricity/readings', [
            'unit_id' => $unit->id,
            'reading_date' => '2026-04-02',
            'prev_reading' => 180,
            'curr_reading' => 190,
        ]);

        $this->assertSame(2, MeterReading::count());
        $this->assertSame(2, Invoice::count());
        $this->assertSame(
            ['2026-04-01', '2026-04-02'],
            Invoice::query()->orderBy('period')->pluck('period')->all()
        );
    }

    public function test_direct_reading_is_rejected_for_submeter_unit(): void
    {
        ['user' => $user, 'unit' => $unit] = $this->createLeaseContext('submeter');

        $response = $this->actingAs($user)->post('/electricity/readings', [
            'unit_id' => $unit->id,
            'reading_date' => '2026-04-01',
            'prev_reading' => 100,
            'curr_reading' => 150,
        ]);

        $response->assertSessionHasErrors('direct_reading');
        $this->assertSame(0, MeterReading::count());
        $this->assertSame(0, Invoice::count());
    }

    public function test_direct_reading_with_no_consumption_creates_no_generator_invoice(): void
    {
        ['user' => $user, 'unit' => $unit] = $this->createLeaseContext('direct');

        $response = $this->actingAs($user)->post('/electricity/readings', [
            'unit_id' => $unit->id,
            'reading_date' => '2026-04-01',
            'prev_reading' => 100,
            'curr_reading' => 100,
        ]);

        $response->assertRedirect();

        $reading = MeterReading::firstOrFail();

        $this->assertEquals(0.00, $reading->gen_kwh);
        $this->assertNull($reading->invoice_id);
        $this->assertSame(0, Invoice::count());
    }

    public function test_unit_exposes_latest_saved_meter_reading(): void
    {
        ['user' => $user, 'unit' => $unit, 'property' => $property] = $this->createLeaseContext('direct');

        MeterReading::create([
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'month' => '2026-04',
            'prev_reading' => 100,
            'curr_reading' => 180,
            'gen_kwh' => 80,
            'reading_date' => '2026-04-01',
            'recorded_by' => $user->name,
        ]);

        MeterReading::create([
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'month' => '2026-04',
            'prev_reading' => 180,
            'curr_reading' => 245,
            'gen_kwh' => 65,
            'reading_date' => '2026-04-15',
            'recorded_by' => $user->name,
        ]);

        $latestReading = $unit->fresh('latestMeterReading')->latestMeterReading;

        $this->assertNotNull($latestReading);
        $this->assertSame('2026-04-15', $latestReading->reading_date);
        $this->assertEquals(180.0, $latestReading->prev_reading);
        $this->assertEquals(245.0, $latestReading->curr_reading);
    }

    public function test_electricity_page_prompts_missing_current_quarter_direct_readings(): void
    {
        Carbon::setTestNow('2026-05-15 10:00:00');

        try {
            ['user' => $user, 'unit' => $readUnit, 'property' => $property] = $this->createLeaseContext('direct');
            $missingUnit = $this->addLeasedUnit($property, 'direct', 'U-MISS');
            $this->addLeasedUnit($property, 'submeter', 'U-SUB');

            MeterReading::create([
                'property_id' => $property->id,
                'unit_id' => $readUnit->id,
                'month' => '2026-04',
                'prev_reading' => 100,
                'curr_reading' => 180,
                'gen_kwh' => 80,
                'reading_date' => '2026-04-15',
                'recorded_by' => $user->name,
            ]);

            MeterReading::create([
                'property_id' => $property->id,
                'unit_id' => $missingUnit->id,
                'month' => '2026-03',
                'prev_reading' => 50,
                'curr_reading' => 75,
                'gen_kwh' => 25,
                'reading_date' => '2026-03-31',
                'recorded_by' => $user->name,
            ]);

            $response = $this->actingAs($user)->get('/electricity?tab=direct');

            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->component('Electricity/Index')
                ->where('directReadingPrompt.label', 'Q2 2026')
                ->where('directReadingPrompt.start_date', '2026-04-01')
                ->where('directReadingPrompt.end_date', '2026-06-30')
                ->where('directReadingPrompt.total_units', 2)
                ->where('directReadingPrompt.read_units', 1)
                ->has('directReadingPrompt.missing_units', 1)
                ->where('directReadingPrompt.missing_units.0.unit_number', 'U-MISS')
                ->has('directReadings', 1)
                ->where('directReadings.0.unit_id', $readUnit->id)
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_submeter_sale_creates_draft_invoice(): void
    {
        ['user' => $user, 'unit' => $unit] = $this->createLeaseContext('submeter');

        $response = $this->actingAs($user)->post('/electricity/sales', [
            'unit_id' => $unit->id,
            'sale_date' => '2026-04-01',
            'amount_paid' => 15000,
            'unit_price' => 500,
            'notes' => 'Counter sale',
        ]);

        $response->assertRedirect();

        $sale = ElectricitySale::with('invoice.items')->firstOrFail();

        $this->assertSame('draft', $sale->invoice->status);
        $this->assertCount(2, $sale->invoice->items);
        $this->assertSame(
            ['Electricity — Submeter Charge', 'Electricity — Submeter VAT'],
            $sale->invoice->items->pluck('description')->all()
        );
        $this->assertEquals(30.00, $sale->units_sold);
        $this->assertEquals(15000.00, $sale->invoice->items->sum('total'));
    }

    public function test_submeter_sale_calculates_units_from_amount_paid(): void
    {
        ['user' => $user, 'unit' => $unit] = $this->createLeaseContext('submeter');

        $response = $this->actingAs($user)->post('/electricity/sales', [
            'unit_id' => $unit->id,
            'sale_date' => '2026-04-01',
            'amount_paid' => 10000,
            'unit_price' => 750,
        ]);

        $response->assertRedirect();

        $sale = ElectricitySale::firstOrFail();

        $this->assertEquals(10000.00, $sale->amount);
        $this->assertEquals(13.33, $sale->units_sold);
    }

    public function test_submeter_sale_is_rejected_for_direct_unit(): void
    {
        ['user' => $user, 'unit' => $unit] = $this->createLeaseContext('direct');

        $response = $this->actingAs($user)->post('/electricity/sales', [
            'unit_id' => $unit->id,
            'sale_date' => '2026-04-01',
            'amount_paid' => 15000,
            'unit_price' => 500,
        ]);

        $response->assertSessionHasErrors('submeter_sale');
        $this->assertSame(0, ElectricitySale::count());
        $this->assertSame(0, Invoice::count());
    }

    private function createLeaseContext(string $electricityType): array
    {
        $property = Property::create([
            'name' => 'Ruky Plaza',
            'code' => 'RPZ-' . strtoupper(substr($electricityType, 0, 3)),
            'status' => 'active',
            'unit_count' => 1,
            'occupied_units' => 1,
            'country' => 'Tanzania',
            'floor_config' => [
                'basements' => 0,
                'has_ground_floor' => false,
                'has_mezzanine' => false,
                'upper_floors' => 7,
            ],
        ]);

        $user = User::factory()->create([
            'role' => 'manager',
            'status' => 'active',
            'property_id' => $property->id,
            'must_change_password' => false,
        ]);

        $tenant = Tenant::create([
            'property_id' => $property->id,
            'name' => ucfirst($electricityType) . ' Tenant',
            'email' => $electricityType . '@example.com',
            'phone' => '255700000001',
            'initials' => strtoupper(substr($electricityType, 0, 4)),
            'tenant_type' => 'individual',
        ]);

        $unit = Unit::create([
            'property_id' => $property->id,
            'unit_number' => 'U-' . strtoupper(substr($electricityType, 0, 3)),
            'floor' => '1',
            'type' => 'Shop',
            'size_sqft' => 100,
            'size_sqm' => 9.29,
            'rate_per_sqm' => 107.64,
            'service_charge_per_sqm' => 0,
            'currency' => 'TZS',
            'rent' => 1000,
            'status' => 'occupied',
            'deposit' => 0,
            'service_charge' => 0,
            'electricity_type' => $electricityType,
        ]);

        Lease::create([
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
            'vat_rate' => 18,
            'status' => 'active',
        ]);

        return compact('property', 'user', 'tenant', 'unit');
    }

    private function addLeasedUnit(Property $property, string $electricityType, string $unitNumber): Unit
    {
        $tenant = Tenant::create([
            'property_id' => $property->id,
            'name' => $unitNumber . ' Tenant',
            'email' => strtolower($unitNumber) . '@example.com',
            'phone' => '255700000002',
            'initials' => str_replace('-', '', $unitNumber),
            'tenant_type' => 'individual',
        ]);

        $unit = Unit::create([
            'property_id' => $property->id,
            'unit_number' => $unitNumber,
            'floor' => '1',
            'type' => 'Shop',
            'size_sqft' => 100,
            'size_sqm' => 9.29,
            'rate_per_sqm' => 107.64,
            'service_charge_per_sqm' => 0,
            'currency' => 'TZS',
            'rent' => 1000,
            'status' => 'occupied',
            'deposit' => 0,
            'service_charge' => 0,
            'electricity_type' => $electricityType,
        ]);

        Lease::create([
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
            'vat_rate' => 18,
            'status' => 'active',
        ]);

        return $unit;
    }
}
