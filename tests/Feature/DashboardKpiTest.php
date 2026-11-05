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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardKpiTest extends TestCase
{
    use RefreshDatabase;

    private function setUpProperty(): array
    {
        $property = Property::create(['name' => 'KPI Tower', 'code' => 'KPI', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0, 'country' => 'Tanzania']);
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'property_id' => $property->id, 'must_change_password' => false]);
        $unit = Unit::create([
            'property_id' => $property->id, 'unit_number' => 'K1', 'floor' => 1, 'type' => 'Office', 'size_sqft' => 100, 'rent' => 1000, 'currency' => 'TZS',
            'status' => 'occupied', 'approval_status' => 'approved', 'deposit' => 0, 'service_charge' => 0, 'electricity_type' => 'direct',
        ]);
        $unit->forceFill(['created_at' => now()->subMonths(4)])->save();
        $tenant = Tenant::create(['property_id' => $property->id, 'name' => 'KPI Tenant', 'email' => 'k@example.com', 'phone' => '+255700000000', 'initials' => 'KT', 'tenant_type' => 'individual']);

        return [$property, $manager, $unit, $tenant];
    }

    private function lease(Property $property, Unit $unit, Tenant $tenant, string $status = 'active', array $extra = []): Lease
    {
        return Lease::create(array_merge([
            'property_id' => $property->id, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->addMonths(9)->toDateString(), 'monthly_rent' => 1000, 'deposit' => 0, 'status' => $status, 'currency' => 'TZS',
            'payment_cycle' => 3, 'duration_months' => 12, 'vat_rate' => 18,
        ], $extra));
    }

    private function invoice(Property $property, ?Lease $lease, string $type, string $status, string $due, float $amount = 1000, string $approval = 'approved'): Invoice
    {
        $invoice = Invoice::create([
            'property_id' => $property->id, 'lease_id' => $lease?->id, 'invoice_number' => 'K/' . uniqid(), 'tenant_name' => 'KPI Tenant', 'unit_ref' => 'K1',
            'type' => $type, 'status' => $status, 'due_date' => $due, 'issued_date' => now()->subDays(30)->toDateString(), 'currency' => 'TZS', 'approval_status' => $approval,
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Rent', 'item_type' => 'rent', 'quantity' => 1, 'unit_price' => $amount, 'total' => $amount]);

        return $invoice;
    }

    public function test_the_overdue_balance_includes_vat_and_is_cleared_by_a_payment_that_covers_it(): void
    {
        [$property, $manager, $unit, $tenant] = $this->setUpProperty();
        $lease = $this->lease($property, $unit, $tenant);
        $invoice = $this->invoice($property, $lease, 'invoice', 'unpaid', now()->subDays(10)->toDateString(), 1000);

        $this->actingAs($manager)->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p->where('stats.overdueBalance', 1180)->where('stats.overdueUnits', 1));

        Payment::create(['property_id' => $property->id, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'invoice_id' => $invoice->id, 'amount' => 1180, 'currency' => 'TZS', 'status' => 'paid', 'month' => 'x', 'paid_date' => now()->toDateString()]);

        $this->actingAs($manager)->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p->where('stats.overdueBalance', 0));
    }

    public function test_an_approved_overdue_proforma_counts_like_in_the_unpaid_invoices_report(): void
    {
        [$property, $manager, $unit, $tenant] = $this->setUpProperty();
        $lease = $this->lease($property, $unit, $tenant);
        $this->invoice($property, $lease, 'proforma', 'proforma', now()->subDays(5)->toDateString(), 1000, 'approved');
        $this->invoice($property, $lease, 'proforma', 'proforma', now()->subDays(5)->toDateString(), 5000, 'pending');

        $this->actingAs($manager)->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p->where('stats.overdueBalance', 1180));
    }

    public function test_occupancy_change_counts_units_not_leases_and_ignores_pending_leases(): void
    {
        [$property, $manager, $unit, $tenant] = $this->setUpProperty();
        $this->lease($property, $unit, $tenant);
        $other = Tenant::create(['property_id' => $property->id, 'name' => 'Second', 'email' => 's@example.com', 'phone' => '+255700000001', 'initials' => 'SE', 'tenant_type' => 'individual']);
        $this->lease($property, $unit, $other);                       // co-tenant on the same unit
        $this->lease($property, $unit, $other, 'pending_accountant'); // not a real lease yet

        // One unit, occupied now and last month: 100% → 100%, no change (not -100 from counting three leases).
        $this->actingAs($manager)->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p->where('stats.occupancyDelta', 0));
    }

    public function test_upcoming_rent_lists_part_paid_invoices_and_hides_settled_ones(): void
    {
        [$property, $manager, $unit, $tenant] = $this->setUpProperty();
        $lease = $this->lease($property, $unit, $tenant);
        $part = $this->invoice($property, $lease, 'invoice', 'partially_paid', now()->addDays(5)->toDateString(), 1000);
        Payment::create(['property_id' => $property->id, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'invoice_id' => $part->id, 'amount' => 500, 'currency' => 'TZS', 'status' => 'paid', 'month' => 'x', 'paid_date' => now()->toDateString()]);
        $settled = $this->invoice($property, $lease, 'invoice', 'unpaid', now()->addDays(6)->toDateString(), 1000);
        Payment::create(['property_id' => $property->id, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'invoice_id' => $settled->id, 'amount' => 1180, 'currency' => 'TZS', 'status' => 'paid', 'month' => 'x', 'paid_date' => now()->toDateString()]);

        // Only the part-paid invoice (still owing) is listed; the fully paid one is not.
        $this->actingAs($manager)->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p
            ->has('upcomingEvents', 1, fn (Assert $e) => $e->where('type', 'rent')->where('title', 'Rent due – K1')->etc()));
    }
}
