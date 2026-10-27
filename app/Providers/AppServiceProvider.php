<?php

namespace App\Providers;

use App\Support\ClientIp;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // ── Request limits ─────────────────────────────────────────────────────────────
        // Every limit is counted per VISITOR (the real address from ClientIp), never per Render's
        // internal server. A limiter that lists two limits must give them different keys
        // (the 'min' / 'hour' tags below), or they would share one counter.
        //
        // Failed LOGINS are counted separately, per account and address, in AuthController.

        // A general ceiling for the whole API (stops floods on search and the like).
        RateLimiter::for('api', fn (Request $r) => $this->limit(Limit::perMinute(300), 'api', $r));

        // How fast any one visitor may TRY to log in (the 5-failures rule is in AuthController).
        RateLimiter::for('login-attempts', fn (Request $r) => $this->limit(Limit::perMinute(20), 'login', $r));

        // Public forms. Mobile networks put many customers behind one address, so these are not tiny.
        RateLimiter::for('public-messages', fn (Request $r) => [
            $this->limit(Limit::perMinute(3), 'min', $r),
            $this->limit(Limit::perHour(10), 'hour', $r),
        ]);
        RateLimiter::for('public-reviews', fn (Request $r) => [
            $this->limit(Limit::perMinute(2), 'min', $r),
            $this->limit(Limit::perHour(3), 'hour', $r),
        ]);
        RateLimiter::for('public-subscribe', fn (Request $r) => [
            $this->limit(Limit::perMinute(3), 'min', $r),
            $this->limit(Limit::perHour(5), 'hour', $r),
        ]);
        RateLimiter::for('rental-requests', fn (Request $r) => [
            $this->limit(Limit::perMinute(6), 'min', $r),
            $this->limit(Limit::perHour(30), 'hour', $r),
        ]);

        // The "someone opened this car" ping.
        RateLimiter::for('car-views', fn (Request $r) => $this->limit(Limit::perMinute(60), 'views', $r));
    }

    // Count this limit per visitor, and answer with a clear message when it is used up.
    private function limit(Limit $limit, string $tag, Request $request): Limit
    {
        return $limit
            ->by($tag . '|' . ClientIp::get($request))
            ->response(function (Request $request, array $headers) {
                return response()->json(
                    ['message' => 'Too many requests. Please wait a little and try again.'],
                    429,
                    $headers
                );
            });
    }
}
