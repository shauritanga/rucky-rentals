<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeaseController extends Controller
{
    public function index()
    {
        $leases  = Lease::with(['tenant', 'unit'])->orderByDesc('created_at')->get();
        $tenants = Tenant::orderBy('name')->get();
        $units   = Unit::orderBy('floor')->orderBy('unit_number')->get();
        return Inertia::render('Leases/Index', compact('leases', 'tenants', 'units'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tenant_id'       => 'required|exists:tenants,id',
            'unit_id'         => 'required|exists:units,id',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after:start_date',
            'duration_months' => 'required|integer|min:1',
            'payment_cycle'   => 'required|integer|in:3,4,6,12',
            'monthly_rent'    => 'required|numeric',
            'deposit'         => 'nullable|numeric',
            'terms'           => 'nullable|string',
        ]);
        $data['deposit']     = $data['deposit'] ?? ($data['monthly_rent'] * 2);
        $data['status']      = 'pending_accountant';
        $data['approval_log'] = json_encode([
            ['step'=>0,'action'=>'submitted','by'=>'James Mwangi (Lease Manager)','date'=>now()->toDateString(),'text'=>'Lease submitted for approval.']
        ]);
        Lease::create($data);
        Unit::find($data['unit_id'])->update(['status' => 'occupied']);
        return back()->with('success', 'Lease created and submitted for approval.');
    }

    public function update(Request $request, Lease $lease)
    {
        $action = $request->input('action');

        if ($action === 'approve_accountant' && $lease->status === 'pending_accountant') {
            $log = json_decode($lease->approval_log, true) ?? [];
            $log[] = ['step'=>1,'action'=>'approved','by'=>'Diana Ochieng (Accountant)','date'=>now()->toDateString(),'text'=>'Financials verified. Approved.'];
            $lease->update(['status' => 'pending_pm', 'approval_log' => json_encode($log)]);
        } elseif ($action === 'approve_pm' && $lease->status === 'pending_pm') {
            $log = json_decode($lease->approval_log, true) ?? [];
            $log[] = ['step'=>2,'action'=>'approved','by'=>'James Mwangi (Property Manager)','date'=>now()->toDateString(),'text'=>'Final approval. Lease activated.'];
            $lease->update(['status' => 'active', 'approval_log' => json_encode($log)]);
        } elseif ($action === 'reject') {
            $log = json_decode($lease->approval_log, true) ?? [];
            $log[] = ['step'=>0,'action'=>'rejected','by'=>'James Mwangi','date'=>now()->toDateString(),'reason'=>$request->input('reason',''),'text'=>'Lease rejected.'];
            $lease->update(['status' => 'rejected', 'approval_log' => json_encode($log)]);
        } elseif ($action === 'resubmit') {
            $log = json_decode($lease->approval_log, true) ?? [];
            $log[] = ['step'=>0,'action'=>'submitted','by'=>'James Mwangi (Lease Manager)','date'=>now()->toDateString(),'text'=>'Lease resubmitted after rejection.'];
            $lease->update(['status' => 'pending_accountant', 'approval_log' => json_encode($log)]);
        }

        return back()->with('success', 'Lease updated.');
    }

    public function destroy(Lease $lease)
    {
        $lease->delete();
        return back()->with('success', 'Lease terminated.');
    }
}
