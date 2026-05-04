<?php

namespace App\Http\Controllers;

use App\Mail\TeamInviteMail;
use App\Notifications\TeamApprovalRequestNotification;
use App\Models\Property;
use App\Models\User;
use App\Traits\LogsAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
            ->get([
                'id', 'name', 'email', 'phone', 'role', 'property_id', 'requested_by_user_id',
                'status', 'permissions', 'created_at', 'updated_at', 'approval_requested_at',
                'approval_decided_at', 'approval_note',
            ])
            ->map(fn(User $member) => $this->mapTeamMember($member))
            ->values();

        $archivedMembers = $this->teamQueryFor($user, true)
            ->orderByDesc('deleted_at')
            ->get([
                'id', 'name', 'email', 'phone', 'role', 'property_id', 'requested_by_user_id',
                'status', 'permissions', 'created_at', 'updated_at', 'deleted_at',
                'approval_requested_at', 'approval_decided_at', 'approval_note',
            ])
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
            'permissions' => 'nullable|array',
        ]);

        abort_if($data['role'] === 'maintenance_staff' && ! $actor?->isSuperuser(), 403, 'Only superuser can create maintenance staff.');

        $propertyId = $this->resolvePropertyIdForActor($actor);
        $propertyName = $propertyId ? Property::where('id', $propertyId)->value('name') : null;

        $permissions = !empty($data['permissions'])
            ? $data['permissions']
            : (self::ROLE_DEFAULTS[$data['role']] ?? []);

        $isPendingApproval = !$actor?->isSuperuser();
        $initialPassword = Str::password(12);

        $member = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($initialPassword),
            'role' => $data['role'],
            'property_id' => $propertyId,
            'requested_by_user_id' => $actor?->id,
            'status' => $isPendingApproval ? 'pending_approval' : 'active',
            'must_change_password' => true,
            'permissions' => $permissions,
            'approval_requested_at' => $isPendingApproval ? now() : null,
            'approval_decided_at' => $isPendingApproval ? null : now(),
            'approval_note' => null,
        ]);

        if ($isPendingApproval) {
            $superusers = User::query()->where('role', 'superuser')->get();
            foreach ($superusers as $superuser) {
                $superuser->notify(new TeamApprovalRequestNotification(
                    member: $member,
                    submittedBy: $actor?->name ?? 'System',
                    propertyName: $propertyName,
                ));
            }

            $this->logAudit(
                request: $request,
                action: 'Team approval requested',
                resource: sprintf('%s (%s)', $member->name, $member->role),
                propertyName: $propertyName,
                category: 'team',
                metadata: [
                    'member_id' => $member->id,
                    'member_email' => $member->email,
                    'requested_by' => $actor?->name,
                ],
                propertyId: $member->property_id ? (int) $member->property_id : null,
            );

            return back()->with('success', 'Staff member submitted for superuser approval.');
        }

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

    public function resubmit(Request $request, User $user)
    {
        $this->authorizeTeamMember($request, $user);
        abort_unless($user->status === 'rejected', 422, 'Only rejected team requests can be resubmitted.');

        $actor = $request->user();
        $propertyName = $user->property_id ? Property::where('id', $user->property_id)->value('name') : null;

        $user->update([
            'status' => 'pending_approval',
            'approval_requested_at' => now(),
            'approval_decided_at' => null,
            'approval_note' => null,
            'requested_by_user_id' => $actor?->id,
        ]);

        $superusers = User::query()->where('role', 'superuser')->get();
        foreach ($superusers as $superuser) {
            $superuser->notify(new TeamApprovalRequestNotification(
                member: $user->fresh(),
                submittedBy: $actor?->name ?? 'System',
                propertyName: $propertyName,
            ));
        }

        $this->logAudit(
            request: $request,
            action: 'Team approval request resubmitted',
            resource: sprintf('%s (%s)', $user->name, $user->role),
            propertyName: $propertyName,
            category: 'team',
            metadata: ['member_id' => $user->id],
            propertyId: $user->property_id ? (int) $user->property_id : null,
        );

        return back()->with('success', 'Team approval request resubmitted.');
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
        $query = ($onlyTrashed ? User::onlyTrashed() : User::query())
            ->with('requestedBy:id,name');
        $query->whereIn('role', self::STAFF_ROLES);

        if ($user?->role === 'manager') {
            if (empty($user->property_id)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($user) {
                    $q->where('property_id', $user->property_id)
                        ->orWhere(function ($globalStaff) {
                            $globalStaff
                                ->where('role', 'maintenance_staff')
                                ->whereNull('property_id');
                        });
                });
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
            'phone' => $member->phone,
            'role' => $member->role,
            'property_id' => $member->property_id,
            'global_access' => $member->role === 'maintenance_staff' && empty($member->property_id),
            'status' => $member->status ?: 'active',
            'last_active' => optional($member->updated_at)->diffForHumans(),
            'deleted_at' => $member->deleted_at ? $member->deleted_at->diffForHumans() : null,
            'requested_by' => $member->relationLoaded('requestedBy') ? $member->requestedBy?->name : null,
            'approval_note' => $member->approval_note,
            'approval_requested_at' => $member->approval_requested_at,
            'approval_decided_at' => $member->approval_decided_at,
            'permissions' => $member->permissions ?? self::ROLE_DEFAULTS[$member->role] ?? self::ROLE_DEFAULTS['viewer'],
        ];
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'accountant' => 'Accountant',
            'lease_manager' => 'Lease Assistant',
            'maintenance_staff' => 'Maintenance Staff',
            'viewer' => 'Viewer',
            default => ucfirst(str_replace('_', ' ', $role)),
        };
    }

}
