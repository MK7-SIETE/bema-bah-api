<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() || ! $request->user()->isSuper()) {
            return response()->json(['message' => 'Unauthorized. Super admin access required.'], 401);
        }
        return $next($request);
    }
}
