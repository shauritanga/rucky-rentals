<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($request->user()->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'bio'   => ['nullable', 'string', 'max:1000'],
        ]);

        $request->user()->update($request->only('name', 'email', 'phone', 'bio'));

        return back()->with('success', 'Personal data saved successfully.');
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

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        // Delete previous avatar file if it exists
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return back()->with('success', 'Profile photo updated.');
    }
}
