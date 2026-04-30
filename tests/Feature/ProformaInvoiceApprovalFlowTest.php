<?php

namespace Tests\Feature;

use App\Mail\ProformaInvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProformaInvoiceApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_proforma_does_not_email_the_tenant_and_starts_pending_approval(): void
    {
        Mail::fake();

        $property = $this->createProperty();
        $manager = $this->createManager($property->id);

        $response = $this->actingAs($manager)->post('/invoices', [
            'type' => 'proforma',
            'tenant_name' => 'Tenant One',
            'tenant_email' => 'tenant@example.com',
            'unit_ref' => 'A-101',
            'issued_date' => '2026-04-30',
            'due_date' => '2026-05-05',
            'period' => 'Apr 2026',
            'notes' => 'Bank details here',
            'items' => [
                [
                    'description' => 'Rental Payment',
                    'quantity' => 1,
                    'unit_price' => 1000,
                ],
            ],
        ]);

        $response->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();

        $this->assertSame('proforma', $invoice->type);
        $this->assertSame('proforma', $invoice->status);
        $this->assertSame('pending_approval', $invoice->approval_status);
        $this->assertNull($invoice->sent_to_tenant_at);
        Mail::assertNothingSent();
    }

    public function test_proforma_must_be_approved_before_it_can_be_sent(): void
    {
        Mail::fake();

        $property = $this->createProperty();
        $manager = $this->createManager($property->id);
        $superuser = User::factory()->create([
            'role' => 'superuser',
            'must_change_password' => false,
            'status' => 'active',
        ]);

        $invoice = $this->createProformaInvoice($property->id, $manager->id, 'pending_approval');

        $blocked = $this->actingAs($manager)->post("/invoices/{$invoice->id}/send");
        $blocked->assertSessionHas('error');
        Mail::assertNothingSent();

        $this->actingAs($superuser)->post("/superuser/invoices/{$invoice->id}/approve", [
            'message' => 'Looks correct.',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame('approved', $invoice->approval_status);

        $sent = $this->actingAs($manager)->post("/invoices/{$invoice->id}/send");
        $sent->assertSessionHas('success');

        Mail::assertSent(ProformaInvoiceMail::class, 1);
        $this->assertNotNull($invoice->fresh()->sent_to_tenant_at);
    }

    public function test_sent_proforma_cannot_be_edited(): void
    {
        $property = $this->createProperty();
        $manager = $this->createManager($property->id);
        $invoice = $this->createProformaInvoice($property->id, $manager->id, 'approved', now()->toDateTimeString());

        $response = $this->actingAs($manager)->patch("/invoices/{$invoice->id}", [
            'tenant_name' => 'Edited Tenant',
            'tenant_email' => 'edited@example.com',
            'unit_ref' => 'A-101',
            'issued_date' => '2026-04-30',
            'due_date' => '2026-05-10',
            'period' => 'Apr 2026',
            'notes' => 'Edited note',
            'items' => [
                [
                    'description' => 'Rental Payment',
                    'quantity' => 1,
                    'unit_price' => 1200,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertSame('Tenant One', $invoice->fresh()->tenant_name);
    }

    private function createProperty(): Property
    {
        return Property::create([
            'name' => 'Test Property',
            'code' => 'TP01',
            'address' => 'Street 1',
            'city' => 'Dar es Salaam',
            'country' => 'Tanzania',
            'status' => 'active',
            'unit_count' => 0,
            'occupied_units' => 0,
        ]);
    }

    private function createManager(int $propertyId): User
    {
        return User::factory()->create([
            'role' => 'manager',
            'property_id' => $propertyId,
            'must_change_password' => false,
            'status' => 'active',
        ]);
    }

    private function createProformaInvoice(int $propertyId, int $requestedByUserId, string $approvalStatus, ?string $sentAt = null): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => 'PF-TEST-001',
            'type' => 'proforma',
            'property_id' => $propertyId,
            'requested_by_user_id' => $requestedByUserId,
            'tenant_name' => 'Tenant One',
            'tenant_email' => 'tenant@example.com',
            'unit_ref' => 'A-101',
            'issued_date' => '2026-04-30',
            'due_date' => '2026-05-05',
            'period' => 'Apr 2026',
            'status' => 'proforma',
            'approval_status' => $approvalStatus,
            'approval_requested_at' => now(),
            'approval_decided_at' => $approvalStatus === 'approved' ? now() : null,
            'sent_to_tenant_at' => $sentAt,
            'notes' => 'Bank details here',
            'currency' => 'USD',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Rental Payment',
            'quantity' => 1,
            'unit_price' => 1000,
            'total' => 1000,
        ]);

        return $invoice;
    }
}
