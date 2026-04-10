<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
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

        $unitsBaseQuery = Unit::query();
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
        $overdueUnits  = (clone $unitsBaseQuery)->where('status', 'overdue')->count();

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

        // Sum monthly rent in TZS, respecting each lease's own currency.
        // Never mix TZS and USD raw amounts — convert everything to TZS first.
        $monthlyRevenueTzs = Lease::whereIn('status', ['active', 'expiring', 'overdue'])
            ->whereIn('unit_id', $unitIdsQuery)
            ->get(['monthly_rent', 'currency'])
            ->sum(function ($lease) {
                $amount = (float) $lease->monthly_rent;
                $currency = $lease->currency ?? 'TZS';
                if ($currency === 'TZS') {
                    return $amount;
                }
                // getLiveRate() reads from cache (same source as UI header badge),
                // never from the stale DB table — keeps both dashboard views in sync.
                return $amount * ExchangeRate::getLiveRate($currency, 'TZS');
            });

        // Revenue for leases running during last month (for delta badge)
        $lastMonthRevenueTzs = Lease::whereIn('unit_id', $unitIdsQuery)
            ->where('start_date', '<=', $lastMonthEnd)
            ->where(fn ($q) => $q->where('end_date', '>=', $lastMonthStart)->orWhereNull('end_date'))
            ->get(['monthly_rent', 'currency'])
            ->sum(function ($lease) {
                $amount   = (float) $lease->monthly_rent;
                $currency = $lease->currency ?? 'TZS';
                if ($currency === 'TZS') return $amount;
                return $amount * ExchangeRate::getLiveRate($currency, 'TZS');
            });

        $revenueDelta = $monthlyRevenueTzs - $lastMonthRevenueTzs;

        // Sum overdue payments in TZS, using the stored base amount where available,
        // otherwise converting on the fly. Never mix currencies raw.
        $overdueBalanceTzs = Payment::where('status', 'overdue')
            ->whereIn('unit_id', (clone $unitsBaseQuery)->select('id'))
            ->get(['amount', 'currency', 'amount_in_base'])
            ->sum(function ($payment) {
                // amount_in_base is pre-converted TZS stored at payment time — use it when present
                if ($payment->amount_in_base !== null) {
                    return (float) $payment->amount_in_base;
                }
                $amount = (float) $payment->amount;
                $currency = $payment->currency ?? 'TZS';
                if ($currency === 'TZS') {
                    return $amount;
                }
                return $amount * ExchangeRate::getLiveRate($currency, 'TZS');
            });

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
            ->with([
                'leases.tenant',
                'payments' => fn ($q) => $q->latest()->limit(1),
            ])
            ->orderBy('floor')
            ->orderBy('unit_number')
            ->limit(7)
            ->get();

        $occupancyByFloor = (clone $unitsBaseQuery)
            ->selectRaw("floor, count(*) as total, sum(case when status in ('occupied','overdue') then 1 else 0 end) as occupied")
            ->groupBy('floor')
            ->orderBy('floor')
            ->get();

        // ── Upcoming events ──────────────────────────────────────────
        $today    = Carbon::today();
        $in7Days  = $today->copy()->addDays(7);
        $events   = collect();
        $scopePropertyId = $this->shouldScopeToProperty($request) ? $this->effectivePropertyId($request) : null;

        // 1. Unpaid invoices due within 7 days → Rent events
        Invoice::where('status', 'unpaid')
            ->whereBetween('due_date', [$today, $in7Days])
            ->when($scopePropertyId !== null, fn ($q) => $q->where('property_id', $scopePropertyId))
            ->orderBy('due_date')
            ->limit(5)
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

        // 2. Leases expiring within 7 days → Lease renewal events
        Lease::with(['tenant', 'unit'])
            ->whereIn('status', ['active', 'expiring'])
            ->whereBetween('end_date', [$today, $in7Days])
            ->whereIn('unit_id', (clone $unitsBaseQuery)->select('id'))
            ->orderBy('end_date')
            ->limit(5)
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

        // 3. Scheduled maintenance due within 7 days → Repair events
        ScheduledMaintenance::whereIn('status', ['upcoming', 'overdue'])
            ->whereBetween('next_due', [$today, $in7Days])
            ->when($scopePropertyId !== null, fn ($q) => $q->where('property_id', $scopePropertyId))
            ->orderBy('next_due')
            ->limit(5)
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
            ->take(6)
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
        ]);
    }
}
