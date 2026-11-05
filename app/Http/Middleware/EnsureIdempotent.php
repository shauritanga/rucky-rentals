<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a save safe to repeat after a lost connection.
 *
 * The browser sends a unique X-Idempotency-Key with every save and reuses the same key when the user presses the
 * button again after an unclear failure. If that key already produced a successful result for this user, the stored
 * result is replayed instead of running the action a second time, so a payment, lease or invoice is never created twice.
 * Failed attempts (validation errors, server errors) are never stored, so a retry simply runs again.
 */
class EnsureIdempotent
{
    private const HEADER = 'X-Idempotency-Key';
    private const TTL_MINUTES = 30;
    private const FLASH_KEYS = ['success', 'warning', 'error', 'created_invoice_id'];

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);
        $userId = $request->user()?->id;

        if (!$key || !$userId || $request->isMethodSafe() || strlen($key) > 100) {
            return $next($request);
        }

        $fingerprint = sha1($userId . '|' . $request->method() . '|' . $request->path() . '|' . $key);
        $resultKey = 'idempotent:result:' . $fingerprint;

        if ($stored = Cache::get($resultKey)) {
            return $this->replay($stored);
        }

        // The first attempt may still be running (a slow connection): wait briefly rather than run the action twice.
        $lock = Cache::lock('idempotent:lock:' . $fingerprint, 60);
        try {
            $lock->block(8);
        } catch (LockTimeoutException) {
            return back()->with('error', 'This is already being processed. Check the list before trying again.');
        }

        try {
            if ($stored = Cache::get($resultKey)) {
                return $this->replay($stored);
            }

            $response = $next($request);

            if ($this->worthStoring($request, $response)) {
                $flash = [];
                foreach (self::FLASH_KEYS as $name) {
                    if ($request->session()->has($name)) {
                        $flash[$name] = $request->session()->get($name);
                    }
                }
                Cache::put($resultKey, [
                    'location' => $response->headers->get('Location'),
                    'status'   => $response->getStatusCode(),
                    'flash'    => $flash,
                ], now()->addMinutes(self::TTL_MINUTES));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /** Only a redirect that succeeded, with no validation errors and no error message, is a result worth repeating. */
    private function worthStoring(Request $request, Response $response): bool
    {
        if (!$response instanceof RedirectResponse || $response->getStatusCode() >= 400) {
            return false;
        }

        $session = $request->session();

        return !$session->has('errors') && !$session->has('error');
    }

    private function replay(array $stored): Response
    {
        $response = redirect($stored['location'] ?: url()->previous(), $stored['status'] ?: 302);
        foreach ($stored['flash'] ?? [] as $name => $value) {
            $response->with($name, $value);
        }

        return $response;
    }
}
