<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
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
    public function index(Request $request)
    {
        $propertyId = $this->resolvePropertyId($request);

        [$start, $end, $prevStart, $prevEnd, $periodLabel] = $this->resolvePeriod($request);

        $paymentAmountExpr = 'COALESCE(amount_in_base, amount * COALESCE(exchange_rate, 1))';

        // --- KPIs ---
        $revenue = Payment::query()
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('status', 'paid')
            ->whereBetween('paid_date', [$start->toDateString(), $end->toDateString()])
            ->sum(DB::raw($paymentAmountExpr));

        $prevRevenue = Payment::query()
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('status', 'paid')
            ->whereBetween('paid_date', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->sum(DB::raw($paymentAmountExpr));

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

        $invoices = Invoice::query()
            ->with('items')
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('type', 'invoice')
            ->whereBetween('issued_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $invoicedAmount = $invoices->reduce(function ($sum, $invoice) {
            $lineTotal = (float) $invoice->items->sum('total');
            if (!empty($invoice->total_in_base)) {
                return $sum + (float) $invoice->total_in_base;
            }
            if (($invoice->currency ?? 'TZS') === 'TZS' || empty($invoice->exchange_rate)) {
                return $sum + $lineTotal;
            }
            return $sum + ($lineTotal * (float) $invoice->exchange_rate);
        }, 0.0);

        $paidAgainstInvoices = Payment::query()
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('status', 'paid')
            ->whereNotNull('invoice_id')
            ->whereBetween('paid_date', [$start->toDateString(), $end->toDateString()])
            ->sum(DB::raw($paymentAmountExpr));

        $collectionRate = $invoicedAmount > 0 ? min(100, ($paidAgainstInvoices / $invoicedAmount) * 100) : 0;

        // --- Monthly Revenue ---
        $periodMonths = [];
        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $periodMonths[$cursor->format('Y-m')] = ['month' => $cursor->format('M'), 'value' => 0.0];
            $cursor->addMonth();
        }

        $dateGroupExpr = $this->dateGroupExpr('paid_date');
        $monthRevenue  = Payment::query()
            ->selectRaw("{$dateGroupExpr} as ym")
            ->selectRaw("SUM({$paymentAmountExpr}) as total")
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('status', 'paid')
            ->whereBetween('paid_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        foreach ($monthRevenue as $row) {
            if (isset($periodMonths[$row->ym])) {
                $periodMonths[$row->ym]['value'] = (float) $row->total;
            }
        }

        $monthlyList    = array_values($periodMonths);
        $maxMonth       = max(1, ...array_map(fn($m) => (float) $m['value'], $monthlyList));
        $monthlyRevenue = array_map(function ($m, $index) use ($maxMonth, $monthlyList) {
            return [
                'month'  => $m['month'],
                'value'  => (float) $m['value'],
                'fill'   => round(((float) $m['value'] / $maxMonth) * 100),
                'accent' => $index === (count($monthlyList) - 1),
            ];
        }, $monthlyList, array_keys($monthlyList));

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
        $topUnitRows = Payment::query()
            ->selectRaw('unit_id, SUM(' . $paymentAmountExpr . ') as total')
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('status', 'paid')
            ->whereNotNull('unit_id')
            ->whereBetween('paid_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('unit_id')
            ->orderByDesc('total')
            ->limit(3)
            ->get();

        $topUnits = $topUnitRows->map(function ($row) {
            $unit          = Unit::find($row->unit_id);
            $latestPayment = Payment::with('tenant')
                ->where('unit_id', $row->unit_id)
                ->where('status', 'paid')
                ->orderByDesc('paid_date')
                ->first();
            return [
                'unit'   => $unit ? ($unit->unit_number . ' - ' . ($unit->type ?? 'Unit')) : ('Unit #' . $row->unit_id),
                'meta'   => $unit
                    ? ('Floor ' . ($unit->floor ?? '-') . ' - ' . ($latestPayment?->tenant?->name ?? 'No tenant'))
                    : 'No unit details',
                'amount' => (float) $row->total,
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
            ->with('tenant')
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

            $paid = $payments->sum(fn($p) => (float) ($p->amount_in_base ?? ($p->amount * ($p->exchange_rate ?? 1))));

            $invoiced = $invGroup->reduce(function ($sum, $inv) {
                $lt = (float) $inv->items->sum('total');
                if (!empty($inv->total_in_base)) return $sum + (float) $inv->total_in_base;
                if (($inv->currency ?? 'TZS') !== 'TZS' && !empty($inv->exchange_rate)) {
                    return $sum + ($lt * (float) $inv->exchange_rate);
                }
                return $sum + $lt;
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
                        ->with('tenant')
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
                        $paid     = $pmts->sum(fn($p) => (float) ($p->amount_in_base ?? ($p->amount * ($p->exchange_rate ?? 1))));
                        $invoiced = $invs->reduce(function ($sum, $inv) {
                            $lt = (float) $inv->items->sum('total');
                            if (!empty($inv->total_in_base)) return $sum + (float) $inv->total_in_base;
                            if (($inv->currency ?? 'TZS') !== 'TZS' && !empty($inv->exchange_rate)) {
                                return $sum + ($lt * (float) $inv->exchange_rate);
                            }
                            return $sum + $lt;
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
                        ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
                        ->where('status', 'paid')
                        ->whereBetween('paid_date', [$start->toDateString(), $end->toDateString()])
                        ->sum(DB::raw($paymentAmountExpr));

                    $maintenanceCost = MaintenanceRecord::query()
                        ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
                        ->where('status', 'resolved')
                        ->whereBetween('reported_date', [$start->toDateString(), $end->toDateString()])
                        ->sum('cost');

                    $totalUnits    = Unit::query()->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))->count();
                    $occupiedUnits = Unit::query()->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))->whereIn('status', ['occupied', 'overdue'])->count();

                    fputcsv($out, ['Revenue',          number_format((float) $revenue, 2)]);
                    fputcsv($out, ['Maintenance Cost', number_format((float) $maintenanceCost, 2)]);
                    fputcsv($out, ['NOI',              number_format((float) $revenue - (float) $maintenanceCost, 2)]);
                    fputcsv($out, ['Total Units',      $totalUnits]);
                    fputcsv($out, ['Occupied Units',   $occupiedUnits]);
                    fputcsv($out, ['Occupancy Rate',   round($totalUnits > 0 ? ($occupiedUnits / $totalUnits) * 100 : 0, 1) . '%']);
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
        $user = $request->user();

        if ($user?->role === 'manager') {
            abort_if(empty($user->property_id), 422, 'Manager is not assigned to any property.');
            abort_if(!Property::where('id', $user->property_id)->exists(), 422, 'Assigned property not found.');
            return (int) $user->property_id;
        }

        return $request->filled('property_id') ? (int) $request->input('property_id') : null;
    }
}
