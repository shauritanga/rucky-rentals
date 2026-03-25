<?php

namespace App\Http\Controllers;

use App\Mail\TeamInviteMail;
use App\Models\Property;
use App\Models\User;
use App\Traits\LogsAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class TeamController extends Controller
{
    use LogsAudit;
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

        $teamMembers = $this->teamQueryFor($user)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'property_id', 'status', 'permissions', 'created_at', 'updated_at'])
            ->map(fn(User $member) => $this->mapTeamMember($member))
            ->values();

        $archivedMembers = $this->teamQueryFor($user, true)
            ->orderByDesc('deleted_at')
            ->get(['id', 'name', 'email', 'role', 'property_id', 'status', 'permissions', 'created_at', 'updated_at', 'deleted_at'])
            ->map(fn(User $member) => $this->mapTeamMember($member))
            ->values();

        return Inertia::render('Team/Index', [
            'teamMembers' => $teamMembers,
            'archivedMembers' => $archivedMembers,
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
            'permissions' => 'nullable|array',
        ]);

        $propertyId = $this->resolvePropertyIdForActor($actor);

        $initialPassword = $data['password'];

        $permissions = !empty($data['permissions'])
            ? $data['permissions']
            : (self::ROLE_DEFAULTS[$data['role']] ?? []);

        $member = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($initialPassword),
            'role' => $data['role'],
            'property_id' => $propertyId,
            'status' => 'active',
            'must_change_password' => true,
            'permissions' => $permissions,
        ]);

        $propertyName = $propertyId ? Property::where('id', $propertyId)->value('name') : null;
        $inviteMailSent = true;

        try {
            Mail::to($member->email)->send(new TeamInviteMail(
                memberName: $member->name,
                email: $member->email,
                initialPassword: $initialPassword,
                loginUrl: url('/login'),
                roleLabel: $this->roleLabel($member->role),
                propertyName: $propertyName,
            ));
        } catch (\Throwable $e) {
            $inviteMailSent = false;
        }

        $this->logAudit(
            request: $request,
            action: 'Team member added',
            resource: sprintf('%s (%s)', $member->name, $member->role),
            propertyName: $propertyName,
            category: 'team',
            result: $inviteMailSent ? 'success' : 'failed',
            metadata: [
                'invite_email_sent' => $inviteMailSent,
                'invite_email' => $member->email,
            ],
            propertyId: $member->property_id ? (int) $member->property_id : null,
        );

        if (!$inviteMailSent) {
            return back()->with('warning', 'Staff member created, but invite email could not be sent.');
        }

        return back()->with('success', 'Staff member added and invite email sent.');
    }

    public function updatePermissions(Request $request, User $user)
    {
        $this->authorizeTeamMember($request, $user);

        $data = $request->validate([
            'permissions' => 'required|array',
        ]);

        $user->update(['permissions' => $data['permissions']]);

        $this->logAudit(
            request: $request,
            action: 'Permissions updated',
            resource: sprintf('%s (%s)', $user->name, $user->role),
            propertyName: $user->property_id ? Property::where('id', $user->property_id)->value('name') : null,
            category: 'team',
            metadata: ['permissions' => $data['permissions']],
            propertyId: $user->property_id ? (int) $user->property_id : null,
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
            propertyId: $user->property_id ? (int) $user->property_id : null,
        );

        return back()->with('success', sprintf('Member %s.', $nextStatus === 'active' ? 'activated' : 'suspended'));
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorizeTeamMember($request, $user);

        $data = $request->validate([
            'confirm_name' => 'required|string|max:120',
        ]);

        if (trim($data['confirm_name']) !== $user->name) {
            return back()->withErrors([
                'confirm_name' => 'Name confirmation does not match the selected team member.',
            ]);
        }

        $propertyName = $user->property_id ? Property::where('id', $user->property_id)->value('name') : null;
        $resource = sprintf('%s (%s)', $user->name, $user->role);

        $user->delete();

        $this->logAudit(
            request: $request,
            action: 'Team member removed',
            resource: $resource,
            propertyName: $propertyName,
            category: 'team',
            metadata: ['soft_deleted' => true],
            propertyId: $user->property_id ? (int) $user->property_id : null,
        );

        return back()->with('success', 'Staff member removed.');
    }

    public function restore(Request $request, int $userId)
    {
        $member = User::onlyTrashed()->whereIn('role', self::STAFF_ROLES)->findOrFail($userId);
        $this->authorizeTeamMember($request, $member);

        $member->restore();
        $member->update(['status' => 'active']);

        $propertyName = $member->property_id ? Property::where('id', $member->property_id)->value('name') : null;
        $this->logAudit(
            request: $request,
            action: 'Team member restored',
            resource: sprintf('%s (%s)', $member->name, $member->role),
            propertyName: $propertyName,
            category: 'team',
            metadata: ['restored' => true],
            propertyId: $member->property_id ? (int) $member->property_id : null,
        );

        return back()->with('success', 'Staff member restored.');
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

    private function teamQueryFor(?User $user, bool $onlyTrashed = false)
    {
        $query = $onlyTrashed ? User::onlyTrashed() : User::query();
        $query->whereIn('role', self::STAFF_ROLES);

        if ($user?->role === 'manager') {
            if (empty($user->property_id)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('property_id', $user->property_id);
            }
        }

        return $query;
    }

    private function mapTeamMember(User $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'phone' => null,
            'role' => $member->role,
            'status' => $member->status ?: 'active',
            'last_active' => optional($member->updated_at)->diffForHumans(),
            'deleted_at' => $member->deleted_at ? $member->deleted_at->diffForHumans() : null,
            'permissions' => $member->permissions ?? self::ROLE_DEFAULTS[$member->role] ?? self::ROLE_DEFAULTS['viewer'],
        ];
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'accountant' => 'Accountant',
            'lease_manager' => 'Lease Manager',
            'maintenance_staff' => 'Maintenance Staff',
            'viewer' => 'Viewer',
            default => ucfirst(str_replace('_', ' ', $role)),
        };
    }

}
