<?php

namespace App\Http\Controllers;

use App\Mail\ManagerWelcomeMail;
use App\Models\AuditLog;
use App\Services\AccountingService;
use App\Models\ExchangeRate;
use App\Models\FuelLog;
use App\Models\Payment;
use App\Support\AccountingAutoPoster;
use App\Support\FloorConfig;
use App\Models\Lease;
use App\Models\MaintenanceRecord;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\LeaseDecisionNotification;
use App\Notifications\MaintenanceApprovalNotification;
use App\Notifications\MaintenanceDecisionNotification;
use App\Traits\LogsAudit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class SuperuserController extends Controller
{
    use LogsAudit;
    public function index()
    {
        $properties = Property::with('manager:id,name,email')
            ->withCount([
                'units as unit_count_live',
                'units as occupied_units_live' => fn($q) => $q->whereIn('units.status', ['occupied', 'overdue']),
            ])
            ->withSum(
                ['leases as revenue_tzs' => fn($q) => $q
                    ->whereIn('leases.status', ['active', 'expiring', 'overdue'])
                    ->where('leases.currency', 'TZS')],
                'monthly_rent'
            )
            ->withSum(
                ['leases as revenue_usd' => fn($q) => $q
                    ->whereIn('leases.status', ['active', 'expiring', 'overdue'])
                    ->where('leases.currency', 'USD')],
                'monthly_rent'
            )
            ->orderBy('name')
            ->get()
            ->map(function ($property) {
                $revTzs = (float) ($property->revenue_tzs ?? 0);
                $revUsd = (float) ($property->revenue_usd ?? 0);

                // Convert USD portion using the live display rate — same cache key as the
                // UI header rate badge, so every view of this property shows the same figure.
                // DO NOT use ExchangeRate::getRate() here — it reads the DB first, which may
                // contain a stale manually-entered rate that differs from the live rate.
                $usdRate = ($revUsd > 0) ? ExchangeRate::getLiveRate('USD', 'TZS') : 0;

                $property->monthly_revenue_tzs = $revTzs + ($revUsd * $usdRate);

                $property->unit_count     = (int) ($property->unit_count_live    ?? 0);
                $property->occupied_units = (int) ($property->occupied_units_live ?? 0);

                // Remove raw currency parts — frontend must never convert financial amounts
                unset($property->revenue_tzs, $property->revenue_usd);

                return $property;
            });

        $onlineThreshold = now()->subMinutes(5);
        $managers = User::query()
            ->with('property:id,name')
            ->whereIn('role', ['manager', 'accountant', 'viewer', 'superuser'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'property_id', 'last_seen_at'])
            ->map(function ($m) use ($onlineThreshold) {
                $m->online = $m->last_seen_at !== null && $m->last_seen_at >= $onlineThreshold;
                $m->lastActive = $m->last_seen_at
                    ? $m->last_seen_at->diffForHumans()
                    : null;
                return $m;
            });

        $archivedManagers = User::onlyTrashed()
            ->whereIn('role', ['manager', 'accountant', 'viewer'])
            ->orderByDesc('deleted_at')
            ->get(['id', 'name', 'email', 'role', 'property_id', 'deleted_at']);

        $auditLogs = AuditLog::query()
            ->latest()
            ->limit(200)
            ->get();

        $settings = SystemSetting::pluck('value', 'key');

        $pendingLeases = Lease::with([
                'tenant:id,name,email,phone',
                'unit:id,unit_number,floor,property_id',
                'unit.property:id,name,manager_user_id',
                'unit.property.manager:id,name,email',
            ])
            ->whereIn('status', ['pending_accountant', 'pending_pm'])
            ->orderBy('created_at')
            ->get();

        $pendingMaintenance = MaintenanceRecord::with([
                'unit:id,unit_number,property_id',
                'unit.property:id,name,manager_user_id',
                'unit.property.manager:id,name,email',
                'property:id,name,manager_user_id',
                'property.manager:id,name,email',
            ])
            ->whereIn('workflow_status', ['submitted', 'pending_manager'])
            ->orderBy('created_at')
            ->get();

        return Inertia::render('Superuser/Index', [
            'properties'         => $properties,
            'managers'           => $managers,
            'auditLogs'          => $auditLogs,
            'settings'           => $settings,
            'pendingLeases'      => $pendingLeases,
            'pendingMaintenance' => $pendingMaintenance,
            'archivedManagers'   => $archivedManagers,
        ]);
    }

    public function storeProperty(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:120',
            'address'          => 'nullable|string|max:255',
            'city'             => 'nullable|string|max:120',
            'country'          => 'nullable|string|max:120',
            'status'           => 'required|in:active,trial,inactive',
            'manager_user_id'  => 'nullable|exists:users,id',
            // floor config fields
            'basements'        => 'nullable|integer|min:0|max:10',
            'has_ground_floor' => 'nullable|boolean',
            'has_mezzanine'    => 'nullable|boolean',
            'upper_floors'     => 'required|integer|min:1|max:100',
        ]);

        // Assemble floor_config and keep total_floors in sync for backward compat
        $floorConfig = [
            'basements'        => (int) ($data['basements'] ?? 0),
            'has_ground_floor' => (bool) ($data['has_ground_floor'] ?? false),
            'has_mezzanine'    => (bool) ($data['has_mezzanine'] ?? false),
            'upper_floors'     => (int) $data['upper_floors'],
        ];
        unset($data['basements'], $data['has_ground_floor'], $data['has_mezzanine'], $data['upper_floors']);
        $data['floor_config'] = $floorConfig;
        $data['total_floors'] = $floorConfig['upper_floors'];

        $maxBldCode = Property::query()
            ->where('code', 'like', 'BLD%')
            ->pluck('code')
            ->map(function ($code) {
                if (!is_string($code)) {
                    return 0;
                }

                if (!preg_match('/^BLD(\d+)$/', strtoupper($code), $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max() ?? 0;

        $nextCode = $maxBldCode + 1;
        $data['code'] = 'BLD' . str_pad((string) $nextCode, 2, '0', STR_PAD_LEFT);
        $data['country'] = $data['country'] ?: 'Tanzania';
        $data['unit_count'] = 0;
        $data['occupied_units'] = 0;

        $property = Property::create($data);

        app(AccountingAutoPoster::class)->seedChartOfAccounts((int) $property->id);

        if (!empty($data['manager_user_id'])) {
            User::where('id', $data['manager_user_id'])->update([
                'role' => 'manager',
                'property_id' => $property->id,
            ]);
        }

        $this->logAudit(
            request: $request,
            action: 'Property created',
            resource: sprintf('%s (%s)', $property->name, $property->code),
            propertyName: $property->name,
            category: 'settings',
            propertyId: (int) $property->id,
        );

        return back()->with('success', 'Property created successfully.');
    }

    public function assignManager(Request $request, Property $property)
    {
        $data = $request->validate([
            'manager_user_id' => 'required|exists:users,id',
        ]);

        $newManager = User::findOrFail($data['manager_user_id']);

        if ($property->manager_user_id && $property->manager_user_id !== $newManager->id) {
            User::where('id', $property->manager_user_id)->update(['property_id' => null]);
        }

        $property->update(['manager_user_id' => $newManager->id]);
        $newManager->update([
            'role' => 'manager',
            'property_id' => $property->id,
        ]);

        $this->logAudit(
            request: $request,
            action: 'Manager assigned',
            resource: sprintf('%s -> %s', $property->name, $newManager->name),
            propertyName: $property->name,
            category: 'user',
            propertyId: (int) $property->id,
        );

        return back()->with('success', 'Manager assigned successfully.');
    }

    public function storeManager(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:120|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'role' => 'required|in:manager,accountant,viewer',
            'property_id' => 'nullable|exists:properties,id',
            'twoFA' => 'nullable|in:yes,no',
        ]);

        $initialPassword = 'password';

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($initialPassword),
            'role' => $data['role'],
            'property_id' => $data['property_id'] ?? null,
            'must_change_password' => $data['role'] === 'manager',
        ]);

        $assignedPropertyName = null;

        if ($data['role'] === 'manager' && !empty($data['property_id'])) {
            $property = Property::findOrFail($data['property_id']);
            $assignedPropertyName = $property->name;

            if ($property->manager_user_id && $property->manager_user_id !== $user->id) {
                User::where('id', $property->manager_user_id)->update(['property_id' => null]);
            }

            $property->update(['manager_user_id' => $user->id]);
        } elseif (!empty($data['property_id'])) {
            $assignedPropertyName = Property::where('id', $data['property_id'])->value('name');
        }

        $this->logAudit(
            request: $request,
            action: 'User created',
            resource: sprintf('%s (%s)', $user->name, $user->role),
            propertyName: $assignedPropertyName ?? 'All',
            category: 'user',
            propertyId: !empty($data['property_id']) ? (int) $data['property_id'] : null,
        );

        $emailWarning = null;
        try {
            Mail::to($user->email)->send(new ManagerWelcomeMail(
                managerName: $user->name,
                email: $user->email,
                initialPassword: $initialPassword,
                loginUrl: url('/login'),
                propertyName: $assignedPropertyName,
            ));
        } catch (\Throwable $e) {
            $emailWarning = 'User created, but welcome email failed to send. Check your mail configuration.';
            \Illuminate\Support\Facades\Log::error('ManagerWelcomeMail failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);
        }

        $message = $emailWarning ?? 'User created successfully. A welcome email has been sent.';
        return back()->with($emailWarning ? 'warning' : 'success', $message);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }

    public function updateSettings(Request $request)
    {
        $allowed = [
            'company_name', 'company_registration', 'vat_number', 'default_currency',
            'default_country', 'support_email', 'min_lease_months',
            'deposit_rent_months', 'deposit_service_charge_months',
            'late_fee_days', 'late_fee_percent', 'expiry_warning_days', 'auto_renew',
            'notif_new_property', 'notif_manager_changes', 'notif_failed_logins',
            'notif_lease_approved', 'notif_overdue_rent', 'notif_system_errors',
            'require_2fa', 'allow_sso', 'session_timeout', 'audit_logging', 'failed_login_alerts',
        ];

        foreach ($allowed as $key) {
            if ($request->has($key)) {
                SystemSetting::set($key, $request->input($key));
            }
        }

        $this->logAudit(
            request: $request,
            action: 'Settings updated',
            resource: 'System settings',
            propertyName: 'All',
            category: 'settings',
        );

        return back()->with('success', 'Settings saved.');
    }

    public function updateRoles(Request $request)
    {
        $request->validate(['permissions' => 'required|array']);

        SystemSetting::set('role_permissions', json_encode($request->permissions));

        $this->logAudit(
            request: $request,
            action: 'Role permissions updated',
            resource: 'Roles & Permissions',
            propertyName: 'All',
            category: 'settings',
        );

        return back()->with('success', 'Role permissions saved.');
    }

    // ── Property View ────────────────────────────────────────────────

    public function enterPropertyView(Property $property)
    {
        abort_if(Auth::user()->role !== 'superuser', 403);
        session(['superuser_viewing_property_id' => $property->id]);
        return redirect('/');
    }

    public function exitPropertyView()
    {
        session()->forget('superuser_viewing_property_id');
        return redirect()->route('superuser.index');
    }

    // ── Lease Approvals ──────────────────────────────────────────────

    public function approveLease(Request $request, Lease $lease)
    {
        abort_if(Auth::user()->role !== 'superuser', 403);
        abort_if(!in_array($lease->status, ['pending_accountant', 'pending_pm']), 422, 'Lease is not pending approval.');

        $message = trim($request->input('message', 'Approved by superuser.')) ?: 'Approved by superuser.';
        $actor   = Auth::user()->name;

        $log   = json_decode($lease->approval_log ?? '[]', true);
        $log[] = [
            'step'    => count($log) + 1,
            'action'  => 'approved',
            'by'      => $actor . ' (Superuser)',
            'date'    => now()->format('d M Y'),
            'text'    => $message,
        ];
        $lease->update(['status' => 'active', 'approval_log' => json_encode($log)]);

        app(LeaseController::class)->ensureInstallmentsGenerated($lease->fresh());

        // Notify the property manager
        $manager = $lease->unit?->property?->manager;
        if ($manager) {
            $manager->notify(new LeaseDecisionNotification($lease->fresh(), 'approved', $message, $actor));
        }

        $propertyName = $lease->unit?->property?->name;
        $this->logAudit(
            request: $request,
            action: 'Lease approved',
            resource: $lease->lease_number ?? ('Lease #' . $lease->id),
            propertyName: $propertyName,
            category: 'lease',
            propertyId: $lease->unit?->property_id ? (int) $lease->unit->property_id : null,
        );

        return back()->with('success', 'Lease approved.');
    }

    public function rejectLease(Request $request, Lease $lease)
    {
        abort_if(Auth::user()->role !== 'superuser', 403);

        $data = $request->validate([
            'message' => 'required|string|min:5|max:1000',
        ]);

        $actor = Auth::user()->name;

        $log   = json_decode($lease->approval_log ?? '[]', true);
        $log[] = [
            'action' => 'rejected',
            'by'     => $actor . ' (Superuser)',
            'date'   => now()->format('d M Y'),
            'reason' => $data['message'],
        ];
        $lease->update(['status' => 'rejected', 'approval_log' => json_encode($log)]);

        // Notify the property manager
        $manager = $lease->unit?->property?->manager;
        if ($manager) {
            $manager->notify(new LeaseDecisionNotification($lease->fresh(), 'rejected', $data['message'], $actor));
        }

        $propertyName = $lease->unit?->property?->name;
        $this->logAudit(
            request: $request,
            action: 'Lease rejected',
            resource: $lease->lease_number ?? ('Lease #' . $lease->id),
            propertyName: $propertyName,
            category: 'lease',
            propertyId: $lease->unit?->property_id ? (int) $lease->unit->property_id : null,
        );

        return back()->with('success', 'Lease rejected.');
    }

    // ── Maintenance Approvals ────────────────────────────────────────

    public function approveMaintenance(Request $request, MaintenanceRecord $ticket)
    {
        abort_if(Auth::user()->role !== 'superuser', 403);

        $message = trim($request->input('message', 'Approved by superuser.')) ?: 'Approved by superuser.';
        $actor   = Auth::user()->name;

        $ticket->update(['workflow_status' => 'approved', 'status' => 'open']);

        $freshTicket = $ticket->fresh();

        // Notify the property manager
        $manager = $ticket->unit?->property?->manager ?? $ticket->property?->manager;
        if ($manager) {
            $manager->notify(new MaintenanceDecisionNotification($freshTicket, 'approved', $message, $actor));
        }

        // Notify the reporter (in-app + email)
        $reporter = User::where('name', $freshTicket->reported_by)->first();
        if ($reporter && $reporter->id !== $manager?->id) {
            $reporter->notify(new MaintenanceApprovalNotification($freshTicket, 'approved'));
        }

        $propertyName = $ticket->unit?->property?->name ?? Property::where('id', $ticket->property_id)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Maintenance ticket approved',
            resource: $ticket->title,
            propertyName: $propertyName,
            category: 'maintenance',
            propertyId: $ticket->property_id ? (int) $ticket->property_id : null,
        );

        return back()->with('success', 'Maintenance ticket approved.');
    }

    public function rejectMaintenance(Request $request, MaintenanceRecord $ticket)
    {
        abort_if(Auth::user()->role !== 'superuser', 403);

        $data = $request->validate([
            'message' => 'required|string|min:5|max:1000',
        ]);

        $actor = Auth::user()->name;

        $ticket->update(['workflow_status' => 'rejected', 'status' => 'open']);

        // Notify the property manager
        $manager = $ticket->unit?->property?->manager ?? $ticket->property?->manager;
        if ($manager) {
            $manager->notify(new MaintenanceDecisionNotification($ticket->fresh(), 'rejected', $data['message'], $actor));
        }

        $propertyName = $ticket->unit?->property?->name ?? Property::where('id', $ticket->property_id)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Maintenance ticket rejected',
            resource: $ticket->title,
            propertyName: $propertyName,
            category: 'maintenance',
            propertyId: $ticket->property_id ? (int) $ticket->property_id : null,
        );

        return back()->with('success', 'Maintenance ticket rejected.');
    }

    // ── Notifications ───────────────────────────────────────────────

    public function getNotifications(): \Illuminate\Http\JsonResponse
    {
        abort_if(Auth::user()->role !== 'superuser', 403);

        $notifications = Auth::user()->notifications()
            ->latest()
            ->take(20)
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'data'       => $n->data,
                'read_at'    => $n->read_at,
                'created_at' => $n->created_at,
            ]);

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => Auth::user()->unreadNotifications()->count(),
        ]);
    }

    public function markNotificationsRead(): \Illuminate\Http\JsonResponse
    {
        abort_if(Auth::user()->role !== 'superuser', 403);
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function clearNotifications(): \Illuminate\Http\JsonResponse
    {
        abort_if(Auth::user()->role !== 'superuser', 403);
        Auth::user()->notifications()->delete();
        return response()->json(['ok' => true]);
    }

    // ── Manager Delete / Restore ─────────────────────────────────────

    public function deleteManager(Request $request, User $user): \Illuminate\Http\RedirectResponse
    {
        abort_if($user->role === 'superuser', 403);

        $data = $request->validate(['confirm_name' => 'required|string|max:120']);

        if (trim($data['confirm_name']) !== $user->name) {
            return back()->withErrors(['confirm_name' => 'Name confirmation does not match.']);
        }

        $propertyName = null;
        $propertyId   = $user->property_id ? (int) $user->property_id : null;

        if ($propertyId) {
            $propertyName = Property::where('id', $propertyId)->value('name');
            Property::where('manager_user_id', $user->id)->update(['manager_user_id' => null]);
        }

        $resource = sprintf('%s (%s)', $user->name, $user->role);
        $user->delete();

        $this->logAudit(
            request: $request,
            action: 'Manager removed',
            resource: $resource,
            propertyName: $propertyName,
            category: 'team',
            metadata: ['soft_deleted' => true],
            propertyId: $propertyId,
        );

        return back()->with('success', "{$user->name} has been removed.");
    }

    public function restoreManager(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $user = User::onlyTrashed()
            ->whereIn('role', ['manager', 'accountant', 'viewer'])
            ->findOrFail($id);

        $user->restore();
        $user->update(['status' => 'active']);

        $propertyName = $user->property_id ? Property::where('id', $user->property_id)->value('name') : null;

        $this->logAudit(
            request: $request,
            action: 'Manager restored',
            resource: sprintf('%s (%s)', $user->name, $user->role),
            propertyName: $propertyName,
            category: 'team',
            metadata: ['restored' => true],
            propertyId: $user->property_id ? (int) $user->property_id : null,
        );

        return back()->with('success', "{$user->name} has been restored.");
    }

    public function ownerRevenue(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_if($request->user()->role !== 'superuser', 403);

        $propertyId = $request->filled('property_id') ? (int) $request->input('property_id') : null;
        [$start, $end] = $this->resolveRevenuePeriod($request);
        $feeRate = (float) SystemSetting::get('management_fee_rate', 0) / 100;

        // GL-backed helper: sum debit or credit for an account code within the period
        $glSum = function (string $accountCode, string $side, ?int $pid = null, ?string $refPattern = null) use ($start, $end): float {
            return (float) DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_entries.status', 'posted')
                ->where('journal_lines.account_code', $accountCode)
                ->whereBetween('journal_entries.entry_date', [$start, $end])
                ->when($pid !== null, fn($q) => $q->where('journal_entries.property_id', $pid))
                ->when($refPattern !== null, fn($q) => $q->where('journal_entries.reference', 'like', $refPattern))
                ->sum("journal_lines.{$side}");
        };

        // Waterfall sourced from GL accounts
        $gross       = $glSum('1000', 'debit',  $propertyId, 'PAY-%');
        $wht         = $glSum('1120', 'debit',  $propertyId);
        $vat         = $glSum('2200', 'credit', $propertyId);
        $sc          = $glSum('4100', 'credit', $propertyId);
        $netRent     = $glSum('4000', 'credit', $propertyId);
        $maintenance = $glSum('5000', 'debit',  $propertyId);
        $mgmtFee     = $glSum('5500', 'debit',  $propertyId);

        // Fuel: account 5200 if posted; fall back to raw FuelLog for legacy/unposted logs
        $fuel = $glSum('5200', 'debit', $propertyId);
        if ($fuel <= 0) {
            $fuel = (float) FuelLog::query()
                ->when($propertyId, fn($q) => $q->where('property_id', $propertyId))
                ->whereBetween('log_date', [$start, $end])
                ->sum('total_cost');
        }

        $net = $netRent - $maintenance - $fuel - $mgmtFee;

        // Per-property breakdown
        $properties = Property::with('manager:id,name')->orderBy('name')->get(['id', 'name'])
            ->map(function ($prop) use ($start, $end, $feeRate, $glSum) {
                $pid   = $prop->id;
                $g     = $glSum('1000', 'debit',  $pid, 'PAY-%');
                $maint = $glSum('5000', 'debit',  $pid);
                $fee   = $glSum('5500', 'debit',  $pid);
                if ($fee <= 0) {
                    $fee = round($g * $feeRate, 2);
                }
                $fl = $glSum('5200', 'debit', $pid);
                if ($fl <= 0) {
                    $fl = (float) FuelLog::where('property_id', $pid)
                        ->whereBetween('log_date', [$start, $end])
                        ->sum('total_cost');
                }
                return [
                    'id'          => $pid,
                    'name'        => $prop->name,
                    'manager'     => $prop->manager?->name,
                    'gross'       => $g,
                    'maintenance' => $maint,
                    'fuel'        => $fl,
                    'fee'         => $fee,
                    'net'         => round($g - $maint - $fl - $fee, 2),
                ];
            });

        return response()->json([
            'period'     => ['from' => $start, 'to' => $end],
            'waterfall'  => compact('gross', 'wht', 'vat', 'sc', 'netRent', 'maintenance', 'fuel', 'mgmtFee', 'net'),
            'fee_rate'   => $feeRate * 100,
            'properties' => $properties,
        ]);
    }

    /**
     * Post management fee GL entry for a given period and property (or all properties).
     *
     * POST /superuser/revenue/post-fee
     * Body: { period, property_id? }
     *   period      — same preset values as ownerRevenue (this_month, last_month, …)
     *   property_id — optional; if omitted, posts fee for all properties separately
     */
    public function postManagementFee(Request $request, AccountingService $accounting): \Illuminate\Http\JsonResponse
    {
        abort_if($request->user()->role !== 'superuser', 403);

        $propertyId = $request->filled('property_id') ? (int) $request->input('property_id') : null;
        [$start, $end] = $this->resolveRevenuePeriod($request);
        $feeRate  = (float) SystemSetting::get('management_fee_rate', 0) / 100;
        $payExpr  = 'COALESCE(amount_in_base, amount * COALESCE(exchange_rate, 1))';

        // Derive a clean period key from the date range (e.g. "2026-03")
        $periodKey = Carbon::parse($start)->format('Y-m');

        $posted = [];

        $query = Property::query()->orderBy('name');
        if ($propertyId) {
            $query->where('id', $propertyId);
        }

        foreach ($query->get(['id', 'name']) as $property) {
            $gross = (float) Payment::query()
                ->where('property_id', $property->id)
                ->where('status', 'paid')
                ->whereBetween('paid_date', [$start, $end])
                ->sum(DB::raw($payExpr));

            $fee = round($gross * $feeRate, 2);
            if ($fee <= 0) {
                continue;
            }

            $entry = $accounting->postManagementFee(
                propertyId: $property->id,
                amount: $fee,
                period: $periodKey,
                entryDate: $end,
            );

            $posted[] = [
                'property_id'   => $property->id,
                'property_name' => $property->name,
                'amount'        => $fee,
                'journal_entry' => $entry->entry_number,
            ];
        }

        if (empty($posted)) {
            return response()->json(['message' => 'No management fee to post for this period.'], 200);
        }

        return response()->json([
            'message' => count($posted) . ' management fee entr' . (count($posted) === 1 ? 'y' : 'ies') . ' posted.',
            'entries' => $posted,
        ]);
    }

    private function resolveRevenuePeriod(Request $request): array
    {
        $preset = $request->input('period', 'this_month');
        $now    = Carbon::now();
        return match ($preset) {
            'this_month'   => [$now->copy()->startOfMonth()->toDateString(),             $now->copy()->endOfMonth()->toDateString()],
            'last_month'   => [$now->copy()->subMonth()->startOfMonth()->toDateString(), $now->copy()->subMonth()->endOfMonth()->toDateString()],
            'this_quarter' => [$now->copy()->startOfQuarter()->toDateString(),           $now->copy()->endOfQuarter()->toDateString()],
            'last_quarter' => [$now->copy()->subQuarter()->startOfQuarter()->toDateString(), $now->copy()->subQuarter()->endOfQuarter()->toDateString()],
            'this_year'    => [$now->copy()->startOfYear()->toDateString(),              $now->copy()->endOfYear()->toDateString()],
            'custom'       => [
                $request->input('from', $now->copy()->startOfMonth()->toDateString()),
                $request->input('to',   $now->toDateString()),
            ],
            default        => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()],
        };
    }

}
