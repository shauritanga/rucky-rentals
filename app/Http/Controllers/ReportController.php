<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\MaintenanceRecord;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $propertyId = $this->resolvePropertyId($request);

        $now = Carbon::now();
        $start = $now->copy()->startOfQuarter();
        $end = $now->copy()->endOfQuarter();
        $prevStart = $start->copy()->subQuarter();
        $prevEnd = $end->copy()->subQuarter();

        $paymentAmountExpr = 'COALESCE(amount_in_base, amount * COALESCE(exchange_rate, 1))';

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

        $noi = (float) $revenue - (float) $maintenanceCost;
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

        $periodMonths = [];
        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $periodMonths[$cursor->format('Y-m')] = [
                'month' => $cursor->format('M'),
                'value' => 0.0,
            ];
            $cursor->addMonth();
        }

        $monthRevenue = Payment::query()
            ->selectRaw("TO_CHAR(paid_date, 'YYYY-MM') as ym")
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

        $monthlyList = array_values($periodMonths);
        $maxMonth = max(1, ...array_map(fn($m) => (float) $m['value'], $monthlyList));
        $monthlyRevenue = array_map(function ($m, $index) use ($maxMonth, $monthlyList) {
            return [
                'month' => $m['month'],
                'value' => (float) $m['value'],
                'fill' => round(((float) $m['value'] / $maxMonth) * 100),
                'accent' => $index === (count($monthlyList) - 1),
            ];
        }, $monthlyList, array_keys($monthlyList));

        $expenseColors = ['var(--amber)', 'var(--accent)', 'var(--green)', 'var(--red)', 'var(--text-muted)'];
        $expenseRows = MaintenanceRecord::query()
            ->selectRaw('category, SUM(cost) as total')
            ->when($propertyId !== null, fn($q) => $q->where('property_id', $propertyId))
            ->where('status', 'resolved')
            ->whereBetween('reported_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('cost')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $maxExpense = max(1, (float) ($expenseRows->max('total') ?? 0));
        $expenseBreakdown = $expenseRows->values()->map(function ($row, $index) use ($expenseColors, $maxExpense) {
            return [
                'label' => $row->category,
                'value' => (float) $row->total,
                'width' => round(((float) $row->total / $maxExpense) * 100),
                'color' => $expenseColors[$index % count($expenseColors)],
            ];
        })->all();

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
            $unit = Unit::find($row->unit_id);
            $latestPayment = Payment::with('tenant')
                ->where('unit_id', $row->unit_id)
                ->where('status', 'paid')
                ->orderByDesc('paid_date')
                ->first();

            return [
                'unit' => $unit ? ($unit->unit_number . ' - ' . ($unit->type ?? 'Unit')) : ('Unit #' . $row->unit_id),
                'meta' => $unit
                    ? ('Floor ' . ($unit->floor ?? '-') . ' - ' . ($latestPayment?->tenant?->name ?? 'No tenant'))
                    : 'No unit details',
                'amount' => (float) $row->total,
            ];
        })->all();

        return Inertia::render('Reports/Index', [
            'report' => [
                'period' => [
                    'label' => 'Q' . $start->quarter . ' ' . $start->year,
                    'from' => $start->toDateString(),
                    'to' => $end->toDateString(),
                ],
                'kpis' => [
                    'revenue' => (float) $revenue,
                    'prevRevenue' => (float) $prevRevenue,
                    'occupancyRate' => (float) $occupancyRate,
                    'occupiedUnits' => (int) $occupiedUnits,
                    'totalUnits' => (int) $totalUnits,
                    'maintenanceCost' => (float) $maintenanceCost,
                    'prevMaintenanceCost' => (float) $prevMaintenanceCost,
                    'noi' => (float) $noi,
                    'prevNoi' => (float) $prevNoi,
                    'collectionRate' => (float) $collectionRate,
                    'paidAmount' => (float) $paidAgainstInvoices,
                    'invoicedAmount' => (float) $invoicedAmount,
                ],
                'monthlyRevenue' => $monthlyRevenue,
                'expenseBreakdown' => $expenseBreakdown,
                'topUnits' => $topUnits,
            ],
        ]);
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
