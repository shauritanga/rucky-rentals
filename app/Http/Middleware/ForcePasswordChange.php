<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            if (!$request->routeIs('password.force') && !$request->routeIs('password.force.update') && !$request->routeIs('logout')) {
                return redirect()->route('password.force');
            }
        }

        return $next($request);
    }
}
