<?php

namespace App\Http\Middleware;

use App\Models\Property;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id'                   => $request->user()->id,
                    'name'                 => $request->user()->name,
                    'email'                => $request->user()->email,
                    'phone'                => $request->user()->phone,
                    'bio'                  => $request->user()->bio,
                    'role'                 => $request->user()->role,
                    'property_id'          => $request->user()->property_id,
                    'property_name'        => $request->user()->property_id
                                                ? Property::find($request->user()->property_id)?->name
                                                : null,
                    'must_change_password' => (bool) $request->user()->must_change_password,
                    'avatar_url'           => $request->user()->avatar
                                                ? '/storage/' . $request->user()->avatar
                                                : null,
                    'session_timeout_minutes' => EnforceSessionTimeout::timeoutMinutesFromSettings(),
                ] : null,
            ],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error'   => fn() => $request->session()->get('error'),
                'warning' => fn() => $request->session()->get('warning'),
                'created_invoice_id' => fn() => $request->session()->get('created_invoice_id'),
            ],
            'notifications_unread' => fn() => $request->user()?->unreadNotifications()->count() ?? 0,
            // One-shot flag: true only on the first page load right after login, then
            // consumed (pull removes it from the session) — drives the due-invoices
            // popup showing once per login rather than once per calendar day.
            'just_logged_in' => fn() => (bool) $request->session()->pull('just_logged_in', false),
            'viewing_property' => fn() => (
                $request->user()?->role === 'superuser' && $request->session()->get('superuser_viewing_property_id')
                    ? Property::find($request->session()->get('superuser_viewing_property_id'), ['id', 'name'])
                    : null
            ),
        ];
    }
}
