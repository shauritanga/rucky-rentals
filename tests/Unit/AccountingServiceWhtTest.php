<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Services\AccountingService;
use App\Support\AccountingAutoPoster;
use Mockery;
use PHPUnit\Framework\TestCase;

class AccountingServiceWhtTest extends TestCase
{
    private function invokeWhtCalculation(
        AccountingService $service,
        float $amountToPost,
        float $paymentAmount,
        float $whtRate,
        Invoice $invoice
    ): float {
        $method = new \ReflectionMethod($service, 'calculateWhtAmountFromInvoice');
        return (float) $method->invoke($service, $amountToPost, $paymentAmount, $whtRate, $invoice);
    }

    private function makeInvoiceWithItems(): Invoice
    {
        $invoice = new Invoice();
        $invoice->setRelation('lease', new Lease(['vat_rate' => 18]));
        $invoice->setRelation('items', collect([
            new InvoiceItem(['item_type' => 'rent', 'description' => 'Monthly rent', 'total' => 1000]),
            new InvoiceItem(['item_type' => 'service_charge', 'description' => 'Service charge', 'total' => 180]),
            new InvoiceItem(['item_type' => 'other', 'description' => 'Electricity recharge', 'total' => 120]),
        ]));

        return $invoice;
    }

    public function test_wht_is_based_on_vat_exclusive_rent_and_service_charge_for_full_payment(): void
    {
        $service = new AccountingService(Mockery::mock(AccountingAutoPoster::class));
        $invoice = $this->makeInvoiceWithItems();

        // Eligible gross = 1,180; VAT(18% inclusive) = 180; eligible net = 1,000; WHT 10% = 100.
        $whtAmount = $this->invokeWhtCalculation($service, 1300, 1300, 10, $invoice);

        $this->assertSame(100.0, $whtAmount);
    }

    public function test_wht_is_prorated_for_partial_payment(): void
    {
        $service = new AccountingService(Mockery::mock(AccountingAutoPoster::class));
        $invoice = $this->makeInvoiceWithItems();

        // Half payment => half eligible net base => 50 WHT.
        $whtAmount = $this->invokeWhtCalculation($service, 650, 650, 10, $invoice);

        $this->assertSame(50.0, $whtAmount);
    }

    public function test_wht_uses_base_currency_amount_for_fx_consistency(): void
    {
        $service = new AccountingService(Mockery::mock(AccountingAutoPoster::class));
        $invoice = $this->makeInvoiceWithItems();

        // Source currency payment: 1,300 @ fx 2,500 => base amount 3,250,000.
        // Eligible net source base: 1,000 => 2,500,000 in base; WHT 10% => 250,000.
        $whtAmount = $this->invokeWhtCalculation($service, 3250000, 1300, 10, $invoice);

        $this->assertSame(250000.0, $whtAmount);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
