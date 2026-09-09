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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardOverdueRentTest extends TestCase
{
    use RefreshDatabase;

    private function createProperty(string $name = 'Test Property', string $code = 'TP1'): Property
    {
        return Property::create([
            'name' => $name,
            'code' => $code,
            'address' => '123 Test Street',
            'city' => 'Dar es Salaam',
            'status' => 'active',
        ]);
    }

    private function createUser(string $role = 'manager', ?int $propertyId = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'property_id' => $propertyId,
            'status' => 'active',
        ]);
    }

    private function createUnit(int $propertyId, string $unitNumber = 'U-101'): Unit
    {
        return Unit::create([
            'property_id' => $propertyId,
            'unit_number' => $unitNumber,
            'floor' => 1,
            'type' => '1 Bed',
            'size_sqft' => 500,
            'rent' => 1000,
            'currency' => 'TZS',
            'status' => 'occupied',
            'approval_status' => 'approved',
        ]);
    }

    private function createInvoice(int $propertyId, string $dueDate, string $status, float $amount, string $unitRef = 'U-101'): Invoice
    {
        $tenant = Tenant::create([
            'property_id' => $propertyId,
            'name' => 'Test Tenant',
            'first_name' => 'Test',
            'last_name' => 'Tenant',
            'email' => 'tenant_' . uniqid() . '@example.com',
            'phone' => '0700000000',
            'national_id' => '1234567890',
            'initials' => 'TT',
            'tenant_type' => 'individual',
            'nok_name' => 'Kin Name',
            'nok_phone' => '0700000001',
            'nok_relation' => 'Sibling',
        ]);

        $invoice = Invoice::create([
            'property_id' => $propertyId,
            'invoice_number' => 'INV-' . strtoupper(uniqid()),
            'tenant_name' => $tenant->name,
            'unit_ref' => $unitRef,
            'type' => 'invoice',
            'status' => $status,
            'due_date' => $dueDate,
            'issued_date' => now()->subDays(30)->toDateString(),
            'currency' => 'TZS',
            'approval_status' => 'approved',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Rent',
            'quantity' => 1,
            'unit_price' => $amount,
            'total' => $amount,
        ]);

        return $invoice;
    }

    public function test_dashboard_reflects_overdue_invoice_in_overdue_balance_and_units(): void
    {
        $property = $this->createProperty();
        $manager = $this->createUser('manager', $property->id);
        $this->createUnit($property->id, 'U-101');

        // Past due invoice
        $this->createInvoice($property->id, now()->subDays(10)->toDateString(), 'overdue', 500000, 'U-101');

        $response = $this->actingAs($manager)->get(route('dashboard'));
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.overdueUnits', 1)
            ->where('stats.overdueBalance', 500000)
        );
    }

    public function test_dashboard_deducts_paid_payments_from_overdue_balance(): void
    {
        $property = $this->createProperty();
        $manager = $this->createUser('manager', $property->id);
        $unit = $this->createUnit($property->id, 'U-102');

        $invoice = $this->createInvoice($property->id, now()->subDays(20)->toDateString(), 'partially_paid', 1000000, 'U-102');

        Payment::create([
            'property_id' => $property->id,
            'tenant_id' => Tenant::first()->id,
            'unit_id' => $unit->id,
            'invoice_id' => $invoice->id,
            'amount' => 400000,
            'currency' => 'TZS',
            'status' => 'paid',
            'month' => 'Mar 2026',
            'paid_date' => now()->subDays(5)->toDateString(),
        ]);

        $response = $this->actingAs($manager)->get(route('dashboard'));
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.overdueUnits', 1)
            ->where('stats.overdueBalance', 600000)
        );
    }
}
