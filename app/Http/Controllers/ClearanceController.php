<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitClearance;
use App\Models\UnitClearanceItem;
use App\Services\AccountingService;
use App\Support\AccountingAutoPoster;
use App\Traits\LogsAudit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ClearanceController extends Controller
{
    use LogsAudit;

    private const MANAGING_ROLES = ['manager', 'superuser'];

    public function index(Request $request)
    {
        $user = $request->user();

        $clearancesQuery = UnitClearance::with(['lease', 'unit', 'property', 'tenant', 'items', 'documents', 'inspectedBy'])
            ->orderByDesc('created_at');
        $this->scopeByUserProperty($clearancesQuery, $request);
        $clearances = $clearancesQuery->get();

        $eligibleLeasesQuery = Lease::with(['tenant', 'unit', 'property'])
            ->whereIn('status', ['active', 'expiring', 'overdue'])
            ->where('deposit', '>', 0)
            ->where('end_date', '<=', now()->toDateString())
            ->whereDoesntHave('clearances', fn ($q) => $q->whereIn('status', ['scheduled', 'in_progress']))
            ->orderBy('lease_number');
        $this->scopeByUserProperty($eligibleLeasesQuery, $request);
        $eligibleLeases = $eligibleLeasesQuery->get();

        return Inertia::render('Clearance/Index', [
            'clearances'     => $clearances,
            'eligibleLeases' => $eligibleLeases,
            'canManage'      => in_array($user?->role, self::MANAGING_ROLES, true),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_if(!in_array($user?->role, self::MANAGING_ROLES, true), 403, 'Only a manager or superuser can start a clearance.');

        $data = $request->validate([
            'lease_id'       => ['required', 'exists:leases,id'],
            'scheduled_date' => ['nullable', 'date'],
        ]);

        $lease = Lease::with(['unit', 'tenant'])->findOrFail($data['lease_id']);

        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId !== null && (int) $lease->property_id !== $effectiveId, 403);
        }

        abort_if(!in_array($lease->status, ['active', 'expiring', 'overdue']), 422, 'Only an active lease can be cleared.');
        abort_if(
            \Carbon\Carbon::parse($lease->end_date)->startOfDay()->gt(\Carbon\Carbon::today()),
            422,
            'This lease has not reached its end date yet. Clearance can only start once the lease term has ended.'
        );
        abort_if((float) $lease->deposit <= 0, 422, 'This lease has no deposit on file.');
        abort_if(
            $lease->clearances()->whereIn('status', ['scheduled', 'in_progress'])->exists(),
            422,
            'This lease already has an open clearance.'
        );

        $count = UnitClearance::count() + 1;

        $clearance = UnitClearance::create([
            'property_id'          => $lease->property_id,
            'lease_id'             => $lease->id,
            'unit_id'              => $lease->unit_id,
            'tenant_id'            => $lease->tenant_id,
            'clearance_number'     => 'CLR-' . str_pad((string) $count, 3, '0', STR_PAD_LEFT),
            'status'               => 'scheduled',
            'scheduled_date'       => $data['scheduled_date'] ?? now()->toDateString(),
            'currency'             => $lease->currency ?: 'TZS',
            'deposit_amount'       => (float) $lease->deposit,
            'refund_amount'        => (float) $lease->deposit,
            'created_by'           => $user?->name,
        ]);

        $propertyName = Property::where('id', $lease->property_id)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Clearance started',
            resource: sprintf('%s — %s', $lease->unit?->unit_number, $lease->tenant?->name ?? 'N/A'),
            propertyName: $propertyName,
            category: 'clearance',
            propertyId: (int) $lease->property_id,
        );

        return back()->with('success', 'Clearance started.')->with('clearance_id', $clearance->id);
    }

    public function update(Request $request, UnitClearance $clearance)
    {
        $this->authorizeClearanceProperty($request, $clearance);
        abort_if(in_array($clearance->status, ['completed', 'cancelled']), 422, 'This clearance is no longer editable.');

        $user = $request->user();

        if ($request->has('cancel')) {
            $data = $request->validate(['cancelled_reason' => 'required|string|max:255']);
            $clearance->update(['status' => 'cancelled', 'cancelled_reason' => $data['cancelled_reason']]);

            return back()->with('success', 'Clearance cancelled.');
        }

        if ($request->has('inspection_checklist')) {
            $data = $request->validate([
                'inspection_checklist'   => 'array',
                'scheduled_date'         => 'nullable|date',
            ]);

            $clearance->update([
                'inspection_checklist' => $data['inspection_checklist'],
                'scheduled_date'       => $data['scheduled_date'] ?? $clearance->scheduled_date,
                'status'               => $clearance->status === 'scheduled' ? 'in_progress' : $clearance->status,
                'inspected_by_user_id' => $clearance->inspected_by_user_id ?? $user?->id,
                'inspected_at'         => $clearance->inspected_at ?? now(),
            ]);

            return back()->with('success', 'Inspection saved.');
        }

        if ($request->has('manager_notes')) {
            $data = $request->validate(['manager_notes' => 'nullable|string']);
            $clearance->update(['manager_notes' => $data['manager_notes']]);

            return back()->with('success', 'Notes saved.');
        }

        return back();
    }

    public function storeItem(Request $request, UnitClearance $clearance)
    {
        $this->authorizeClearanceProperty($request, $clearance);
        abort_if(in_array($clearance->status, ['completed', 'cancelled']), 422, 'This clearance is no longer editable.');

        $data = $request->validate([
            'category'           => 'required|string|max:100',
            'room'               => 'nullable|string|max:100',
            'description'        => 'required|string|max:255',
            'cost'               => 'required|numeric|min:0',
            'responsible_party'  => 'nullable|in:tenant,landlord',
            'images'             => 'nullable|array',
            'images.*'           => 'file|image|max:5120',
        ]);

        $item = UnitClearanceItem::create([
            'unit_clearance_id' => $clearance->id,
            'category'          => $data['category'],
            'room'              => $data['room'] ?? null,
            'description'       => $data['description'],
            'cost'              => $data['cost'],
            'responsible_party' => $data['responsible_party'] ?? 'tenant',
        ]);

        if ($clearance->status === 'scheduled') {
            $clearance->update(['status' => 'in_progress']);
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('clearance/' . $clearance->id, 'public');
                Document::create([
                    'unit_clearance_id'      => $clearance->id,
                    'unit_clearance_item_id' => $item->id,
                    'name'                   => $image->getClientOriginalName(),
                    'file_path'              => $path,
                    'file_type'              => $image->getClientOriginalExtension() ?: 'jpg',
                    'file_size'              => round($image->getSize() / 1024, 1) . ' KB',
                    'tag'                    => 'clearance',
                    'document_type'          => 'clearance_image',
                    'uploaded_by'            => $request->user()?->name ?? 'System',
                ]);
            }
        }

        $clearance->recalculateTotals();

        return back()->with('success', 'Item added.');
    }

    public function updateItem(Request $request, UnitClearance $clearance, UnitClearanceItem $item)
    {
        $this->authorizeClearanceProperty($request, $clearance);
        abort_if((int) $item->unit_clearance_id !== $clearance->id, 404);
        abort_if(in_array($clearance->status, ['completed', 'cancelled']), 422, 'This clearance is no longer editable.');

        $data = $request->validate([
            'category'          => 'sometimes|string|max:100',
            'room'              => 'nullable|string|max:100',
            'description'       => 'sometimes|string|max:255',
            'cost'              => 'sometimes|numeric|min:0',
            'responsible_party' => 'sometimes|in:tenant,landlord',
        ]);

        $item->update($data);
        $clearance->recalculateTotals();

        return back()->with('success', 'Item updated.');
    }

    public function destroyItem(Request $request, UnitClearance $clearance, UnitClearanceItem $item)
    {
        $this->authorizeClearanceProperty($request, $clearance);
        abort_if((int) $item->unit_clearance_id !== $clearance->id, 404);
        abort_if(in_array($clearance->status, ['completed', 'cancelled']), 422, 'This clearance is no longer editable.');

        $item->delete();
        $clearance->recalculateTotals();

        return back()->with('success', 'Item removed.');
    }

    public function finalize(Request $request, UnitClearance $clearance, AccountingService $accountingService)
    {
        $this->authorizeClearanceProperty($request, $clearance);

        $user = $request->user();
        abort_if(!in_array($user?->role, self::MANAGING_ROLES, true), 403, 'Only a manager or superuser can finalize a clearance.');
        abort_if($clearance->status === 'completed', 422, 'This clearance has already been finalized.');
        abort_if($clearance->status === 'cancelled', 422, 'This clearance was cancelled.');
        $request->validate(['confirm' => 'required|accepted']);

        $lease = Lease::findOrFail($clearance->lease_id);

        DB::transaction(function () use ($clearance, $lease, $user, $accountingService) {
            $clearance->recalculateTotals();
            $clearance->refresh();

            app(AccountingAutoPoster::class)->voidByReference($lease->property_id, 'DEP-' . $lease->id);
            $accountingService->postClearanceSettlement($clearance);

            $log = json_decode($lease->approval_log, true) ?? [];
            $log[] = [
                'step' => 0, 'action' => 'terminated', 'by' => $user->name, 'date' => now()->toDateString(),
                'text' => 'Lease terminated via clearance ' . $clearance->clearance_number,
            ];
            $lease->update(['status' => 'terminated', 'approval_log' => json_encode($log)]);

            if ($lease->unit_id) {
                $unit = Unit::find($lease->unit_id);
                if ($unit && !$unit->hasOtherActiveLeases()) {
                    $unit->update(['status' => 'vacant']);
                }
            }

            $clearance->update([
                'status'       => 'completed',
                'finalized_by' => $user->name,
                'finalized_at' => now(),
            ]);
        });

        $propertyName = Property::where('id', $clearance->property_id)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Clearance finalized',
            resource: $clearance->clearance_number,
            propertyName: $propertyName,
            category: 'clearance',
            metadata: [
                'deposit'    => $clearance->deposit_amount,
                'deductions' => $clearance->total_deductions,
                'refund'     => $clearance->refund_amount,
                'shortfall'  => $clearance->shortfall_amount,
            ],
            propertyId: (int) $clearance->property_id,
        );

        return back()->with('success', 'Clearance finalized. Lease terminated and deposit settled.');
    }

    public function downloadCertificate(Request $request, UnitClearance $clearance): \Illuminate\Http\Response
    {
        $this->authorizeClearanceProperty($request, $clearance);

        $clearance->load(['lease', 'unit', 'property', 'tenant', 'items', 'inspectedBy']);

        $companyName  = \App\Models\SystemSetting::get('company_name', 'Mwamba Properties');
        $companyEmail = \App\Models\SystemSetting::get('support_email', '');

        $pdfContent = Pdf::loadView('pdf.clearance-certificate', [
            'clearance'    => $clearance,
            'companyName'  => $companyName,
            'companyEmail' => $companyEmail,
        ])->setPaper('a4', 'portrait')->output();

        $filename = $clearance->clearance_number . '.pdf';

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function destroy(Request $request, UnitClearance $clearance)
    {
        $this->authorizeClearanceProperty($request, $clearance);
        abort_if($clearance->status !== 'scheduled', 422, 'Only a not-yet-started clearance can be deleted.');

        $propertyName = Property::where('id', $clearance->property_id)->value('name');
        $number       = $clearance->clearance_number;

        $clearance->delete();

        $this->logAudit(
            request: $request,
            action: 'Clearance deleted',
            resource: $number,
            propertyName: $propertyName,
            category: 'clearance',
            propertyId: (int) $clearance->property_id,
        );

        return back()->with('success', 'Clearance deleted.');
    }

    private function scopeByUserProperty($query, Request $request): void
    {
        if (!$this->shouldScopeToProperty($request)) return;
        $propertyId = $this->effectivePropertyId($request);
        if ($propertyId === null) { $query->whereRaw('1 = 0'); return; }
        $query->where('property_id', $propertyId);
    }

    private function authorizeClearanceProperty(Request $request, UnitClearance $clearance): void
    {
        if (!$this->shouldScopeToProperty($request)) return;
        $effectiveId = $this->effectivePropertyId($request);
        if ($effectiveId === null) return;
        abort_if((int) $clearance->property_id !== $effectiveId, 403);
    }
}
