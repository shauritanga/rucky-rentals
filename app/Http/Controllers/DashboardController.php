<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Tenant;
use App\Models\Payment;
use App\Models\MaintenanceRecord;
use App\Models\Lease;
use App\Support\MockRentalData;
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
        ]);
    }
}
