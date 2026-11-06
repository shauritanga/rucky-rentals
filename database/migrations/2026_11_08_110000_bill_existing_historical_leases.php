<?php

use App\Http\Controllers\LeaseController;
use App\Models\Lease;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Historical leases recorded before they followed the normal flow have no payment schedule or deposit proforma.
     * Give them both, so their past periods can be invoiced and paid like any other lease.
     */
    public function up(): void
    {
        $leases = app(LeaseController::class);

        Lease::where('status', 'terminated')
            ->whereDoesntHave('installments')
            ->whereNotIn('id', DB::table('invoices')->whereNotNull('lease_id')->select('lease_id'))
            ->orderBy('id')
            ->each(function (Lease $lease) use ($leases) {
                try {
                    $leases->ensureInstallmentsGenerated($lease->fresh());
                    $leases->issueDepositInvoice($lease->fresh());
                } catch (\Throwable $e) {
                    report($e); // a lease that cannot be billed (for example a USD deposit with no rate) is left as it was
                }
            });
    }

    public function down(): void
    {
        // The generated schedule and proforma are ordinary records; nothing to undo automatically.
    }
};
