<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class TeamController extends Controller
{
    private const STAFF_ROLES = ['accountant', 'lease_manager', 'maintenance_staff', 'viewer'];

    private const ROLE_DEFAULTS = [
        'accountant' => [
            'dashboard' => true,
            'units' => false,
            'tenants' => true,
            'leases' => true,
            'payments' => true,
            'invoices' => true,
            'maintenance' => false,
            'documents' => true,
            'electricity' => false,
            'accounting' => true,
            'reports' => true,
            'team' => false,
        ],
        'lease_manager' => [
            'dashboard' => true,
            'units' => true,
            'tenants' => true,
            'leases' => true,
            'payments' => false,
            'invoices' => true,
            'maintenance' => false,
            'documents' => true,
            'electricity' => false,
            'accounting' => false,
            'reports' => true,
            'team' => false,
        ],
        'maintenance_staff' => [
            'dashboard' => true,
            'units' => true,
            'tenants' => false,
            'leases' => false,
            'payments' => false,
            'invoices' => false,
            'maintenance' => true,
            'documents' => false,
            'electricity' => true,
            'accounting' => false,
            'reports' => false,
            'team' => false,
        ],
        'viewer' => [
            'dashboard' => true,
            'units' => true,
            'tenants' => true,
            'leases' => false,
            'payments' => false,
            'invoices' => false,
            'maintenance' => false,
            'documents' => false,
            'electricity' => false,
            'accounting' => false,
            'reports' => true,
            'team' => false,
        ],
    ];

    public function index(Request $request)
    {
        $user = $request->user();

        $query = User::query()->whereIn('role', self::STAFF_ROLES);

        if ($user?->role === 'manager') {
            if (empty($user->property_id)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('property_id', $user->property_id);
            }
        }

        $teamMembers = $query
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'property_id', 'status', 'created_at', 'updated_at'])
            ->map(function (User $member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'phone' => null,
                    'role' => $member->role,
                    'status' => $member->status ?: 'active',
                    'last_active' => optional($member->updated_at)->diffForHumans(),
                    'permissions' => self::ROLE_DEFAULTS[$member->role] ?? self::ROLE_DEFAULTS['viewer'],
                ];
            })
            ->values();

        return Inertia::render('Team/Index', [
            'teamMembers' => $teamMembers,
            'roleDefaults' => self::ROLE_DEFAULTS,
        ]);
    }

    public function store(Request $request)
    {
        $actor = $request->user();

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:120|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'role' => 'required|in:accountant,lease_manager,maintenance_staff,viewer',
            'password' => 'required|string|min:8',
        ]);

        $propertyId = $this->resolvePropertyIdForActor($actor);

        $member = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'property_id' => $propertyId,
            'status' => 'active',
            'must_change_password' => true,
        ]);

        $propertyName = $propertyId ? Property::where('id', $propertyId)->value('name') : null;
        $this->logAudit(
            request: $request,
            action: 'Team member added',
            resource: sprintf('%s (%s)', $member->name, $member->role),
            propertyName: $propertyName,
            category: 'team',
        );

        return back()->with('success', 'Staff member added successfully.');
    }

    public function updatePermissions(Request $request, User $user)
    {
        $this->authorizeTeamMember($request, $user);

        $data = $request->validate([
            'permissions' => 'required|array',
        ]);

        $this->logAudit(
            request: $request,
            action: 'Permissions updated',
            resource: sprintf('%s (%s)', $user->name, $user->role),
            propertyName: $user->property_id ? Property::where('id', $user->property_id)->value('name') : null,
            category: 'team',
            metadata: ['permissions' => $data['permissions']],
        );

        return back()->with('success', 'Permissions updated.');
    }

    public function toggleStatus(Request $request, User $user)
    {
        $this->authorizeTeamMember($request, $user);

        $nextStatus = $user->status === 'suspended' ? 'active' : 'suspended';
        $user->update(['status' => $nextStatus]);

        $this->logAudit(
            request: $request,
            action: $nextStatus === 'active' ? 'Team member activated' : 'Team member suspended',
            resource: sprintf('%s (%s)', $user->name, $user->role),
            propertyName: $user->property_id ? Property::where('id', $user->property_id)->value('name') : null,
            category: 'team',
        );

        return back()->with('success', sprintf('Member %s.', $nextStatus === 'active' ? 'activated' : 'suspended'));
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorizeTeamMember($request, $user);

        $propertyName = $user->property_id ? Property::where('id', $user->property_id)->value('name') : null;
        $resource = sprintf('%s (%s)', $user->name, $user->role);

        $user->delete();

        $this->logAudit(
            request: $request,
            action: 'Team member removed',
            resource: $resource,
            propertyName: $propertyName,
            category: 'team',
        );

        return back()->with('success', 'Staff member removed.');
    }

    private function resolvePropertyIdForActor(?User $actor): ?int
    {
        if (!$actor) {
            return null;
        }

        if ($actor->role === 'manager') {
            abort_if(empty($actor->property_id), 422, 'Manager is not assigned to any property.');
            return (int) $actor->property_id;
        }

        return $actor->property_id ? (int) $actor->property_id : null;
    }

    private function authorizeTeamMember(Request $request, User $teamMember): void
    {
        abort_unless(in_array($teamMember->role, self::STAFF_ROLES, true), 404);

        $actor = $request->user();
        if ($actor?->role === 'manager') {
            abort_if((int) $teamMember->property_id !== (int) $actor->property_id, 403);
        }
    }

    private function logAudit(Request $request, string $action, ?string $resource, ?string $propertyName, string $category, string $result = 'success', array $metadata = []): void
    {
        $actor = $request->user();

        AuditLog::create([
            'user_id' => $actor?->id,
            'user_name' => $actor?->name ?? 'System',
            'action' => $action,
            'resource' => $resource,
            'property_name' => $propertyName,
            'ip_address' => $request->ip(),
            'result' => $result,
            'category' => $category,
            'metadata' => empty($metadata) ? null : $metadata,
        ]);
    }
}
