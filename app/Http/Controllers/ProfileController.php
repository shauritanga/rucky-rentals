<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()?->isSuperuser()) {
            return Inertia::render('Superuser/Profile');
        }

        return Inertia::render('Profile');
    }

    public function superuser(Request $request)
    {
        abort_unless($request->user()?->isSuperuser(), 403);

        return Inertia::render('Superuser/Profile');
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
}
