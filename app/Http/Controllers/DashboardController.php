<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MaintenanceRecord;
use App\Models\Payment;
use App\Models\ScheduledMaintenance;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\MockRentalData;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Redirect superuser to their panel unless they're in property-view mode
        if (Auth::check() && Auth::user()->role === 'superuser' && !session('superuser_viewing_property_id')) {
            return redirect()->route('superuser.index');
        }

        $user = Auth::user();

        // Don't serve mock data when superuser is viewing a real property
        if (MockRentalData::shouldUse() && $user?->role !== 'manager' && !session('superuser_viewing_property_id')) {
            return Inertia::render('Dashboard', MockRentalData::dashboard());
        }

        $unitsBaseQuery = Unit::query()->approved();
        if ($this->shouldScopeToProperty($request)) {
            $propertyId = $this->effectivePropertyId($request);
            if ($propertyId === null) {
                $unitsBaseQuery->whereRaw('1 = 0');
            } else {
                $unitsBaseQuery->where('property_id', $propertyId);
            }
        }

        $unitIdsQuery = (clone $unitsBaseQuery)->select('id');

        $totalUnits    = (clone $unitsBaseQuery)->count();
        $occupiedUnits = (clone $unitsBaseQuery)->whereIn('status', ['occupied', 'overdue'])->count();
        $vacantUnits   = (clone $unitsBaseQuery)->where('status', 'vacant')->count();

        // ── Last-month deltas ─────────────────────────────────────────────────
        $thisMonthStart = Carbon::now()->startOfMonth();
        $lastMonthStart = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd   = Carbon::now()->subMonthNoOverflow()->endOfMonth();

        // Units added this calendar month
        $totalUnitsDelta = (clone $unitsBaseQuery)
            ->where('created_at', '>=', $thisMonthStart)
            ->count();

        // Leases running during last month (date-range based, status-agnostic)
        $lastMonthOccupied = Lease::whereIn('unit_id', $unitIdsQuery)
            ->where('start_date', '<=', $lastMonthEnd)
            ->where(fn ($q) => $q->where('end_date', '>=', $lastMonthStart)->orWhereNull('end_date'))
            ->count();

        $lastMonthTotalUnits = (clone $unitsBaseQuery)
            ->where('created_at', '<=', $lastMonthEnd)
            ->count();

        $currentOccupancyPct   = $totalUnits > 0 ? (int) round($occupiedUnits / $totalUnits * 100) : 0;
        $lastMonthOccupancyPct = $lastMonthTotalUnits > 0 ? (int) round($lastMonthOccupied / $lastMonthTotalUnits * 100) : 0;
        $occupancyDelta        = $currentOccupancyPct - $lastMonthOccupancyPct;

        // Revenue = TZS actually collected in the month (payments are always TZS).
        $collectedBetween = fn ($from, $to) => (float) Payment::where('status', 'paid')
            ->whereIn('unit_id', $unitIdsQuery)
            ->whereRaw('COALESCE(paid_date, DATE(created_at)) BETWEEN ? AND ?', [$from->toDateString(), $to->toDateString()])
            ->sum('amount_in_base');

        $monthlyRevenueTzs   = $collectedBetween($thisMonthStart, Carbon::now()->endOfMonth());
        $lastMonthRevenueTzs = $collectedBetween($lastMonthStart, $lastMonthEnd);

        $revenueDelta = $monthlyRevenueTzs - $lastMonthRevenueTzs;

        // ── Overdue Invoices & Balance ─────────────────────────────────────────
        // Calculate real overdue rent from unpaid/overdue invoices past their due date.
        $today = Carbon::today();
        $overdueInvoicesQuery = Invoice::with(['items', 'lease'])
            ->whereNotIn('status', ['draft', 'proforma', 'paid'])
            ->where(function ($q) use ($today) {
                $q->where('status', 'overdue')
                  ->orWhere('due_date', '<', $today);
            });

        if ($this->shouldScopeToProperty($request)) {
            $effectivePropId = $this->effectivePropertyId($request);
            if ($effectivePropId !== null) {
                $overdueInvoicesQuery->where(function ($q) use ($effectivePropId, $unitIdsQuery) {
                    $q->where('property_id', $effectivePropId)
                      ->orWhereIn('lease_id', Lease::whereIn('unit_id', $unitIdsQuery)->select('id'));
                });
            } else {
                $overdueInvoicesQuery->whereRaw('1 = 0');
            }
        }

        $overdueInvoices = $overdueInvoicesQuery->get();

        $paidByInvoice = Payment::whereIn('invoice_id', $overdueInvoices->pluck('id'))
            ->where('status', 'paid')
            ->selectRaw('invoice_id, SUM(amount) as paid_sum')
            ->groupBy('invoice_id')
            ->pluck('paid_sum', 'invoice_id');

        $overdueBalanceTzs = 0.0;
        $overdueUnitsMap = [];

        foreach ($overdueInvoices as $inv) {
            $gross = (float) $inv->items->sum('total');
            $paid = (float) ($paidByInvoice[$inv->id] ?? 0);
            $outstanding = max(0, round($gross - $paid, 2));
            if ($outstanding <= 0) {
                continue;
            }

            $overdueBalanceTzs += $outstanding;

            $unitKey = $inv->unit_ref ?: ($inv->lease?->unit_id ? 'u-' . $inv->lease->unit_id : 'inv-' . $inv->id);
            $overdueUnitsMap[$unitKey] = true;
        }

        // Also include any legacy overdue payment records (if any exist)
        $legacyOverduePaymentSum = Payment::where('status', 'overdue')
            ->whereIn('unit_id', (clone $unitsBaseQuery)->select('id'))
            ->sum('amount_in_base');
        $overdueBalanceTzs += $legacyOverduePaymentSum;

        $overdueUnits = max(
            (clone $unitsBaseQuery)->where('status', 'overdue')->count(),
            count($overdueUnitsMap)
        );

        $recentPayments = Payment::with(['tenant', 'unit'])
            ->whereIn('unit_id', (clone $unitsBaseQuery)->select('id'))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $maintenanceItems = MaintenanceRecord::whereIn('status', ['open', 'in-progress'])
            ->whereIn('unit_id', (clone $unitsBaseQuery)->select('id'))
            ->orderByDesc('reported_date')
            ->limit(4)
            ->get();

        $units = (clone $unitsBaseQuery)
            ->with(['leases.tenant'])
            ->orderBy('floor')
            ->orderBy('unit_number')
            ->limit(5)
            ->get();

        $occupancyByFloor = (clone $unitsBaseQuery)
            ->selectRaw("floor, count(*) as total, sum(case when status in ('occupied','overdue') then 1 else 0 end) as occupied")
            ->groupBy('floor')
            ->orderBy('floor')
            ->get();

        // ── Upcoming events ──────────────────────────────────────────
        $today    = Carbon::today();
        $upcomingDays = in_array((int) $request->query('upcoming_days'), [15, 30], true)
            ? (int) $request->query('upcoming_days')
            : 30;
        $windowEnd = $today->copy()->addDays($upcomingDays);
        $events   = collect();
        $scopePropertyId = $this->shouldScopeToProperty($request) ? $this->effectivePropertyId($request) : null;

        // 1. Unpaid invoices due within the window → Rent events
        Invoice::where('status', 'unpaid')
            ->whereBetween('due_date', [$today, $windowEnd])
            ->when($scopePropertyId !== null, fn ($q) => $q->where('property_id', $scopePropertyId))
            ->orderBy('due_date')
            ->limit(10)
            ->get()
            ->each(function ($inv) use (&$events) {
                $date = Carbon::parse($inv->due_date);
                $events->push([
                    'day'   => $date->format('j'),
                    'mon'   => $date->format('M'),
                    'title' => 'Rent due' . ($inv->unit_ref ? " – {$inv->unit_ref}" : ''),
                    'meta'  => $inv->tenant_name ?? 'Tenant',
                    'type'  => 'rent',
                    'label' => 'Rent',
                    'sort'  => $date->timestamp,
                ]);
            });

        // 2. Leases expiring within the window → Lease renewal events
        Lease::with(['tenant', 'unit'])
            ->whereIn('status', ['active', 'expiring'])
            ->whereBetween('end_date', [$today, $windowEnd])
            ->whereIn('unit_id', (clone $unitsBaseQuery)->select('id'))
            ->orderBy('end_date')
            ->limit(10)
            ->get()
            ->each(function ($lease) use (&$events) {
                $date = Carbon::parse($lease->end_date);
                $events->push([
                    'day'   => $date->format('j'),
                    'mon'   => $date->format('M'),
                    'title' => 'Lease renewal' . ($lease->unit ? " – {$lease->unit->unit_number}" : ''),
                    'meta'  => ($lease->tenant?->name ?? 'Tenant') . ' · expires ' . $date->format('d M'),
                    'type'  => 'move',
                    'label' => 'Lease',
                    'sort'  => $date->timestamp,
                ]);
            });

        // 3. Scheduled maintenance due within the window → Repair events
        ScheduledMaintenance::whereIn('status', ['upcoming', 'overdue'])
            ->whereBetween('next_due', [$today, $windowEnd])
            ->when($scopePropertyId !== null, fn ($q) => $q->where('property_id', $scopePropertyId))
            ->orderBy('next_due')
            ->limit(10)
            ->get()
            ->each(function ($m) use (&$events) {
                $date = Carbon::parse($m->next_due);
                $events->push([
                    'day'   => $date->format('j'),
                    'mon'   => $date->format('M'),
                    'title' => $m->title . ($m->unit_ref ? " – {$m->unit_ref}" : ''),
                    'meta'  => $m->assignee ? "Assigned: {$m->assignee}" : ($m->category ?? 'Scheduled'),
                    'type'  => 'repair',
                    'label' => 'Repair',
                    'sort'  => $date->timestamp,
                ]);
            });

        $upcomingEvents = $events
            ->sortBy('sort')
            ->take(8)
            ->values()
            ->map(fn ($e) => collect($e)->except('sort')->all());

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalUnits'      => $totalUnits,
                'totalUnitsDelta' => $totalUnitsDelta,    // units added this month
                'occupiedUnits'   => $occupiedUnits,
                'occupancyDelta'  => $occupancyDelta,     // percentage-point change vs last month
                'vacantUnits'     => $vacantUnits,
                'overdueUnits'    => $overdueUnits,
                'monthlyRevenue'  => $monthlyRevenueTzs,  // already in TZS
                'revenueDelta'    => $revenueDelta,        // TZS change vs last month
                'overdueBalance'  => $overdueBalanceTzs,  // already in TZS
            ],
            'recentPayments'   => $recentPayments,
            'maintenanceItems' => $maintenanceItems,
            'units'            => $units,
            'occupancyByFloor' => $occupancyByFloor,
            'upcomingEvents'   => $upcomingEvents,
            'upcomingDays'     => $upcomingDays,
        ]);
    }
}
