<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Return the latest 20 notifications for the authenticated user.
     * Used by non-superuser roles (manager, accountant, staff, etc.).
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        abort_if(!$user, 401);

        $notifications = $user->notifications()
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
            'unread_count'  => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(): \Illuminate\Http\JsonResponse
    {
        Auth::user()?->unreadNotifications()->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }
}
