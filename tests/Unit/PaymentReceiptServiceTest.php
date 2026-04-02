<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\PaymentReceiptService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentReceiptServiceTest extends TestCase
{
    public function test_builds_pro_rated_breakdown_from_invoice_items(): void
    {
        $service = new PaymentReceiptService();
        $invoice = new Invoice();
        $invoice->setRelation('items', collect([
            new InvoiceItem(['item_type' => 'rent', 'description' => 'Rent', 'total' => 1000]),
            new InvoiceItem(['item_type' => 'service_charge', 'description' => 'Service charge', 'total' => 200]),
            new InvoiceItem(['item_type' => 'electricity', 'description' => 'Electricity', 'total' => 300]),
        ]));

        $breakdown = $service->buildPaymentBreakdown($invoice, 750); // 50% of 1500

        $this->assertSame(500.0, $breakdown['rent']);
        $this->assertSame(100.0, $breakdown['service_charge']);
        $this->assertSame(150.0, $breakdown['electricity']);
        $this->assertTrue($breakdown['has_lease_related']);
        $this->assertFalse($breakdown['is_electricity_only']);
    }

    public function test_allows_electricity_only_receipt_without_wht_confirmation(): void
    {
        $service = new PaymentReceiptService();

        $service->assertReceiptEligibility(
            issueReceipt: true,
            whtConfirmed: false,
            hasLeaseRelatedCharges: false,
            isPartialPayment: false
        );

        $this->assertTrue(true);
    }

    public function test_blocks_receipt_when_wht_missing_for_lease_charges(): void
    {
        $service = new PaymentReceiptService();

        $this->expectException(ValidationException::class);
        $service->assertReceiptEligibility(
            issueReceipt: true,
            whtConfirmed: false,
            hasLeaseRelatedCharges: true,
            isPartialPayment: false
        );
    }

    public function test_blocks_receipt_for_partial_payment_even_with_wht_confirmation(): void
    {
        $service = new PaymentReceiptService();

        $this->expectException(ValidationException::class);
        $service->assertReceiptEligibility(
            issueReceipt: true,
            whtConfirmed: true,
            hasLeaseRelatedCharges: true,
            isPartialPayment: true
        );
    }
}
