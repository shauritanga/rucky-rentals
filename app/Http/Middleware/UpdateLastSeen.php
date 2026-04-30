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
        if ($request->user() && !EnforceSessionTimeout::isPassiveRequest($request)) {
            $request->user()->timestamps = false;
            $request->user()->update(['last_seen_at' => now()]);
            $request->user()->timestamps = true;
        }

        return $next($request);
    }
}
