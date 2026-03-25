<?php

namespace App\Http\Controllers;

use App\Mail\ManagerWelcomeMail;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Models\User;
use App\Traits\LogsAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
                'units as occupied_units_live' => fn($q) => $q->whereIn('status', ['occupied', 'overdue']),
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($property) {
                $property->unit_count = (int) ($property->unit_count_live ?? 0);
                $property->occupied_units = (int) ($property->occupied_units_live ?? 0);
                return $property;
            });

        $managers = User::query()
            ->whereIn('role', ['manager', 'superuser'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'property_id']);

        $auditLogs = AuditLog::query()
            ->latest()
            ->limit(200)
            ->get();

        $settings = SystemSetting::pluck('value', 'key');

        return Inertia::render('Superuser/Index', [
            'properties' => $properties,
            'managers'   => $managers,
            'auditLogs'  => $auditLogs,
            'settings'   => $settings,
        ]);
    }

    public function storeProperty(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:120',
            'country' => 'nullable|string|max:120',
            'status' => 'required|in:active,trial,inactive',
            'unit_count' => 'nullable|integer|min:0',
            'occupied_units' => 'nullable|integer|min:0',
            'total_floors' => 'nullable|integer|min:1',
            'manager_user_id' => 'nullable|exists:users,id',
        ]);

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
            'property_id' => $data['role'] === 'manager' ? ($data['property_id'] ?? null) : null,
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
        }

        $this->logAudit(
            request: $request,
            action: 'User created',
            resource: sprintf('%s (%s)', $user->name, $user->role),
            propertyName: $assignedPropertyName ?? 'All',
            category: 'user',
            propertyId: ($data['role'] === 'manager' && !empty($data['property_id'])) ? (int) $data['property_id'] : null,
        );

        if ($data['role'] === 'manager') {
            Mail::to($user->email)->send(new ManagerWelcomeMail(
                managerName: $user->name,
                email: $user->email,
                initialPassword: $initialPassword,
                loginUrl: url('/login'),
                propertyName: $assignedPropertyName,
            ));
        }

        return back()->with('success', 'User created successfully.');
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
            'default_country', 'support_email', 'min_lease_months', 'deposit_multiplier',
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

}
