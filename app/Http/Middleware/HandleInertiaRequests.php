<?php

namespace App\Http\Middleware;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
                    'role'                 => $request->user()->role,
                    'must_change_password' => (bool) $request->user()->must_change_password,
                    'avatar_url'           => $request->user()->avatar
                                                ? Storage::url($request->user()->avatar)
                                                : null,
                ] : null,
            ],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'created_invoice_id' => fn() => $request->session()->get('created_invoice_id'),
            ],
            'viewing_property' => fn() => (
                $request->user()?->role === 'superuser' && $request->session()->get('superuser_viewing_property_id')
                    ? Property::find($request->session()->get('superuser_viewing_property_id'), ['id', 'name'])
                    : null
            ),
        ];
    }
}
