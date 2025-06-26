<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class MultiAuthGuard
{
    public function handle($request, Closure $next)
    {
        // Try 'api' guard
        if (Auth::guard('api')->check()) {
            Auth::shouldUse('api');
        }
        // Try 'member' guard
        elseif (Auth::guard('member')->check()) {
            Auth::shouldUse('member');
        }
        else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}

