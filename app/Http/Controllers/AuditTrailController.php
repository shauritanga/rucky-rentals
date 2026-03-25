<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditTrailController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = AuditLog::query()->latest();

        if ($user?->role === 'manager') {
            if (empty($user->property_id)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($user) {
                    $q->where('property_id', $user->property_id)
                        ->orWhere('user_id', $user->id);
                });
            }
        }

        $auditLogs = $query
            ->limit(500)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'ts' => optional($log->created_at)->format('Y-m-d H:i'),
                    'user' => $log->user_name ?? 'Unknown',
                    'action' => $log->action,
                    'resource' => $log->resource ?? '—',
                    'module' => $log->category ?? 'settings',
                    'ip' => $log->ip_address ?? '—',
                    'result' => $log->result ?? 'success',
                ];
            })
            ->values();

        return Inertia::render('Audit/Index', [
            'auditLogs' => $auditLogs,
        ]);
    }
}
