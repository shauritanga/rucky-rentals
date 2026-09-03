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

class DueInvoicesReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_due_reminders_scoped_to_their_property(): void
    {
        $propertyA = $this->createProperty('Property A', 'PA1');
        $propertyB = $this->createProperty('Property B', 'PB1');
        $manager = $this->createUser('manager', $propertyA->id);

        $this->createInvoice($propertyA->id, now()->subDay()->toDateString(), 'unpaid', 1000, 'Tenant A');
        $this->createInvoice($propertyB->id, now()->subDay()->toDateString(), 'unpaid', 1000, 'Tenant B');

        $response = $this->actingAs($manager)->getJson('/invoices/due-reminders');
        $response->assertOk();
        $due = $response->json('due_invoices');

        $this->assertCount(1, $due);
        $this->assertSame('Tenant A', $due[0]['tenant_name']);
    }

    public function test_superuser_outside_property_view_sees_all_due_invoices(): void
    {
        $propertyA = $this->createProperty('Property A', 'PA2');
        $propertyB = $this->createProperty('Property B', 'PB2');
        $superuser = $this->createUser('superuser');

        $this->createInvoice($propertyA->id, now()->subDay()->toDateString(), 'unpaid', 1000, 'Tenant A');
        $this->createInvoice($propertyB->id, now()->subDay()->toDateString(), 'unpaid', 1000, 'Tenant B');

        $response = $this->actingAs($superuser)->getJson('/invoices/due-reminders');
        $this->assertCount(2, $response->json('due_invoices'));
    }

    public function test_superuser_in_property_view_mode_is_scoped_like_a_manager(): void
    {
        $propertyA = $this->createProperty('Property A', 'PA3');
        $propertyB = $this->createProperty('Property B', 'PB3');
        $superuser = $this->createUser('superuser');

        $this->createInvoice($propertyA->id, now()->subDay()->toDateString(), 'unpaid', 1000, 'Tenant A');
        $this->createInvoice($propertyB->id, now()->subDay()->toDateString(), 'unpaid', 1000, 'Tenant B');

        $response = $this->actingAs($superuser)
            ->withSession(['superuser_viewing_property_id' => $propertyA->id])
            ->getJson('/invoices/due-reminders');

        $due = $response->json('due_invoices');
        $this->assertCount(1, $due);
        $this->assertSame('Tenant A', $due[0]['tenant_name']);
    }

    public function test_accountant_can_access_due_reminders_unscoped(): void
    {
        $propertyA = $this->createProperty('Property A', 'PA4');
        $propertyB = $this->createProperty('Property B', 'PB4');
        $accountant = $this->createUser('accountant');

        $this->createInvoice($propertyA->id, now()->subDay()->toDateString(), 'unpaid', 1000, 'Tenant A');
        $this->createInvoice($propertyB->id, now()->subDay()->toDateString(), 'unpaid', 1000, 'Tenant B');

        $response = $this->actingAs($accountant)->getJson('/invoices/due-reminders');
        $response->assertOk();
        $this->assertCount(2, $response->json('due_invoices'));
    }

    public function test_unauthorized_roles_are_forbidden(): void
    {
        $property = $this->createProperty('Property A', 'PA5');

        foreach (['lease_manager', 'viewer', 'maintenance_staff'] as $role) {
            $user = $this->createUser($role, $property->id);
            $this->actingAs($user)->getJson('/invoices/due-reminders')->assertStatus(403);
        }
    }

    public function test_overdue_invoice_is_included_regardless_of_stale_status(): void
    {
        $property = $this->createProperty('Property A', 'PA6');
        $manager = $this->createUser('manager', $property->id);

        // due_date far in the past, status never recomputed to 'overdue'.
        $this->createInvoice($property->id, now()->subDays(30)->toDateString(), 'unpaid', 500, 'Stale Tenant');

        $response = $this->actingAs($manager)->getJson('/invoices/due-reminders');
        $due = $response->json('due_invoices');

        $this->assertCount(1, $due);
        $this->assertTrue($due[0]['is_overdue']);
        $this->assertSame(30, $due[0]['days']);
    }

    public function test_invoice_due_within_7_days_is_included_and_beyond_is_excluded(): void
    {
        $property = $this->createProperty('Property A', 'PA7');
        $manager = $this->createUser('manager', $property->id);

        $this->createInvoice($property->id, now()->addDays(6)->toDateString(), 'unpaid', 500, 'Soon Tenant');
        $this->createInvoice($property->id, now()->addDays(8)->toDateString(), 'unpaid', 500, 'Later Tenant');

        $response = $this->actingAs($manager)->getJson('/invoices/due-reminders');
        $due = $response->json('due_invoices');

        $this->assertCount(1, $due);
        $this->assertSame('Soon Tenant', $due[0]['tenant_name']);
        $this->assertFalse($due[0]['is_overdue']);
    }

    public function test_draft_proforma_and_paid_invoices_are_excluded(): void
    {
        $property = $this->createProperty('Property A', 'PA8');
        $manager = $this->createUser('manager', $property->id);

        $this->createInvoice($property->id, now()->subDay()->toDateString(), 'draft', 500, 'Draft Tenant');
        $this->createInvoice($property->id, now()->subDay()->toDateString(), 'proforma', 500, 'Proforma Tenant');
        $this->createInvoice($property->id, now()->subDay()->toDateString(), 'paid', 500, 'Paid Tenant');

        $response = $this->actingAs($manager)->getJson('/invoices/due-reminders');
        $this->assertCount(0, $response->json('due_invoices'));
    }

    public function test_amount_due_subtracts_paid_payments(): void
    {
        $property = $this->createProperty('Property A', 'PA9');
        $manager = $this->createUser('manager', $property->id);

        $invoice = $this->createInvoice($property->id, now()->subDay()->toDateString(), 'partially_paid', 1000, 'Partial Tenant');
        $tenant = $this->createTenant($property->id, 'partial@example.com');
        $unit = $this->createUnit($property->id, 'PA9-U1');
        Payment::create([
            'property_id' => $property->id,
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'amount' => 400,
            'status' => 'paid',
            'currency' => 'USD',
            'month' => 'Test Period',
        ]);

        $response = $this->actingAs($manager)->getJson('/invoices/due-reminders');
        $due = $response->json('due_invoices');

        $this->assertCount(1, $due);
        $this->assertEquals(600.0, $due[0]['amount_due']);
    }

    public function test_invoice_fully_paid_via_payments_but_stale_status_is_excluded(): void
    {
        $property = $this->createProperty('Property A', 'PA10');
        $manager = $this->createUser('manager', $property->id);

        $invoice = $this->createInvoice($property->id, now()->subDay()->toDateString(), 'unpaid', 1000, 'Fully Paid Tenant');
        $tenant = $this->createTenant($property->id, 'fullypaid@example.com');
        $unit = $this->createUnit($property->id, 'PA10-U1');
        Payment::create([
            'property_id' => $property->id,
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'amount' => 1000,
            'status' => 'paid',
            'currency' => 'USD',
            'month' => 'Test Period',
        ]);

        $response = $this->actingAs($manager)->getJson('/invoices/due-reminders');
        $this->assertCount(0, $response->json('due_invoices'));
    }

    private function createProperty(string $name, string $code): Property
    {
        return Property::create([
            'name' => $name,
            'code' => $code,
            'address' => 'Street 1',
            'city' => 'Dar es Salaam',
            'country' => 'Tanzania',
            'status' => 'active',
            'unit_count' => 0,
            'occupied_units' => 0,
        ]);
    }

    private function createUser(string $role, ?int $propertyId = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'property_id' => $propertyId,
            'must_change_password' => false,
            'status' => 'active',
        ]);
    }

    private function createTenant(int $propertyId, string $email): Tenant
    {
        return Tenant::create([
            'property_id' => $propertyId,
            'name' => 'Test Tenant',
            'first_name' => 'Test',
            'last_name' => 'Tenant',
            'email' => $email,
            'phone' => '0700000000',
            'national_id' => '1234567890',
            'initials' => 'TT',
            'tenant_type' => 'individual',
            'nok_name' => 'Kin Name',
            'nok_phone' => '0700000001',
            'nok_relation' => 'Sibling',
        ]);
    }

    private function createUnit(int $propertyId, string $unitNumber): Unit
    {
        return Unit::create([
            'property_id' => $propertyId,
            'unit_number' => $unitNumber,
            'floor' => '1',
            'type' => 'Office Suite',
            'size_sqft' => 100,
            'size_sqm' => 9.29,
            'rate_per_sqm' => 107.64,
            'service_charge_per_sqm' => 0,
            'currency' => 'USD',
            'rent' => 1000,
            'status' => 'occupied',
            'deposit' => 0,
            'service_charge' => 0,
            'electricity_type' => 'direct',
            'approval_status' => 'approved',
        ]);
    }

    private function createInvoice(int $propertyId, string $dueDate, string $status, float $total, string $tenantName): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-' . uniqid(),
            'type' => 'invoice',
            'property_id' => $propertyId,
            'tenant_name' => $tenantName,
            'tenant_email' => strtolower(str_replace(' ', '.', $tenantName)) . '@example.com',
            'unit_ref' => 'A-101',
            'issued_date' => now()->subDays(10)->toDateString(),
            'due_date' => $dueDate,
            'period' => 'Test Period',
            'status' => $status,
            'currency' => 'USD',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Rental Payment',
            'quantity' => 1,
            'unit_price' => $total,
            'total' => $total,
        ]);

        return $invoice;
    }
}
