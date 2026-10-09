<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DealerAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() || (! $request->user()->isDealer() && ! $request->user()->isSuper())) {
            return response()->json(['message' => 'Unauthorized. Dealer access required.'], 401);
        }
        if ($request->user()->isDealer() && ! $request->user()->isActive()) {
            return response()->json(['message' => 'Account suspended.'], 403);
        }
        return $next($request);
    }
}
