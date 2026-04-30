<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionTimeout
{
    private const LAST_ACTIVITY_SESSION_KEY = 'auth.last_activity_at';
    private const PASSIVE_ACTIVITY_HEADER = 'X-Session-Activity';
    private const DEFAULT_TIMEOUT_MINUTES = 15;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $timeoutMinutes = $this->timeoutMinutes();
        $lastActivityAt = $request->session()->get(self::LAST_ACTIVITY_SESSION_KEY);

        if (is_numeric($lastActivityAt)) {
            $expiresAt = Carbon::createFromTimestamp((int) $lastActivityAt)->addMinutes($timeoutMinutes);

            if (now()->greaterThanOrEqualTo($expiresAt)) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'action' => 'Session timeout',
                    'resource' => $user->email,
                    'property_id' => $user->property_id ? (int) $user->property_id : null,
                    'ip_address' => $request->ip(),
                    'result' => 'success',
                    'category' => 'auth',
                    'metadata' => ['timeout_minutes' => $timeoutMinutes],
                ]);

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $this->timeoutResponse($request);
            }
        }

        if (!$this->isPassiveRequest($request)) {
            $request->session()->put(self::LAST_ACTIVITY_SESSION_KEY, now()->timestamp);
        }

        return $next($request);
    }

    public static function lastActivitySessionKey(): string
    {
        return self::LAST_ACTIVITY_SESSION_KEY;
    }

    public static function isPassiveRequest(Request $request): bool
    {
        return strtolower((string) $request->header(self::PASSIVE_ACTIVITY_HEADER)) === 'passive';
    }

    public static function timeoutMinutesFromSettings(): int
    {
        $value = SystemSetting::get('session_timeout', self::DEFAULT_TIMEOUT_MINUTES);
        $minutes = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $minutes === false ? self::DEFAULT_TIMEOUT_MINUTES : (int) $minutes;
    }

    private function timeoutMinutes(): int
    {
        return self::timeoutMinutesFromSettings();
    }

    private function timeoutResponse(Request $request): JsonResponse|RedirectResponse
    {
        $message = 'Your session expired after inactivity. Please sign in again.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        return redirect()
            ->route('login')
            ->withErrors(['email' => $message]);
    }
}
