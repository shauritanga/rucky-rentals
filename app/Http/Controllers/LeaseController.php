<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use App\Models\Lease;
use App\Models\LeaseInstallment;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\LeaseApprovalRequestNotification;
use App\Support\AccountingAutoPoster;
use App\Support\MockRentalData;
use App\Traits\LogsAudit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class LeaseController extends Controller
{
    use LogsAudit;
    public function index(Request $request)
    {
        $user = $request->user();

        if (MockRentalData::shouldUse() && $user?->role !== 'manager') {
            return Inertia::render('Leases/Index', [
                'leases'   => MockRentalData::leases(),
                'tenants'  => MockRentalData::tenants(),
                'units'    => MockRentalData::units(),
                'settings' => SystemSetting::pluck('value', 'key'),
            ]);
        }

        $leasesQuery = Lease::with([
            'tenant',
            'unit',
            'installments' => fn($q) => $q->orderBy('sequence'),
        ])->orderByDesc('created_at');
        $tenantsQuery = Tenant::query()->orderBy('name');
        $unitsQuery = Unit::query()->orderBy('floor')->orderBy('unit_number');

        $this->scopeByUserProperty($leasesQuery, $request, 'property_id');
        $this->scopeByUserProperty($tenantsQuery, $request, 'property_id');
        $this->scopeByUserProperty($unitsQuery, $request, 'property_id');

        $leases   = $leasesQuery->get();
        $tenants  = $tenantsQuery->get();
        $units    = $unitsQuery->get();
        $settings = SystemSetting::pluck('value', 'key');
        return Inertia::render('Leases/Index', compact('leases', 'tenants', 'units', 'settings'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $effectivePropertyId = $this->shouldScopeToProperty($request) ? $this->effectivePropertyId($request) : null;

        if ($this->shouldScopeToProperty($request)) {
            abort_if($effectivePropertyId === null, 422, 'No property context available.');
            abort_if(!Property::where('id', $effectivePropertyId)->exists(), 422, 'Assigned property not found.');
        }

        $validated = $request->validate([
            'tenant_mode'     => 'nullable|in:existing,new',
            'tenant_id'       => [
                'required',
                Rule::exists('tenants', 'id')->when(
                    $effectivePropertyId,
                    fn($rule) => $rule->where(fn($q) => $q->where('property_id', $effectivePropertyId))
                ),
            ],
            'new_tenant_national_id' => 'nullable|string|max:255',
            'unit_id'         => [
                'required',
                Rule::exists('units', 'id')->when(
                    $effectivePropertyId,
                    fn($rule) => $rule->where(fn($q) => $q->where('property_id', $effectivePropertyId))
                ),
            ],
            'possession_date' => 'nullable|date',
            'rent_start_date' => 'nullable|date',
            'fitout_enabled'  => 'nullable|boolean',
            'fitout_to_date'  => 'nullable|date',
            'fitout_days'     => 'nullable|integer|min:0',
            'wht_rate'        => 'nullable|numeric|min:0|max:100',
            'service_charge_rate' => 'nullable|numeric|min:0|max:100',
            'vat_rate'        => 'nullable|numeric|min:0|max:100',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after:start_date',
            'duration_months' => 'required|integer|min:1',
            'payment_cycle'   => 'required|integer|in:3,4,6,12',
            'monthly_rent'    => 'required|numeric',
            'deposit'         => 'nullable|numeric',
            'terms'           => 'nullable|string',
        ]);

        $unit = Unit::findOrFail($validated['unit_id']);
        $propertyId = $unit->property_id;
        abort_if(empty($propertyId), 422, 'Selected unit is not linked to any property.');

        if ($effectivePropertyId !== null) {
            abort_if((int) $propertyId !== $effectivePropertyId, 403);
        }

        $tenantId = $validated['tenant_id'] ?? null;

        if (($validated['tenant_mode'] ?? 'existing') === 'new') {
            $words = preg_split('/\s+/', trim($validated['new_tenant_name']));
            $initials = strtoupper(
                substr($words[0] ?? '', 0, 1) .
                    substr($words[1] ?? '', 0, 1)
            );

            $tenant = Tenant::create([
                'property_id' => $propertyId,
                'name' => $validated['new_tenant_name'],
                'email' => $validated['new_tenant_email'],
                'phone' => $validated['new_tenant_phone'],
                'national_id' => $validated['new_tenant_national_id'] ?? null,
                'initials' => $initials ?: 'NA',
                'color' => 'rgba(59,130,246,.18)',
                'text_color' => 'var(--accent)',
                'nok_name' => null,
                'nok_phone' => null,
                'nok_relation' => null,
                'notes' => null,
            ]);

            $tenantId = $tenant->id;
        }

        if (($validated['tenant_mode'] ?? 'existing') === 'existing') {
            $tenant = Tenant::findOrFail($tenantId);
            if ($effectivePropertyId !== null) {
                abort_if((int) $tenant->property_id !== $effectivePropertyId, 403);
            }
        }

        // Determine initial status — superuser acting in property view mode auto-approves
        if ($this->isSuperuserActing($request)) {
            $status = 'active';
            $approvalLog = json_encode([[
                'step' => 1, 'action' => 'approved',
                'by' => $user->name . ' (Superuser)',
                'date' => now()->toDateString(),
                'text' => 'Auto-approved by superuser.',
            ]]);
        } else {
            $status = 'pending_accountant';
            $approvalLog = json_encode([
                ['step' => 0, 'action' => 'submitted', 'by' => $user->name, 'date' => now()->toDateString(), 'text' => 'Lease submitted for approval.']
            ]);
        }

        $data = [
            'property_id' => $propertyId,
            'tenant_id' => $tenantId,
            'unit_id' => $validated['unit_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'duration_months' => $validated['duration_months'],
            'payment_cycle' => $validated['payment_cycle'],
            'currency' => $unit->currency ?: 'USD',
            'possession_date' => $validated['possession_date'] ?? $validated['start_date'],
            'rent_start_date' => $validated['rent_start_date'] ?? $validated['start_date'],
            'fitout_enabled' => (bool) ($validated['fitout_enabled'] ?? false),
            'fitout_to_date' => $validated['fitout_to_date'] ?? null,
            'fitout_days' => (int) ($validated['fitout_days'] ?? 0),
            'wht_rate' => (float) ($validated['wht_rate'] ?? 10),
            'service_charge_rate' => (float) ($validated['service_charge_rate'] ?? 5),
            'vat_rate' => (float) ($validated['vat_rate'] ?? 18),
            'monthly_rent' => $validated['monthly_rent'],
            'deposit' => $validated['deposit'] ?? (function () use ($validated, $unit) {
                $rentMonths = (float) SystemSetting::get('deposit_rent_months', 1);
                $scMonths   = (float) SystemSetting::get('deposit_service_charge_months', 1);
                return round(($validated['monthly_rent'] * $rentMonths) + (($unit->service_charge ?? 0) * $scMonths), 2);
            })(),
            'terms' => $validated['terms'] ?? null,
            'status' => $status,
            'approval_log' => $approvalLog,
        ];

        DB::transaction(function () use (&$lease, $data, $unit) {
            $lease = Lease::create($data);
            $unit->update(['status' => 'occupied']);
            // If auto-approved, generate installments and post deposit GL entry
            if ($lease->status === 'active') {
                $this->ensureInstallmentsGenerated($lease->fresh());
                $this->postDepositEntry($lease->fresh());
            }
        });

        $propertyName = Property::where('id', $propertyId)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Lease created',
            resource: sprintf('%s - %s', $unit->unit_number, $tenant->name ?? 'N/A'),
            propertyName: $propertyName,
            category: 'lease',
            propertyId: (int) $propertyId,
        );

        // Notify all superusers when a lease is submitted for approval
        if ($lease->status === 'pending_accountant') {
            $superusers = User::where('role', 'superuser')->get();
            Notification::send(
                $superusers,
                new LeaseApprovalRequestNotification(
                    $lease->fresh(['tenant', 'unit.property']),
                    $user->name
                )
            );
        }

        $message = $this->isSuperuserActing($request)
            ? 'Lease created and approved.'
            : 'Lease created and submitted for approval.';

        return back()->with('success', $message);
    }

    public function update(Request $request, Lease $lease)
    {
        $user = $request->user();

        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId !== null && (int) $lease->property_id !== $effectiveId, 403);
        }

        $action = $request->input('action');

        if ($action === 'approve_superuser' && $user->role === 'superuser'
            && in_array($lease->status, ['pending_accountant', 'pending_pm'])) {
            $log = json_decode($lease->approval_log, true) ?? [];
            $log[] = [
                'step' => 2, 'action' => 'approved',
                'by' => $user->name . ' (Superuser)',
                'date' => now()->toDateString(),
                'text' => 'Approved by superuser.',
            ];
            DB::transaction(function () use ($lease, $log) {
                $lease->update(['status' => 'active', 'approval_log' => json_encode($log)]);
                $this->ensureInstallmentsGenerated($lease->fresh());
                $this->postDepositEntry($lease->fresh());
            });
        } elseif ($action === 'approve_accountant' && $lease->status === 'pending_accountant') {
            // Only superuser can approve
            abort_if($user->role !== 'superuser', 403, 'Only superuser can approve leases.');
            $log = json_decode($lease->approval_log, true) ?? [];
            $log[] = ['step' => 1, 'action' => 'approved', 'by' => $user->name . ' (Superuser)', 'date' => now()->toDateString(), 'text' => 'Approved by superuser.'];
            DB::transaction(function () use ($lease, $log) {
                $lease->update(['status' => 'active', 'approval_log' => json_encode($log)]);
                $this->ensureInstallmentsGenerated($lease->fresh());
                $this->postDepositEntry($lease->fresh());
            });
        } elseif ($action === 'approve_pm' && $lease->status === 'pending_pm') {
            // Only superuser can approve
            abort_if($user->role !== 'superuser', 403, 'Only superuser can approve leases.');
            $log = json_decode($lease->approval_log, true) ?? [];
            $log[] = ['step' => 2, 'action' => 'approved', 'by' => $user->name . ' (Superuser)', 'date' => now()->toDateString(), 'text' => 'Approved by superuser.'];
            DB::transaction(function () use ($lease, $log) {
                $lease->update(['status' => 'active', 'approval_log' => json_encode($log)]);
                $this->ensureInstallmentsGenerated($lease->fresh());
                $this->postDepositEntry($lease->fresh());
            });
        } elseif ($action === 'reject') {
            $log = json_decode($lease->approval_log, true) ?? [];
            $log[] = ['step' => 0, 'action' => 'rejected', 'by' => $user->name, 'date' => now()->toDateString(), 'reason' => $request->input('reason', ''), 'text' => 'Lease rejected.'];
            $lease->update(['status' => 'rejected', 'approval_log' => json_encode($log)]);
        } elseif ($action === 'resubmit') {
            $log = json_decode($lease->approval_log, true) ?? [];
            $log[] = ['step' => 0, 'action' => 'submitted', 'by' => $user->name, 'date' => now()->toDateString(), 'text' => 'Lease resubmitted after rejection.'];
            $lease->update(['status' => 'pending_accountant', 'approval_log' => json_encode($log)]);

            $superusers = User::where('role', 'superuser')->get();
            Notification::send(
                $superusers,
                new LeaseApprovalRequestNotification(
                    $lease->fresh(['tenant', 'unit.property']),
                    $user->name
                )
            );
        } elseif ($action === 'edit') {
            // Active/expiring/overdue leases → superuser only
            if (in_array($lease->status, ['active', 'expiring', 'overdue'])) {
                abort_if($user->role !== 'superuser' && !$this->isSuperuserActing($request), 403, 'Only superuser can edit an active lease.');
            }
            // Pending/rejected → any authenticated user with property access can edit

            $editData = $request->validate([
                'tenant_id'       => ['required', Rule::exists('tenants', 'id')],
                'unit_id'         => ['required', Rule::exists('units', 'id')],
                'start_date'      => 'required|date',
                'end_date'        => 'required|date|after:start_date',
                'duration_months' => 'required|integer|min:1',
                'payment_cycle'   => 'required|integer|in:3,4,6,12',
                'possession_date' => 'nullable|date',
                'rent_start_date' => 'nullable|date',
                'fitout_enabled'  => 'nullable|boolean',
                'fitout_to_date'  => 'nullable|date',
                'fitout_days'     => 'nullable|integer|min:0',
                'monthly_rent'    => 'required|numeric|min:0',
                'deposit'         => 'nullable|numeric|min:0',
                'wht_rate'        => 'nullable|numeric|min:0|max:100',
                'vat_rate'        => 'nullable|numeric|min:0|max:100',
                'terms'           => 'nullable|string',
            ]);

            DB::transaction(function () use ($lease, $editData) {
                $oldUnitId     = $lease->unit_id;
                $newUnitId     = $editData['unit_id'];
                $oldDeposit    = (float) $lease->deposit;
                $wasActive     = in_array($lease->status, ['active', 'expiring', 'overdue']);

                $lease->update($editData);

                if ((int) $oldUnitId !== (int) $newUnitId) {
                    Unit::where('id', $oldUnitId)->update(['status' => 'available']);
                    Unit::where('id', $newUnitId)->update(['status' => 'occupied']);
                }

                // If lease is active and deposit amount changed, re-post the deposit GL entry
                $newDeposit = (float) ($editData['deposit'] ?? $oldDeposit);
                if ($wasActive && abs($newDeposit - $oldDeposit) > 0.01) {
                    $this->voidDepositEntry($lease);
                    $this->postDepositEntry($lease->fresh());
                }
            });
        } elseif ($action === 'update_fitout') {
            abort_if($user->role !== 'superuser' && !$this->isSuperuserActing($request), 403, 'Only superuser can edit fit-out settings.');
            $fitoutData = $request->validate([
                'fitout_enabled' => 'required|boolean',
                'fitout_to_date' => 'nullable|date',
                'fitout_days'    => 'nullable|integer|min:0',
            ]);
            $lease->update([
                'fitout_enabled' => $fitoutData['fitout_enabled'],
                'fitout_to_date' => $fitoutData['fitout_enabled'] ? ($fitoutData['fitout_to_date'] ?? null) : null,
                'fitout_days'    => $fitoutData['fitout_enabled'] ? (int) ($fitoutData['fitout_days'] ?? 0) : 0,
            ]);
        }

        $propertyName = Property::where('id', $lease->property_id)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Lease status updated',
            resource: sprintf('Lease #%d → %s', $lease->id, $lease->fresh()->status),
            propertyName: $propertyName,
            category: 'lease',
            metadata: ['action' => $action, 'new_status' => $lease->fresh()->status],
            propertyId: $lease->property_id ? (int) $lease->property_id : null,
        );

        return back()->with('success', 'Lease updated.');
    }

    public function destroy(Lease $lease)
    {
        $request = request();
        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId !== null && (int) $lease->property_id !== $effectiveId, 403);
        }

        $propertyId   = $lease->property_id;
        $propertyName = Property::where('id', $propertyId)->value('name');
        $resource     = sprintf('Lease #%d', $lease->id);

        DB::transaction(function () use ($lease) {
            $this->voidDepositEntry($lease);
            app(\App\Services\AccountingService::class)->postDepositRefund($lease);
            $lease->delete();
        });

        $this->logAudit(
            request: $request,
            action: 'Lease deleted',
            resource: $resource,
            propertyName: $propertyName,
            category: 'lease',
            propertyId: $propertyId ? (int) $propertyId : null,
        );

        return back()->with('success', 'Lease terminated.');
    }

    private function voidDepositEntry(Lease $lease): void
    {
        app(AccountingAutoPoster::class)->voidByReference(
            $lease->property_id,
            'DEP-' . $lease->id,
        );
    }

    private function postDepositEntry(Lease $lease): void
    {
        $deposit = (float) ($lease->deposit ?? 0);
        if ($deposit <= 0) return;

        $currency = strtoupper((string) ($lease->currency ?? 'TZS'));
        $depositTzs = $currency === 'TZS'
            ? $deposit
            : round($deposit * ExchangeRate::getRate(
                propertyId: null,
                fromCurrency: $currency,
                toCurrency: 'TZS',
                date: $lease->start_date,
              ), 2);

        app(AccountingAutoPoster::class)->post(
            propertyId: $lease->property_id,
            entryDate: $lease->start_date,
            description: 'Security deposit received',
            reference: 'DEP-' . $lease->id,
            lines: [
                ['account_code' => '1000', 'account_name' => 'Cash at Bank',     'debit' => $depositTzs, 'credit' => 0],
                ['account_code' => '2100', 'account_name' => 'Deposits Payable', 'debit' => 0,           'credit' => $depositTzs],
            ],
            sourceType: 'lease',
            sourceId: $lease->id,
        );
    }

    private function scopeByUserProperty($query, Request $request, string $column): void
    {
        if (!$this->shouldScopeToProperty($request)) return;
        $propertyId = $this->effectivePropertyId($request);
        if ($propertyId === null) { $query->whereRaw('1 = 0'); return; }
        $query->where($column, $propertyId);
    }

    public function ensureInstallmentsGenerated(Lease $lease): void
    {
        if ($lease->installments()->exists()) {
            return;
        }

        $cycleMonths = (int) ($lease->payment_cycle ?: 1);
        $monthlyRent = (float) ($lease->monthly_rent ?: 0);
        $currency = in_array(strtoupper((string) $lease->currency), ['USD', 'TZS'], true)
            ? strtoupper((string) $lease->currency)
            : 'USD';

        $start = Carbon::parse($lease->rent_start_date ?: $lease->start_date)->startOfDay();
        $end = Carbon::parse($lease->end_date)->startOfDay();
        $today = Carbon::today();

        $sequence = 1;
        $cursor = $start->copy();

        while ($cursor->lt($end) && $sequence <= 120) {
            $periodStart = $cursor->copy();
            $nextCursor = $cursor->copy()->addMonths($cycleMonths);
            $periodClose = $nextCursor->lte($end) ? $nextCursor : $end->copy();
            $periodEnd = $periodClose->copy()->subDay();

            $monthsInPeriod = max(1, $periodStart->diffInMonths($periodClose));
            $amount = round($monthlyRent * $monthsInPeriod, 2);
            $status = $periodStart->lt($today) ? 'overdue' : 'unpaid';

            LeaseInstallment::create([
                'property_id' => $lease->property_id,
                'lease_id' => $lease->id,
                'sequence' => $sequence,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'due_date' => $periodStart->toDateString(),
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
                'paid_amount' => 0,
            ]);

            $cursor = $nextCursor;
            $sequence += 1;
        }
    }
}
