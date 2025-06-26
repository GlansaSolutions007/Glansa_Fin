<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

class CheckJwtToken
{
    // public function handle(Request $request, Closure $next)
    // {
    //     $token = $request->bearerToken();

    //     if ($token !== env('JWT_SECRET')) {
    //         return response()->json(['error' => 'Unauthorized'], 401);
    //     }

    //     return $next($request);
    // }

    public function handle($request, Closure $next)
    {
        try {
            // Try to authenticate the user using the token
            $user = JWTAuth::parseToken()->authenticate();
            
            // If no user found, return unauthorized
            if (!$user) {
                return response()->json(['error' => 'User not found'], 401);
            }
        } catch (Exception $e) {
            // Catch token parsing errors (e.g., expired token)
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
