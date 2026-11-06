<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperuserNeedsNoApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function proformaPayload(int $leaseId): array
    {
        return [
            'lease_id' => $leaseId, 'type' => 'proforma', 'tenant_name' => 'Tenant One', 'tenant_email' => 'tenant@example.com', 'unit_ref' => 'A-101',
            'issued_date' => '2026-04-30', 'due_date' => '2026-05-05', 'period' => 'Apr 2026',
            'items' => [['description' => 'Rental Payment', 'quantity' => 1, 'unit_price' => 1000]],
        ];
    }

    private function activeLease(int $propertyId): \App\Models\Lease
    {
        $unit = \App\Models\Unit::create(['property_id' => $propertyId, 'unit_number' => 'A-101', 'floor' => 1, 'type' => 'Office', 'size_sqft' => 100, 'rent' => 1000, 'currency' => 'TZS', 'status' => 'occupied', 'approval_status' => 'approved', 'deposit' => 0, 'service_charge' => 0, 'electricity_type' => 'direct']);
        $tenant = \App\Models\Tenant::create(['property_id' => $propertyId, 'name' => 'Tenant One', 'email' => 'tenant@example.com', 'phone' => '+255700000000', 'initials' => 'TO', 'tenant_type' => 'individual']);

        return \App\Models\Lease::create([
            'property_id' => $propertyId, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'duration_months' => 12, 'payment_cycle' => 3, 'monthly_rent' => 1000, 'deposit' => 0, 'status' => 'active', 'currency' => 'TZS', 'vat_rate' => 18,
        ]);
    }

    private function setUpProperty(): array
    {
        $property = Property::create(['name' => 'Sup Tower', 'code' => 'SUP', 'status' => 'active', 'unit_count' => 0, 'occupied_units' => 0, 'country' => 'Tanzania']);
        $superuser = User::factory()->create(['role' => 'superuser', 'status' => 'active', 'must_change_password' => false]);
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'property_id' => $property->id, 'must_change_password' => false]);

        return [$property, $superuser, $manager];
    }

    public function test_a_superusers_proforma_is_approved_at_once_and_a_managers_still_waits(): void
    {
        [$property, $superuser, $manager] = $this->setUpProperty();
        $lease = $this->activeLease($property->id);

        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])
            ->post('/invoices', $this->proformaPayload($lease->id))->assertSessionHas('success', 'Proforma invoice created.');
        $mine = Invoice::firstOrFail();
        $this->assertSame('approved', $mine->approval_status);
        $this->assertNotNull($mine->approval_decided_at);

        $this->actingAs($manager)->post('/invoices', $this->proformaPayload($lease->id));
        $theirs = Invoice::orderByDesc('id')->firstOrFail();
        $this->assertSame('pending_approval', $theirs->approval_status);
    }

    public function test_a_superusers_edit_and_resubmit_of_a_proforma_need_no_approval(): void
    {
        [$property, $superuser, $manager] = $this->setUpProperty();
        $invoice = Invoice::create([
            'property_id' => $property->id, 'invoice_number' => 'PF-1', 'type' => 'proforma', 'status' => 'proforma', 'tenant_name' => 'Tenant One', 'unit_ref' => 'A-101',
            'issued_date' => '2026-04-30', 'due_date' => '2026-05-05', 'currency' => 'TZS', 'approval_status' => 'rejected',
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Rental Payment', 'quantity' => 1, 'unit_price' => 1000, 'total' => 1000]);
        $lease = $this->activeLease($property->id);
        $acting = fn () => $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id]);

        $acting()->post("/invoices/{$invoice->id}/resubmit")->assertSessionHas('success', 'Proforma invoice approved.');
        $this->assertSame('approved', $invoice->fresh()->approval_status);

        $invoice->update(['approval_status' => 'rejected']);
        $acting()->patch("/invoices/{$invoice->id}", $this->proformaPayload($lease->id))->assertSessionHasNoErrors();
        $this->assertSame('approved', $invoice->fresh()->approval_status);
    }

    public function test_an_invoice_without_a_lease_is_refused(): void
    {
        [$property, $superuser, $manager] = $this->setUpProperty();
        $payload = $this->proformaPayload(0);
        unset($payload['lease_id']);

        $this->actingAs($manager)->post('/invoices', $payload)->assertSessionHasErrors(['lease_id' => 'Choose the lease this invoice is for.']);
        $this->assertSame(0, Invoice::count());
    }
}
