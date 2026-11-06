<?php

use App\Models\Invoice;
use App\Services\TenantCreditService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Overpayments that already exist become credit rows, so nobody's advance payment is lost when credit goes live. */
    public function up(): void
    {
        $service = app(TenantCreditService::class);

        Invoice::with(['items', 'lease:id,vat_rate,tenant_id'])
            ->whereIn('id', DB::table('payments')->where('status', 'paid')->whereNotNull('invoice_id')->select('invoice_id'))
            ->orderBy('id')
            ->each(function (Invoice $invoice) use ($service) {
                $service->syncOverpayment($invoice);
            });
    }

    public function down(): void
    {
        DB::table('tenant_credits')->where('type', 'overpayment')->delete();
    }
};
