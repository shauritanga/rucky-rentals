<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = (bool) $request->boolean('remember');

        if (!Auth::attempt($credentials, $remember)) {
            AuditLog::create([
                'user_name'  => $credentials['email'],
                'action'     => 'Login',
                'resource'   => $credentials['email'],
                'ip_address' => $request->ip(),
                'result'     => 'failure',
                'category'   => 'auth',
            ]);

            return back()
                ->withErrors(['email' => 'Invalid email or password.'])
                ->onlyInput('email');
        }

        $user = $request->user();

        if ($user && $user->status === 'suspended') {
            AuditLog::create([
                'user_id'    => $user->id,
                'user_name'  => $user->name,
                'action'     => 'Login',
                'resource'   => $user->email,
                'property_id'=> $user->property_id ? (int) $user->property_id : null,
                'ip_address' => $request->ip(),
                'result'     => 'blocked',
                'category'   => 'auth',
            ]);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Your account is suspended. Please contact your administrator.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        AuditLog::create([
            'user_id'     => $user->id,
            'user_name'   => $user->name,
            'action'      => 'Login',
            'resource'    => $user->email,
            'property_id' => $user->property_id ? (int) $user->property_id : null,
            'ip_address'  => $request->ip(),
            'result'      => 'success',
            'category'    => 'auth',
        ]);

        if ($user && $user->must_change_password) {
            return redirect()->route('password.force');
        }

        if ($user && $user->role === 'superuser') {
            return redirect()->route('superuser.index');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            AuditLog::create([
                'user_id'     => $user->id,
                'user_name'   => $user->name,
                'action'      => 'Logout',
                'resource'    => $user->email,
                'property_id' => $user->property_id ? (int) $user->property_id : null,
                'ip_address'  => $request->ip(),
                'result'      => 'success',
                'category'    => 'auth',
            ]);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location(route('login'));
    }

    public function showForcePasswordChange(Request $request)
    {
        if (!$request->user() || !$request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.force-password-change');
    }

    public function forcePasswordChange(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user || !$user->must_change_password) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        AuditLog::create([
            'user_id'     => $user->id,
            'user_name'   => $user->name,
            'action'      => 'Password changed',
            'resource'    => $user->email,
            'property_id' => $user->property_id ? (int) $user->property_id : null,
            'ip_address'  => $request->ip(),
            'result'      => 'success',
            'category'    => 'auth',
        ]);

        if ($user->role === 'superuser') {
            return redirect()->route('superuser.index')->with('success', 'Password changed successfully.');
        }

        return redirect()->route('dashboard')->with('success', 'Password changed successfully.');
    }
}
