<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\MaintenanceRecord;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private function calculatePaymentWhtTotal(Payment $payment): float
    {
        $rentRate = (float) ($payment->invoice?->lease?->wht_rate ?? 0);
        $serviceChargeRate = (float) ($payment->invoice?->lease?->service_charge_rate ?? 5);
        $rentBase = (float) $payment->breakdown_rent;
        $serviceChargeBase = (float) $payment->breakdown_service_charge;

        if ($rentBase <= 0 && $serviceChargeBase <= 0) {
            return 0.0;
        }

        $fx = ($payment->amount > 0 && !empty($payment->amount_in_base))
            ? (float) $payment->amount_in_base / (float) $payment->amount
            : (float) ($payment->exchange_rate ?? 1);

        return round(
            (($rentBase * $fx) * ($rentRate / 100)) +
            (($serviceChargeBase * $fx) * ($serviceChargeRate / 100)),
            2
        );
    }

    public function index(Request $request)
    {
        $propertyId = $this->resolvePropertyId($request);

        [$start, $end, $prevStart, $prevEnd, $periodLabel] = $this->resolvePeriod($request);

        $paymentAmountExpr = 'COALESCE(amount_in_base, amount * COALESCE(exchange_rate, 1))';
        $paidPayments      = $this->paidPaymentsForRange($propertyId, $start, $end);
        $prevPaidPayments  = $this->paidPaymentsForRange($propertyId, $prevStart, $prevEnd);
        $revenueRows       = $this->buildPaymentRevenueRows($paidPayments);
        $prevRevenueRows   = $this->buildPaymentRevenueRows($prevPaidPayments);

        // --- KPIs ---
        $revenue = round((float) $revenueRows->sum('value'), 2);

        $prevRevenue = round((float) $prevRevenueRows->sum('value'), 2);

        $maintenanceCost = MaintenanceRecord::query()
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('status', 'resolved')
            ->whereBetween('reported_date', [$start->toDateString(), $end->toDateString()])
            ->sum('cost');

        $prevMaintenanceCost = MaintenanceRecord::query()
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('status', 'resolved')
            ->whereBetween('reported_date', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->sum('cost');

        $noi     = (float) $revenue - (float) $maintenanceCost;
        $prevNoi = (float) $prevRevenue - (float) $prevMaintenanceCost;

        $totalUnits = Unit::query()
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->count();

        $occupiedUnits = Unit::query()
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->whereIn('status', ['occupied', 'overdue'])
            ->count();

        $occupancyRate = $totalUnits > 0 ? ($occupiedUnits / $totalUnits) * 100 : 0;

        // Invoiced amount: total value of invoices that have payments recorded in this period.
        // Scoped by payment date (not issued_date) so the collection rate denominator matches
        // the paid numerator — invoices issued in a prior period but paid now are included.
        $invoices = Invoice::query()
            ->with(['items', 'lease:id,vat_rate'])
            ->whereHas('payments', function ($q) use ($start, $end) {
                $q->where('status', 'paid')
                  ->whereBetween('paid_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('type', 'invoice')
            ->get();

        $invoicedAmount = $invoices->reduce(function ($sum, $invoice) {
            $lineItems   = $invoice->items->reject(fn($i) => $i->item_type === 'electricity_vat');
            $elecVat     = (float) $invoice->items->where('item_type', 'electricity_vat')->sum('total');
            $lineTotal   = (float) $lineItems->sum('total');
            $vatRate     = (float) ($invoice->lease?->vat_rate ?? 0);
            $vatAmount   = $elecVat > 0 ? $elecVat : round($lineTotal * $vatRate / 100, 2);
            $grandTotal  = $lineTotal + $vatAmount;

            if (!empty($invoice->total_in_base)) {
                return $sum + (float) $invoice->total_in_base;
            }
            if (($invoice->currency ?? 'TZS') === 'TZS' || empty($invoice->exchange_rate)) {
                return $sum + $grandTotal;
            }
            return $sum + ($grandTotal * (float) $invoice->exchange_rate);
        }, 0.0);

        $paidAgainstInvoices = Payment::query()
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('status', 'paid')
            ->whereNotNull('invoice_id')
            ->whereBetween('paid_date', [$start->toDateString(), $end->toDateString()])
            ->sum(DB::raw($paymentAmountExpr));

        $collectionRate = $invoicedAmount > 0 ? min(100, ($paidAgainstInvoices / $invoicedAmount) * 100) : 0;

        // --- WHT Total (period-scoped, all paid lease-linked payments) ---
        // WHT is calculated from the lease rate on rent/service-charge breakdowns for
        // every paid payment in the period — regardless of wht_confirmed flag, which
        // is a receipt-workflow flag, not a reporting gate.
        $whtPayments = Payment::query()
            ->with(['invoice.lease:id,wht_rate,service_charge_rate'])
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('status', 'paid')
            ->whereBetween('paid_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('invoice_id')
            ->get();

        $whtTotal = $whtPayments->sum(fn($p) => $this->calculatePaymentWhtTotal($p));

        // --- VAT Total (period-scoped by PAYMENT date — VAT is collected when cash received) ---
        // Part A: electricity & generator VAT stored as item_type = 'electricity_vat'
        // Scoped by payments.paid_date so VAT appears in the period the money was received.
        $vatFromElectricity = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('payments', 'payments.invoice_id', '=', 'invoices.id')
            ->when($propertyId !== null, fn($q) => $q->where('invoices.property_id', $propertyId))
            ->where('invoice_items.item_type', 'electricity_vat')
            ->where('invoices.type', 'invoice')
            ->where('payments.status', 'paid')
            ->whereBetween('payments.paid_date', [$start->toDateString(), $end->toDateString()])
            ->sum(DB::raw('invoice_items.total * COALESCE(invoices.exchange_rate, 1)'));

        // Part B: lease VAT on rent/service-charge items — scoped by payments.paid_date
        $leasePaidInvoices = Invoice::query()
            ->with(['items', 'lease:id,vat_rate'])
            ->whereHas('payments', function ($q) use ($start, $end) {
                $q->where('status', 'paid')
                  ->whereBetween('paid_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('type', 'invoice')
            ->whereIn('status', ['paid', 'partially_paid'])
            ->get();

        $vatFromLeases = $leasePaidInvoices->sum(function ($inv) {
            $vatRate = (float) ($inv->lease?->vat_rate ?? 0);
            if ($vatRate <= 0) return 0.0;
            $rentAndSc  = $inv->items
                ->reject(fn($i) => in_array($i->item_type, ['electricity_charge', 'electricity_vat']))
                ->sum('total');
            $itemsTotal = (float) $inv->items->sum('total');
            $fx = ($itemsTotal > 0 && !empty($inv->total_in_base))
                ? (float) $inv->total_in_base / $itemsTotal
                : (float) ($inv->exchange_rate ?? 1);
            return round((float) $rentAndSc * $fx * ($vatRate / 100), 2);
        });

        $vatTotal = round((float) $vatFromElectricity + $vatFromLeases, 2);

        // --- Security Deposits Held (live balance — not period-scoped) ---
        $depositsHeld = Lease::query()
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->whereIn('status', ['active', 'expiring', 'overdue'])
            ->get(['deposit', 'currency'])
            ->sum(function ($lease) {
                $amount   = (float) ($lease->deposit ?? 0);
                $currency = $lease->currency ?? 'TZS';
                if ($currency === 'TZS') return $amount;
                return round($amount * ExchangeRate::getLiveRate($currency, 'TZS'), 2);
            });

        // --- Monthly Revenue ---
        $periodMonths = [];
        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $periodMonths[$cursor->format('Y-m')] = [
                'month'          => $cursor->format('M'),
                'value'          => 0.0,
                'rent'           => 0.0,
                'service_charge' => 0.0,
                'electricity'    => 0.0,
            ];
            $cursor->addMonth();
        }

        foreach ($revenueRows as $row) {
            if (!isset($periodMonths[$row['ym']])) {
                continue;
            }

            $periodMonths[$row['ym']]['value'] += (float) $row['value'];
            $periodMonths[$row['ym']]['rent'] += (float) $row['rent'];
            $periodMonths[$row['ym']]['service_charge'] += (float) $row['service_charge'];
            $periodMonths[$row['ym']]['electricity'] += (float) $row['electricity'];
        }

        foreach ($periodMonths as &$slot) {
            $slot['value']          = round((float) $slot['value'], 2);
            $slot['rent']           = round((float) $slot['rent'], 2);
            $slot['service_charge'] = round((float) $slot['service_charge'], 2);
            $slot['electricity']    = round((float) $slot['electricity'], 2);
        }
        unset($slot);

        $monthlyRevenue = array_map(fn($m) => [
            'month'          => $m['month'],
            'value'          => (float) $m['value'],
            'rent'           => (float) $m['rent'],
            'service_charge' => (float) $m['service_charge'],
            'electricity'    => (float) $m['electricity'],
        ], array_values($periodMonths));

        // --- Expense Breakdown ---
        $expenseColors   = ['var(--amber)', 'var(--accent)', 'var(--green)', 'var(--red)', 'var(--text-muted)'];
        $expenseRows     = MaintenanceRecord::query()
            ->selectRaw('category, SUM(cost) as total')
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('status', 'resolved')
            ->whereBetween('reported_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('cost')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $maxExpense       = max(1, (float) ($expenseRows->max('total') ?? 0));
        $expenseBreakdown = $expenseRows->values()->map(function ($row, $index) use ($expenseColors, $maxExpense) {
            return [
                'label' => $row->category,
                'value' => (float) $row->total,
                'width' => round(((float) $row->total / $maxExpense) * 100),
                'color' => $expenseColors[$index % count($expenseColors)],
            ];
        })->all();

        // --- Top Units ---
        $topUnitRows = $revenueRows
            ->filter(fn ($row) => !empty($row['payment']->unit_id))
            ->groupBy(fn ($row) => $row['payment']->unit_id)
            ->map(fn ($rows, $unitId) => [
                'unit_id' => (int) $unitId,
                'total' => round((float) $rows->sum('value'), 2),
                'latest_payment' => $rows->sortByDesc(fn ($row) => $row['payment']->paid_date)->first()['payment'],
            ])
            ->sortByDesc('total')
            ->take(3)
            ->values();

        $topUnits = $topUnitRows->map(function ($row) {
            $unit          = Unit::find($row['unit_id']);
            $latestPayment = $row['latest_payment'];
            return [
                'unit'   => $unit ? ($unit->unit_number . ' - ' . ($unit->type ?? 'Unit')) : ('Unit #' . $row['unit_id']),
                'meta'   => $unit
                    ? ('Floor ' . ($unit->floor ?? '-') . ' - ' . ($latestPayment?->tenant?->name ?? 'No tenant'))
                    : 'No unit details',
                'amount' => (float) $row['total'],
            ];
        })->all();

        // --- AR Aging ---
        $today          = Carbon::today();
        $unpaidInvoices = Invoice::query()
            ->with('items')
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('type', 'invoice')
            ->whereIn('status', ['unpaid', 'overdue', 'partially_paid'])
            ->whereNotNull('due_date')
            ->get();

        $arAging = [
            ['label' => 'Current (0–30 days)', 'count' => 0, 'amount' => 0.0, 'color' => 'green'],
            ['label' => '31–60 days',           'count' => 0, 'amount' => 0.0, 'color' => 'amber'],
            ['label' => '61–90 days',           'count' => 0, 'amount' => 0.0, 'color' => 'orange'],
            ['label' => '90+ days',             'count' => 0, 'amount' => 0.0, 'color' => 'red'],
        ];

        foreach ($unpaidInvoices as $inv) {
            $daysOverdue = (int) Carbon::parse($inv->due_date)->diffInDays($today, false);
            $lineTotal   = (float) $inv->items->sum('total');
            $amount      = !empty($inv->total_in_base)
                ? (float) $inv->total_in_base
                : (($inv->currency ?? 'TZS') !== 'TZS' && !empty($inv->exchange_rate)
                    ? $lineTotal * (float) $inv->exchange_rate
                    : $lineTotal);

            $bucket = match (true) {
                $daysOverdue <= 30 => 0,
                $daysOverdue <= 60 => 1,
                $daysOverdue <= 90 => 2,
                default            => 3,
            };
            $arAging[$bucket]['count']++;
            $arAging[$bucket]['amount'] += $amount;
        }

        // --- Lease Expiry (next 90 days) ---
        $leaseExpiry = Lease::query()
            ->with(['tenant', 'unit'])
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->whereIn('status', ['active', 'expiring'])
            ->whereNotNull('end_date')
            ->where('end_date', '>=', $today->toDateString())
            ->where('end_date', '<=', $today->copy()->addDays(90)->toDateString())
            ->orderBy('end_date')
            ->get()
            ->map(function ($lease) use ($today) {
                $daysLeft = (int) $today->diffInDays(Carbon::parse($lease->end_date));
                return [
                    'unit'     => $lease->unit
                        ? ($lease->unit->unit_number . ' - ' . ($lease->unit->type ?? 'Unit'))
                        : 'N/A',
                    'tenant'   => $lease->tenant?->name ?? 'N/A',
                    'end_date' => $lease->end_date,
                    'days_left'=> $daysLeft,
                    'status'   => $lease->status,
                ];
            })->all();

        // --- Tenant Payment Summary ---
        $tenantPayments = Payment::query()
            ->with(['tenant', 'invoice.items', 'invoice.lease:id,vat_rate'])
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('status', 'paid')
            ->whereBetween('paid_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('tenant_id')
            ->get()
            ->groupBy('tenant_id');

        $tenantInvoiced = Invoice::query()
            ->with(['items', 'lease.tenant'])
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('type', 'invoice')
            ->whereBetween('issued_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn($inv) => $inv->lease?->tenant_id);

        $tenantIds     = $tenantPayments->keys()->merge($tenantInvoiced->keys())->unique()->filter();
        $tenantSummary = $tenantIds->map(function ($tenantId) use ($tenantPayments, $tenantInvoiced) {
            $payments  = $tenantPayments->get($tenantId, collect());
            $invGroup  = $tenantInvoiced->get($tenantId, collect());
            $tenant    = $payments->first()?->tenant ?? $invGroup->first()?->lease?->tenant;

            $paid = $payments->sum(fn($p) => (float) $this->paymentNetRevenueSummary($p)['value']);

            $invoiced = $invGroup->reduce(function ($sum, $inv) {
                return $sum + $this->invoiceNetRevenueSummary($inv)['value'];
            }, 0.0);

            $lastPayment = $payments->sortByDesc('paid_date')->first();

            return [
                'tenant'       => $tenant?->name ?? 'Unknown',
                'invoiced'     => $invoiced,
                'paid'         => $paid,
                'balance'      => max(0.0, $invoiced - $paid),
                'last_payment' => $lastPayment?->paid_date ?? null,
            ];
        })->sortByDesc('balance')->values()->all();

        // --- Sidebar data ---
        $availablePeriods = $this->buildPeriodOptions();
        $properties       = $request->user()?->role === 'superuser'
            ? Property::select('id', 'name')->orderBy('name')->get()->toArray()
            : [];

        return Inertia::render('Reports/Index', [
            'report' => [
                'period' => [
                    'label'  => $periodLabel,
                    'from'   => $start->toDateString(),
                    'to'     => $end->toDateString(),
                    'preset' => $request->input('period', 'this_quarter'),
                ],
                'kpis' => [
                    'revenue'             => (float) $revenue,
                    'prevRevenue'         => (float) $prevRevenue,
                    'occupancyRate'       => (float) $occupancyRate,
                    'occupiedUnits'       => (int) $occupiedUnits,
                    'totalUnits'          => (int) $totalUnits,
                    'maintenanceCost'     => (float) $maintenanceCost,
                    'prevMaintenanceCost' => (float) $prevMaintenanceCost,
                    'noi'                 => (float) $noi,
                    'prevNoi'             => (float) $prevNoi,
                    'collectionRate'      => (float) $collectionRate,
                    'paidAmount'          => (float) $paidAgainstInvoices,
                    'invoicedAmount'      => (float) $invoicedAmount,
                    'vatTotal'            => (float) $vatTotal,
                    'whtTotal'            => (float) $whtTotal,
                    'depositsHeld'        => (float) $depositsHeld,
                ],
                'monthlyRevenue'   => $monthlyRevenue,
                'expenseBreakdown' => $expenseBreakdown,
                'topUnits'         => $topUnits,
                'arAging'          => $arAging,
                'leaseExpiry'      => $leaseExpiry,
                'tenantSummary'    => $tenantSummary,
            ],
            'availablePeriods' => $availablePeriods,
            'properties'       => $properties,
            'filters'          => [
                'period'      => $request->input('period', 'this_quarter'),
                'from'        => $request->input('from'),
                'to'          => $request->input('to'),
                'property_id' => $request->input('property_id'),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $propertyId = $this->resolvePropertyId($request);
        [$start, $end, , , $periodLabel] = $this->resolvePeriod($request);
        $type = $request->input('type', 'overview');

        $paymentAmountExpr = 'COALESCE(amount_in_base, amount * COALESCE(exchange_rate, 1))';

        $filename = 'report-' . $type . '-' . $start->format('Y-m-d') . '-to-' . $end->format('Y-m-d') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(
            function () use ($propertyId, $start, $end, $type, $paymentAmountExpr, $periodLabel) {
                $out = fopen('php://output', 'w');

                if ($type === 'ar_aging') {
                    fputcsv($out, ['AR Aging Report', 'As of: ' . Carbon::today()->toDateString()]);
                    fputcsv($out, ['Age Bucket', 'Invoice Count', 'Amount (TZS)']);

                    $today          = Carbon::today();
                    $unpaidInvoices = Invoice::query()
                        ->with('items')
                        ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
                        ->where('type', 'invoice')
                        ->whereIn('status', ['unpaid', 'overdue', 'partially_paid'])
                        ->whereNotNull('due_date')
                        ->get();

                    $buckets = [
                        ['label' => 'Current (0-30 days)', 'count' => 0, 'amount' => 0.0],
                        ['label' => '31-60 days',           'count' => 0, 'amount' => 0.0],
                        ['label' => '61-90 days',           'count' => 0, 'amount' => 0.0],
                        ['label' => '90+ days',             'count' => 0, 'amount' => 0.0],
                    ];

                    foreach ($unpaidInvoices as $inv) {
                        $days   = (int) Carbon::parse($inv->due_date)->diffInDays($today, false);
                        $lt     = (float) $inv->items->sum('total');
                        $amount = !empty($inv->total_in_base) ? (float) $inv->total_in_base
                            : (($inv->currency ?? 'TZS') !== 'TZS' && !empty($inv->exchange_rate)
                                ? $lt * (float) $inv->exchange_rate : $lt);
                        $b = match (true) { $days <= 30 => 0, $days <= 60 => 1, $days <= 90 => 2, default => 3 };
                        $buckets[$b]['count']++;
                        $buckets[$b]['amount'] += $amount;
                    }

                    foreach ($buckets as $row) {
                        fputcsv($out, [$row['label'], $row['count'], number_format($row['amount'], 2)]);
                    }

                } elseif ($type === 'leases') {
                    fputcsv($out, ['Lease Expiry Report', 'Leases expiring within 90 days']);
                    fputcsv($out, ['Unit', 'Tenant', 'End Date', 'Days Left', 'Status']);

                    $today  = Carbon::today();
                    $leases = Lease::query()
                        ->with(['tenant', 'unit'])
                        ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
                        ->whereIn('status', ['active', 'expiring'])
                        ->whereNotNull('end_date')
                        ->where('end_date', '>=', $today->toDateString())
                        ->where('end_date', '<=', $today->copy()->addDays(90)->toDateString())
                        ->orderBy('end_date')
                        ->get();

                    foreach ($leases as $lease) {
                        $daysLeft = (int) $today->diffInDays(Carbon::parse($lease->end_date));
                        fputcsv($out, [
                            $lease->unit ? ($lease->unit->unit_number . ' - ' . ($lease->unit->type ?? 'Unit')) : 'N/A',
                            $lease->tenant?->name ?? 'N/A',
                            $lease->end_date,
                            $daysLeft,
                            $lease->status,
                        ]);
                    }

                } elseif ($type === 'tenants') {
                    fputcsv($out, ['Tenant Payment Summary', 'Period: ' . $periodLabel]);
                    fputcsv($out, ['Tenant', 'Invoiced (TZS)', 'Paid (TZS)', 'Balance (TZS)', 'Last Payment Date']);

                    $pmtGroups = Payment::query()
                        ->with(['tenant', 'invoice.items', 'invoice.lease:id,vat_rate'])
                        ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
                        ->where('status', 'paid')
                        ->whereBetween('paid_date', [$start->toDateString(), $end->toDateString()])
                        ->whereNotNull('tenant_id')
                        ->get()
                        ->groupBy('tenant_id');

                    $invGroups = Invoice::query()
                        ->with(['items', 'lease.tenant'])
                        ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
                        ->where('type', 'invoice')
                        ->whereBetween('issued_date', [$start->toDateString(), $end->toDateString()])
                        ->get()
                        ->groupBy(fn($inv) => $inv->lease?->tenant_id);

                    $tenantIds = $pmtGroups->keys()->merge($invGroups->keys())->unique()->filter();
                    foreach ($tenantIds as $tid) {
                        $pmts     = $pmtGroups->get($tid, collect());
                        $invs     = $invGroups->get($tid, collect());
                        $tenant   = $pmts->first()?->tenant ?? $invs->first()?->lease?->tenant;
                        $paid     = $pmts->sum(fn($p) => (float) $this->paymentNetRevenueSummary($p)['value']);
                        $invoiced = $invs->reduce(function ($sum, $inv) {
                            return $sum + $this->invoiceNetRevenueSummary($inv)['value'];
                        }, 0.0);
                        $last = $pmts->sortByDesc('paid_date')->first()?->paid_date ?? '';
                        fputcsv($out, [
                            $tenant?->name ?? 'Unknown',
                            number_format($invoiced, 2),
                            number_format($paid, 2),
                            number_format(max(0, $invoiced - $paid), 2),
                            $last,
                        ]);
                    }

                } else {
                    // Overview
                    fputcsv($out, ['Overview Report', 'Period: ' . $periodLabel]);
                    fputcsv($out, ['Metric', 'Value']);

                    $revenue = Payment::query()
                        ->with(['invoice.items', 'invoice.lease:id,vat_rate'])
                        ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
                        ->where('status', 'paid')
                        ->whereBetween('paid_date', [$start->toDateString(), $end->toDateString()])
                        ->get()
                        ->sum(fn ($payment) => $this->paymentNetRevenueSummary($payment)['value']);

                    $maintenanceCost = MaintenanceRecord::query()
                        ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
                        ->where('status', 'resolved')
                        ->whereBetween('reported_date', [$start->toDateString(), $end->toDateString()])
                        ->sum('cost');

                    $totalUnits    = Unit::query()->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))->count();
                    $occupiedUnits = Unit::query()->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))->whereIn('status', ['occupied', 'overdue'])->count();

                    // VAT export
                    $exportVatElec = DB::table('invoice_items')
                        ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                        ->when($propertyId !== null, fn($q) => $q->where('invoices.property_id', $propertyId))
                        ->where('invoice_items.item_type', 'electricity_vat')
                        ->where('invoices.type', 'invoice')
                        ->whereIn('invoices.status', ['paid', 'partially_paid'])
                        ->whereBetween('invoices.issued_date', [$start->toDateString(), $end->toDateString()])
                        ->sum(DB::raw('invoice_items.total * COALESCE(invoices.exchange_rate, 1)'));

                    $exportLeaseInvoices = Invoice::query()
                        ->with(['items', 'lease:id,vat_rate'])
                        ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
                        ->where('type', 'invoice')
                        ->whereIn('status', ['paid', 'partially_paid'])
                        ->whereBetween('issued_date', [$start->toDateString(), $end->toDateString()])
                        ->get();

                    $exportVatLeases = $exportLeaseInvoices->sum(function ($inv) {
                        $vatRate = (float) ($inv->lease?->vat_rate ?? 0);
                        if ($vatRate <= 0) return 0.0;
                        $rentAndSc  = $inv->items->reject(fn($i) => in_array($i->item_type, ['electricity_charge', 'electricity_vat']))->sum('total');
                        $itemsTotal = (float) $inv->items->sum('total');
                        $fx = ($itemsTotal > 0 && !empty($inv->total_in_base)) ? (float) $inv->total_in_base / $itemsTotal : (float) ($inv->exchange_rate ?? 1);
                        return round((float) $rentAndSc * $fx * ($vatRate / 100), 2);
                    });

                    // WHT export
                    $exportWhtPayments = Payment::query()
                        ->with(['invoice.lease:id,wht_rate,service_charge_rate'])
                        ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
                        ->where('status', 'paid')
                        ->whereBetween('paid_date', [$start->toDateString(), $end->toDateString()])
                        ->whereNotNull('invoice_id')->get();

                    $exportWht = $exportWhtPayments->sum(fn($p) => $this->calculatePaymentWhtTotal($p));

                    // Deposits export
                    $exportDeposits = Lease::query()
                        ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
                        ->whereIn('status', ['active', 'expiring', 'overdue'])
                        ->get(['deposit', 'currency'])
                        ->sum(function ($lease) {
                            $amount   = (float) ($lease->deposit ?? 0);
                            $currency = $lease->currency ?? 'TZS';
                            if ($currency === 'TZS') return $amount;
                            return round($amount * ExchangeRate::getLiveRate($currency, 'TZS'), 2);
                        });

                    fputcsv($out, ['Revenue',                  number_format((float) $revenue, 2)]);
                    fputcsv($out, ['Maintenance Cost',         number_format((float) $maintenanceCost, 2)]);
                    fputcsv($out, ['NOI',                      number_format((float) $revenue - (float) $maintenanceCost, 2)]);
                    fputcsv($out, ['Total Units',              $totalUnits]);
                    fputcsv($out, ['Occupied Units',           $occupiedUnits]);
                    fputcsv($out, ['Occupancy Rate',           round($totalUnits > 0 ? ($occupiedUnits / $totalUnits) * 100 : 0, 1) . '%']);
                    fputcsv($out, ['VAT Collected',            number_format(round((float) $exportVatElec + $exportVatLeases, 2), 2)]);
                    fputcsv($out, ['WHT Withheld',             number_format($exportWht, 2)]);
                    fputcsv($out, ['Security Deposits Held',   number_format($exportDeposits, 2)]);
                }

                fclose($out);
            },
            200,
            $headers
        );
    }

    private function resolvePeriod(Request $request): array
    {
        $now    = Carbon::now();
        $preset = $request->input('period', 'this_quarter');

        if ($preset === 'custom') {
            $from = $request->filled('from')
                ? Carbon::parse($request->input('from'))->startOfDay()
                : $now->copy()->startOfQuarter();
            $to = $request->filled('to')
                ? Carbon::parse($request->input('to'))->endOfDay()
                : $now->copy()->endOfQuarter();
            $prevDiff = (int) $from->diffInDays($to);
            $prevFrom = $from->copy()->subDays($prevDiff + 1)->startOfDay();
            $prevTo   = $from->copy()->subDay()->endOfDay();
            $label    = $from->format('M d') . ' – ' . $to->format('M d, Y');
            return [$from, $to, $prevFrom, $prevTo, $label];
        }

        return match ($preset) {
            'last_quarter' => (function () use ($now) {
                $s  = $now->copy()->subQuarter()->startOfQuarter();
                $e  = $now->copy()->subQuarter()->endOfQuarter();
                $ps = $s->copy()->subQuarter();
                $pe = $e->copy()->subQuarter();
                return [$s, $e, $ps, $pe, 'Q' . $s->quarter . ' ' . $s->year];
            })(),
            'this_year' => (function () use ($now) {
                $s  = $now->copy()->startOfYear();
                $e  = $now->copy()->endOfYear();
                $ps = $s->copy()->subYear();
                $pe = $e->copy()->subYear();
                return [$s, $e, $ps, $pe, 'FY ' . $now->year];
            })(),
            'last_year' => (function () use ($now) {
                $yr = $now->year - 1;
                $s  = Carbon::create($yr, 1, 1)->startOfDay();
                $e  = Carbon::create($yr, 12, 31)->endOfDay();
                $ps = Carbon::create($yr - 1, 1, 1)->startOfDay();
                $pe = Carbon::create($yr - 1, 12, 31)->endOfDay();
                return [$s, $e, $ps, $pe, 'FY ' . $yr];
            })(),
            default => (function () use ($now) { // this_quarter
                $s  = $now->copy()->startOfQuarter();
                $e  = $now->copy()->endOfQuarter();
                $ps = $s->copy()->subQuarter();
                $pe = $e->copy()->subQuarter();
                return [$s, $e, $ps, $pe, 'Q' . $s->quarter . ' ' . $s->year];
            })(),
        };
    }

    private function buildPeriodOptions(): array
    {
        $now = Carbon::now();
        return [
            ['value' => 'this_quarter', 'label' => 'Q' . $now->quarter . ' ' . $now->year . ' (This Quarter)'],
            ['value' => 'last_quarter', 'label' => 'Q' . $now->copy()->subQuarter()->quarter . ' ' . $now->copy()->subQuarter()->year . ' (Last Quarter)'],
            ['value' => 'this_year',    'label' => 'FY ' . $now->year . ' (This Year)'],
            ['value' => 'last_year',    'label' => 'FY ' . ($now->year - 1) . ' (Last Year)'],
            ['value' => 'custom',       'label' => 'Custom Range...'],
        ];
    }

    private function dateGroupExpr(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'mysql'  => "DATE_FORMAT({$column}, '%Y-%m')",
            default  => "TO_CHAR({$column}, 'YYYY-MM')",
        };
    }

    private function resolvePropertyId(Request $request): ?int
    {
        // Manager or superuser in property-view mode — scope to their effective property
        if ($this->shouldScopeToProperty($request)) {
            return $this->effectivePropertyId($request);
        }

        // Superuser not in property-view mode — use query param if provided
        return $request->filled('property_id') ? (int) $request->input('property_id') : null;
    }

    private function paidPaymentsForRange(?int $propertyId, Carbon $start, Carbon $end)
    {
        return Payment::query()
            ->with(['invoice.items', 'invoice.lease:id,vat_rate', 'tenant'])
            ->when($propertyId !== null, fn ($q) => $q->where('property_id', $propertyId))
            ->where('status', 'paid')
            ->whereBetween('paid_date', [$start->toDateString(), $end->toDateString()])
            ->get();
    }

    private function buildPaymentRevenueRows($payments)
    {
        return $payments->map(function (Payment $payment) {
            $summary = $this->paymentNetRevenueSummary($payment);

            return [
                ...$summary,
                'payment' => $payment,
                'ym' => Carbon::parse($payment->paid_date)->format('Y-m'),
            ];
        });
    }

    private function paymentNetRevenueSummary(Payment $payment): array
    {
        $baseAmount = $this->paymentBaseAmount($payment);
        $fx = $this->paymentFx($payment);

        if ($payment->invoice) {
            $invoiceSummary = $this->invoiceNetRevenueSummary($payment->invoice);
            $items = $payment->invoice->items ?? collect();
            $leaseVatBase = $this->leaseVatAmountForInvoice($payment->invoice) * $fx;
            $electricityVatBase = (float) $items
                ->filter(fn ($item) => ($item->item_type ?? null) === 'electricity_vat')
                ->sum('total') * $fx;

            $totalDueBase = $invoiceSummary['value'] + $leaseVatBase + $electricityVatBase;
            $ratio = $totalDueBase > 0 ? min(1, $baseAmount / $totalDueBase) : 0.0;

            $rent = round($invoiceSummary['rent'] * $ratio, 2);
            $serviceCharge = round($invoiceSummary['service_charge'] * $ratio, 2);
            $electricity = round($invoiceSummary['electricity'] * $ratio, 2);

            return [
                'rent' => $rent,
                'service_charge' => $serviceCharge,
                'electricity' => $electricity,
                'value' => round($rent + $serviceCharge + $electricity, 2),
            ];
        }

        $vatRate = $this->cashBasisVatRateForPayment($payment);
        $netAmount = $vatRate > 0
            ? round($baseAmount / (1 + ($vatRate / 100)), 2)
            : round($baseAmount, 2);

        return [
            'rent' => $netAmount,
            'service_charge' => 0.0,
            'electricity' => 0.0,
            'value' => $netAmount,
        ];
    }

    private function invoiceNetRevenueSummary(Invoice $invoice): array
    {
        $fx = $this->invoiceFx($invoice);
        $items = $invoice->items ?? collect();

        $electricityChargeItems = $items->filter(
            fn ($item) => in_array($item->item_type ?? null, ['electricity_charge', 'electricity'], true)
        );
        $electricityVatItems = $items->filter(fn ($item) => ($item->item_type ?? null) === 'electricity_vat');
        $nonElectricityItems = $items->reject(
            fn ($item) => in_array($item->item_type ?? null, ['electricity_charge', 'electricity', 'electricity_vat'], true)
        );

        $serviceChargeItems = $nonElectricityItems->filter(
            fn ($item) => ($item->item_type ?? null) === 'service_charge'
                || (($item->item_type ?? 'other') === 'other' && stripos($item->description ?? '', 'service charge') !== false)
        );

        $rentItems = $nonElectricityItems->reject(
            fn ($item) => ($item->item_type ?? null) === 'service_charge'
                || (($item->item_type ?? 'other') === 'other' && stripos($item->description ?? '', 'service charge') !== false)
        );

        $rent = round((float) $rentItems->sum('total') * $fx, 2);
        $serviceCharge = round((float) $serviceChargeItems->sum('total') * $fx, 2);
        $electricityCharge = round((float) $electricityChargeItems->sum('total') * $fx, 2);
        $electricity = $electricityVatItems->isNotEmpty()
            ? $electricityCharge
            : round($this->normalizeLegacyElectricityNetAmount($electricityChargeItems, $electricityCharge), 2);

        return [
            'rent' => $rent,
            'service_charge' => $serviceCharge,
            'electricity' => $electricity,
            'value' => round($rent + $serviceCharge + $electricity, 2),
        ];
    }

    private function paymentNetElectricityBase(Payment $payment, float $fx): float
    {
        $gross = round((float) $payment->breakdown_electricity * $fx, 2);
        if ($gross <= 0) {
            return 0.0;
        }

        $items = $payment->invoice?->items ?? collect();
        $electricityChargeItems = $items->filter(
            fn ($item) => in_array($item->item_type ?? null, ['electricity_charge', 'electricity'], true)
        );
        $electricityVat = (float) $items
            ->filter(fn ($item) => ($item->item_type ?? null) === 'electricity_vat')
            ->sum('total');

        if ($electricityVat > 0) {
            $electricityCharge = (float) $electricityChargeItems->sum('total');
            $electricityGross = $electricityCharge + $electricityVat;

            if ($electricityGross > 0) {
                return round($gross * ($electricityCharge / $electricityGross), 2);
            }
        }

        return round($this->normalizeLegacyElectricityNetAmount($electricityChargeItems, $gross), 2);
    }

    private function normalizeLegacyElectricityNetAmount($electricityChargeItems, float $gross): float
    {
        if ($gross <= 0) {
            return 0.0;
        }

        $hasLegacyGrossSubmeterLine = $electricityChargeItems->contains(
            fn ($item) => stripos((string) ($item->description ?? ''), 'Electricity — Submeter Sale') !== false
        );

        if ($hasLegacyGrossSubmeterLine) {
            return round($gross / 1.18, 2);
        }

        return round($gross, 2);
    }

    private function paymentBaseAmount(Payment $payment): float
    {
        if ($payment->amount_in_base !== null) {
            return (float) $payment->amount_in_base;
        }

        return round((float) $payment->amount * (float) ($payment->exchange_rate ?? 1), 2);
    }

    private function paymentFx(Payment $payment): float
    {
        $amount = (float) $payment->amount;
        if ($amount > 0 && $payment->amount_in_base !== null) {
            return (float) $payment->amount_in_base / $amount;
        }

        return (float) ($payment->exchange_rate ?? 1);
    }

    private function invoiceFx(Invoice $invoice): float
    {
        return (float) ($invoice->exchange_rate ?? 1);
    }

    private function leaseVatAmountForInvoice(Invoice $invoice): float
    {
        $vatRate = (float) ($invoice->lease?->vat_rate ?? 0);
        if ($vatRate <= 0) {
            return 0.0;
        }

        $items = $invoice->items ?? collect();
        $leaseNet = (float) $items
            ->reject(fn ($item) => in_array($item->item_type ?? null, ['electricity_charge', 'electricity', 'electricity_vat'], true))
            ->sum('total');

        return round($leaseNet * ($vatRate / 100), 2);
    }

    private function cashBasisVatRateForPayment(Payment $payment): float
    {
        if (!empty($payment->invoice?->lease?->vat_rate)) {
            return (float) $payment->invoice->lease->vat_rate;
        }

        if ($payment->unit_id) {
            return (float) (Lease::query()
                ->where('unit_id', $payment->unit_id)
                ->whereIn('status', ['active', 'expiring', 'overdue'])
                ->value('vat_rate') ?? 18);
        }

        return 18.0;
    }
}
