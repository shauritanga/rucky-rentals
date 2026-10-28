<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\LeaseInstallment;
use Carbon\Carbon;

class LeaseBillingCloser
{
    /**
     * Drop billing that has not fallen due when a lease is terminated:
     * future unpaid installments and unpaid, not-yet-due proformas.
     * Already-due items and anything with payments are left as arrears.
     */
    public function closeFutureBilling(Lease $lease): void
    {
        $today = Carbon::today()->toDateString();

        LeaseInstallment::where('lease_id', $lease->id)
            ->whereNull('invoice_id')
            ->where('paid_amount', '<=', 0)
            ->whereDate('due_date', '>', $today)
            ->delete();

        Invoice::where('lease_id', $lease->id)
            ->where('type', 'proforma')
            ->whereIn('status', ['draft', 'proforma'])
            ->whereDate('due_date', '>', $today)
            ->whereDoesntHave('payments')
            ->get()
            ->each(function (Invoice $invoice) {
                LeaseInstallment::where('invoice_id', $invoice->id)->update(['invoice_id' => null]);
                $invoice->items()->delete();
                $invoice->delete();
            });
    }
}
