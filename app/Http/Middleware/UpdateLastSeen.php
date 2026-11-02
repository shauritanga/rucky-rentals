<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastSeen
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // "Last seen" only needs minute accuracy, so skip the database write when it was updated moments ago.
        $user = $request->user();
        if ($user && !EnforceSessionTimeout::isPassiveRequest($request)
            && (!$user->last_seen_at || \Illuminate\Support\Carbon::parse($user->last_seen_at)->lt(now()->subMinute()))) {
            $user->timestamps = false;
            $user->update(['last_seen_at' => now()]);
            $user->timestamps = true;
        }

        return $next($request);
    }
}
