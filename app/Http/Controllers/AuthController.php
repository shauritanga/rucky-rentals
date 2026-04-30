<?php

namespace App\Http\Controllers;

use App\Mail\LoginOtpMail;
use App\Models\AuditLog;
use App\Models\LoginOtp;
use App\Models\SystemSetting;
use App\Models\User;
use App\Http\Middleware\EnforceSessionTimeout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AuthController extends Controller
{
    private const OTP_LENGTH = 6;
    private const OTP_EXPIRY_MINUTES = 10;
    private const OTP_RESEND_COOLDOWN_SECONDS = 60;
    private const OTP_MAX_ATTEMPTS = 5;
    private const OTP_MAX_RESENDS = 5;
    private const PENDING_LOGIN_SESSION_KEY = 'auth.pending_login';

    public function showLogin(Request $request)
    {
        $this->clearPendingLoginState($request);

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = (bool) $request->boolean('remember');

        $this->clearPendingLoginState($request);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
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

        if ($blockedResponse = $this->blockedLoginResponse($request, $user)) {
            return $blockedResponse;
        }

        if ($this->requiresOtp()) {
            try {
                $challenge = $this->issueOtpChallenge($request, $user);
            } catch (\Throwable $e) {
                Log::error('Failed to send login OTP email.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'mailer' => config('mail.default'),
                    'from_address' => config('mail.from.address'),
                    'from_name' => config('mail.from.name'),
                    'error' => $e->getMessage(),
                ]);

                AuditLog::create([
                    'user_id'     => $user->id,
                    'user_name'   => $user->name,
                    'action'      => 'OTP send',
                    'resource'    => $user->email,
                    'property_id' => $user->property_id ? (int) $user->property_id : null,
                    'ip_address'  => $request->ip(),
                    'result'      => 'failed',
                    'category'    => 'auth',
                    'metadata'    => ['error' => $e->getMessage()],
                ]);

                return back()
                    ->withErrors(['email' => 'The verification email could not be sent. Please try again.'])
                    ->onlyInput('email');
            }

            $request->session()->put(self::PENDING_LOGIN_SESSION_KEY, [
                'user_id' => $user->id,
                'challenge' => $challenge->challenge,
                'remember' => $remember,
            ]);

            return redirect()
                ->route('login.otp.show')
                ->with('success', 'A verification code has been sent to your email address.');
        }

        return $this->completeLogin($request, $user, $remember);
    }

    public function logout(Request $request): \Symfony\Component\HttpFoundation\Response
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

    public function showVerifyOtp(Request $request)
    {
        $pending = $this->pendingLoginPayload($request);
        if (!$pending) {
            return redirect()->route('login')->with('warning', 'Your verification session has expired. Please sign in again.');
        }

        $challenge = $this->pendingChallenge($pending);
        if (!$challenge) {
            $this->clearPendingLoginState($request);
            return redirect()->route('login')->with('warning', 'Your verification session has expired. Please sign in again.');
        }

        if ($challenge->expires_at->isPast()) {
            AuditLog::create([
                'user_id'     => $challenge->user_id,
                'user_name'   => $challenge->user?->name,
                'action'      => 'OTP expired',
                'resource'    => $challenge->user?->email,
                'property_id' => $challenge->user?->property_id ? (int) $challenge->user->property_id : null,
                'ip_address'  => $request->ip(),
                'result'      => 'failure',
                'category'    => 'auth',
            ]);
        }

        return view('auth.verify-otp', [
            'maskedEmail' => $this->maskEmail($challenge->user->email),
            'expiresAt' => $challenge->expires_at,
            'resendAvailableAt' => ($challenge->last_sent_at ?? $challenge->created_at)->copy()->addSeconds(self::OTP_RESEND_COOLDOWN_SECONDS),
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:' . self::OTP_LENGTH],
        ]);

        $pending = $this->pendingLoginPayload($request);
        if (!$pending) {
            return redirect()->route('login')->with('warning', 'Your verification session has expired. Please sign in again.');
        }

        $challenge = $this->pendingChallenge($pending);
        if (!$challenge) {
            $this->clearPendingLoginState($request);
            return redirect()->route('login')->with('warning', 'Your verification session has expired. Please sign in again.');
        }

        if ($challenge->consumed_at !== null || $challenge->expires_at->isPast()) {
            AuditLog::create([
                'user_id'     => $challenge->user_id,
                'user_name'   => $challenge->user?->name,
                'action'      => 'OTP verify',
                'resource'    => $challenge->user?->email,
                'property_id' => $challenge->user?->property_id ? (int) $challenge->user->property_id : null,
                'ip_address'  => $request->ip(),
                'result'      => 'expired',
                'category'    => 'auth',
            ]);

            return back()->withErrors(['otp' => 'This verification code has expired. Request a new code and try again.']);
        }

        if ($challenge->attempt_count >= self::OTP_MAX_ATTEMPTS) {
            return back()->withErrors(['otp' => 'Too many incorrect attempts. Please request a new code.']);
        }

        if (!Hash::check($validated['otp'], $challenge->code_hash)) {
            $challenge->increment('attempt_count');

            AuditLog::create([
                'user_id'     => $challenge->user_id,
                'user_name'   => $challenge->user?->name,
                'action'      => 'OTP verify',
                'resource'    => $challenge->user?->email,
                'property_id' => $challenge->user?->property_id ? (int) $challenge->user->property_id : null,
                'ip_address'  => $request->ip(),
                'result'      => 'failure',
                'category'    => 'auth',
                'metadata'    => ['attempt_count' => $challenge->fresh()->attempt_count],
            ]);

            return back()->withErrors(['otp' => 'Invalid verification code.']);
        }

        $challenge->forceFill([
            'consumed_at' => now(),
        ])->save();

        AuditLog::create([
            'user_id'     => $challenge->user_id,
            'user_name'   => $challenge->user?->name,
            'action'      => 'OTP verify',
            'resource'    => $challenge->user?->email,
            'property_id' => $challenge->user?->property_id ? (int) $challenge->user->property_id : null,
            'ip_address'  => $request->ip(),
            'result'      => 'success',
            'category'    => 'auth',
        ]);

        $user = $challenge->user()->first();
        if (!$user) {
            $this->clearPendingLoginState($request);
            return redirect()->route('login')->with('warning', 'Your account could not be found. Please sign in again.');
        }

        if ($blockedResponse = $this->blockedLoginResponse($request, $user)) {
            $this->clearPendingLoginState($request);
            return $blockedResponse;
        }

        $remember = (bool) ($pending['remember'] ?? false);
        return $this->completeLogin($request, $user, $remember);
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $pending = $this->pendingLoginPayload($request);
        if (!$pending) {
            return redirect()->route('login')->with('warning', 'Your verification session has expired. Please sign in again.');
        }

        $challenge = $this->pendingChallenge($pending);
        if (!$challenge) {
            $this->clearPendingLoginState($request);
            return redirect()->route('login')->with('warning', 'Your verification session has expired. Please sign in again.');
        }

        $lastSentAt = $challenge->last_sent_at ?? $challenge->created_at;
        $secondsUntilResend = now()->diffInSeconds($lastSentAt->copy()->addSeconds(self::OTP_RESEND_COOLDOWN_SECONDS), false);
        if ($secondsUntilResend > 0) {
            return back()->withErrors([
                'otp' => "Please wait {$secondsUntilResend} seconds before requesting another code.",
            ]);
        }

        if ($challenge->resend_count >= self::OTP_MAX_RESENDS) {
            return back()->withErrors(['otp' => 'You have reached the resend limit. Please sign in again.']);
        }

        $user = $challenge->user()->first();
        if (!$user) {
            $this->clearPendingLoginState($request);
            return redirect()->route('login')->with('warning', 'Your verification session has expired. Please sign in again.');
        }

        if ($blockedResponse = $this->blockedLoginResponse($request, $user)) {
            $this->clearPendingLoginState($request);
            return $blockedResponse;
        }

        $challenge->forceFill(['consumed_at' => now()])->save();

        try {
            $replacement = $this->issueOtpChallenge($request, $user, $challenge->resend_count + 1);
        } catch (\Throwable $e) {
            Log::error('Failed to resend login OTP email.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'mailer' => config('mail.default'),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'error' => $e->getMessage(),
            ]);

            AuditLog::create([
                'user_id'     => $user->id,
                'user_name'   => $user->name,
                'action'      => 'OTP resend',
                'resource'    => $user->email,
                'property_id' => $user->property_id ? (int) $user->property_id : null,
                'ip_address'  => $request->ip(),
                'result'      => 'failed',
                'category'    => 'auth',
                'metadata'    => ['error' => $e->getMessage()],
            ]);

            return back()->withErrors(['otp' => 'The verification email could not be resent. Please try again.']);
        }

        $request->session()->put(self::PENDING_LOGIN_SESSION_KEY, [
            'user_id' => $user->id,
            'challenge' => $replacement->challenge,
            'remember' => (bool) ($pending['remember'] ?? false),
        ]);

        AuditLog::create([
            'user_id'     => $user->id,
            'user_name'   => $user->name,
            'action'      => 'OTP resend',
            'resource'    => $user->email,
            'property_id' => $user->property_id ? (int) $user->property_id : null,
            'ip_address'  => $request->ip(),
            'result'      => 'success',
            'category'    => 'auth',
        ]);

        return back()->with('success', 'A new verification code has been sent to your email address.');
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

    private function completeLogin(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->forget(self::PENDING_LOGIN_SESSION_KEY);
        $request->session()->put(EnforceSessionTimeout::lastActivitySessionKey(), now()->timestamp);

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

        if ($user->must_change_password) {
            return redirect()->route('password.force');
        }

        if ($user->role === 'superuser') {
            return redirect()->route('superuser.index');
        }

        return redirect()->intended(route('dashboard'));
    }

    private function requiresOtp(): bool
    {
        return SystemSetting::get('require_2fa', '1') === '1';
    }

    private function blockedLoginResponse(Request $request, User $user): ?RedirectResponse
    {
        if (!in_array($user->status, ['suspended', 'pending_approval', 'rejected'], true)) {
            return null;
        }

        $message = match ($user->status) {
            'pending_approval' => 'Your account is pending superuser approval. Please wait for the welcome email.',
            'rejected' => 'Your account request was rejected. Please contact your administrator.',
            default => 'Your account is suspended. Please contact your administrator.',
        };

        AuditLog::create([
            'user_id'     => $user->id,
            'user_name'   => $user->name,
            'action'      => 'Login',
            'resource'    => $user->email,
            'property_id' => $user->property_id ? (int) $user->property_id : null,
            'ip_address'  => $request->ip(),
            'result'      => 'blocked',
            'category'    => 'auth',
        ]);

        return redirect()
            ->route('login')
            ->withErrors(['email' => $message])
            ->onlyInput('email');
    }

    private function issueOtpChallenge(Request $request, User $user, int $resendCount = 0): LoginOtp
    {
        $code = str_pad((string) random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);

        LoginOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $challenge = LoginOtp::create([
            'user_id' => $user->id,
            'challenge' => Str::random(40),
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            'resend_count' => $resendCount,
            'last_sent_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            Mail::to($user->email)->send(new LoginOtpMail(
                recipientName: $user->name,
                otpCode: $code,
                expiresInMinutes: self::OTP_EXPIRY_MINUTES,
            ));
        } catch (\Throwable $e) {
            $challenge->delete();
            throw $e;
        }

        AuditLog::create([
            'user_id'     => $user->id,
            'user_name'   => $user->name,
            'action'      => 'OTP send',
            'resource'    => $user->email,
            'property_id' => $user->property_id ? (int) $user->property_id : null,
            'ip_address'  => $request->ip(),
            'result'      => 'success',
            'category'    => 'auth',
            'metadata'    => ['resend_count' => $resendCount],
        ]);

        return $challenge;
    }

    private function pendingLoginPayload(Request $request): ?array
    {
        $payload = $request->session()->get(self::PENDING_LOGIN_SESSION_KEY);

        return is_array($payload) ? $payload : null;
    }

    private function pendingChallenge(array $pending): ?LoginOtp
    {
        if (empty($pending['challenge']) || empty($pending['user_id'])) {
            return null;
        }

        return LoginOtp::query()
            ->with('user')
            ->where('challenge', $pending['challenge'])
            ->where('user_id', $pending['user_id'])
            ->first();
    }

    private function clearPendingLoginState(Request $request): void
    {
        $pending = $this->pendingLoginPayload($request);
        if ($pending && !empty($pending['challenge'])) {
            LoginOtp::query()
                ->where('challenge', $pending['challenge'])
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);
        }

        $request->session()->forget(self::PENDING_LOGIN_SESSION_KEY);
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visibleName = strlen($name) <= 2
            ? substr($name, 0, 1)
            : substr($name, 0, 2);

        return $visibleName . str_repeat('*', max(strlen($name) - strlen($visibleName), 2)) . '@' . $domain;
    }
}
