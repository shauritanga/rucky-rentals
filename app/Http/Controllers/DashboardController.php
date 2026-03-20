<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Tenant;
use App\Models\Payment;
use App\Models\MaintenanceTicket;
use App\Models\Lease;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totalUnits    = Unit::count();
        $occupiedUnits = Unit::whereIn('status', ['occupied', 'overdue'])->count();
        $vacantUnits   = Unit::where('status', 'vacant')->count();
        $overdueUnits  = Unit::where('status', 'overdue')->count();

        $monthlyRevenue = Lease::whereIn('status', ['active', 'expiring', 'overdue'])->sum('monthly_rent');
        $overdueBalance = Payment::where('status', 'overdue')->sum('amount');

        $recentPayments = Payment::with(['tenant', 'unit'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $maintenanceItems = MaintenanceTicket::whereIn('status', ['open', 'in-progress'])
            ->orderByDesc('reported_date')
            ->limit(4)
            ->get();

        $units = Unit::with(['leases.tenant'])->orderBy('floor')->orderBy('unit_number')->limit(7)->get();

        $occupancyByFloor = Unit::selectRaw('floor, count(*) as total, sum(case when status in ("occupied","overdue") then 1 else 0 end) as occupied')
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
