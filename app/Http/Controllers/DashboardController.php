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
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        if (Auth::check() && Auth::user()->role === 'superuser') {
            return redirect()->route('superuser.index');
        }

        $user = Auth::user();

        if (MockRentalData::shouldUse() && $user?->role !== 'manager') {
            return Inertia::render('Dashboard', MockRentalData::dashboard());
        }

        $unitsBaseQuery = Unit::query();
        if ($user && $user->role === 'manager') {
            if (empty($user->property_id)) {
                $unitsBaseQuery->whereRaw('1 = 0');
            } else {
                $unitsBaseQuery->where('property_id', $user->property_id);
            }
        }

        $unitIdsQuery = (clone $unitsBaseQuery)->select('id');

        $totalUnits    = (clone $unitsBaseQuery)->count();
        $occupiedUnits = (clone $unitsBaseQuery)->whereIn('status', ['occupied', 'overdue'])->count();
        $vacantUnits   = (clone $unitsBaseQuery)->where('status', 'vacant')->count();
        $overdueUnits  = (clone $unitsBaseQuery)->where('status', 'overdue')->count();

        $monthlyRevenue = Lease::whereIn('status', ['active', 'expiring', 'overdue'])
            ->whereIn('unit_id', $unitIdsQuery)
            ->sum('monthly_rent');
        $overdueBalance = Payment::where('status', 'overdue')
            ->whereIn('unit_id', (clone $unitsBaseQuery)->select('id'))
            ->sum('amount');

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

        // 1. Unpaid invoices due within 7 days → Rent events
        Invoice::where('status', 'unpaid')
            ->whereBetween('due_date', [$today, $in7Days])
            ->when($user && $user->role === 'manager' && $user->property_id, fn ($q) => $q->where('property_id', $user->property_id))
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
            ->when($user && $user->role === 'manager' && $user->property_id, fn ($q) => $q->where('property_id', $user->property_id))
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
                'totalUnits'     => $totalUnits,
                'occupiedUnits'  => $occupiedUnits,
                'vacantUnits'    => $vacantUnits,
                'overdueUnits'   => $overdueUnits,
                'monthlyRevenue' => $monthlyRevenue,
                'overdueBalance' => $overdueBalance,
            ],
            'recentPayments'   => $recentPayments,
            'maintenanceItems' => $maintenanceItems,
            'units'            => $units,
            'occupancyByFloor' => $occupancyByFloor,
            'upcomingEvents'   => $upcomingEvents,
        ]);
    }
}
