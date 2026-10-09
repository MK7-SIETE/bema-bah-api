<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RentalAuth
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user->isRentalAdmin()) {
            return response()->json(['message' => 'Unauthorized. Rental admin access required.'], 401);
        }
        if (! $user->isActive()) {
            return response()->json(['message' => 'Account suspended.'], 403);
        }

        return $next($request);
    }
}
