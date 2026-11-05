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

    private function proformaPayload(): array
    {
        return [
            'type' => 'proforma', 'tenant_name' => 'Tenant One', 'tenant_email' => 'tenant@example.com', 'unit_ref' => 'A-101',
            'issued_date' => '2026-04-30', 'due_date' => '2026-05-05', 'period' => 'Apr 2026',
            'items' => [['description' => 'Rental Payment', 'quantity' => 1, 'unit_price' => 1000]],
        ];
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

        $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id])
            ->post('/invoices', $this->proformaPayload())->assertSessionHas('success', 'Proforma invoice created.');
        $mine = Invoice::firstOrFail();
        $this->assertSame('approved', $mine->approval_status);
        $this->assertNotNull($mine->approval_decided_at);

        $this->actingAs($manager)->post('/invoices', $this->proformaPayload());
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
        $acting = fn () => $this->actingAs($superuser)->withSession(['superuser_viewing_property_id' => $property->id]);

        $acting()->post("/invoices/{$invoice->id}/resubmit")->assertSessionHas('success', 'Proforma invoice approved.');
        $this->assertSame('approved', $invoice->fresh()->approval_status);

        $invoice->update(['approval_status' => 'rejected']);
        $acting()->patch("/invoices/{$invoice->id}", $this->proformaPayload())->assertSessionHasNoErrors();
        $this->assertSame('approved', $invoice->fresh()->approval_status);
    }
}
