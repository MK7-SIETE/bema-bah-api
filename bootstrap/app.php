<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__.'/../routes/web.php',
        api:      __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health:   '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->use([
            \Illuminate\Http\Middleware\TrustProxies::class,
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Render sits behind a proxy; trust it so request->ip() is the real visitor.
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        $middleware->alias([
            'dealer.auth' => \App\Http\Middleware\DealerAuth::class,
            'super.auth'  => \App\Http\Middleware\SuperAuth::class,
            'rental.auth' => \App\Http\Middleware\RentalAuth::class,
        ]);

        // Tell Sanctum to use token auth for API routes
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Every /api/* request gets JSON errors (401, 403, 404, 422, 500), whatever headers the caller sent.
        // Without this, a request that forgets "Accept: application/json" tries to redirect to a
        // "login" route that does not exist and ends in a 500 instead of a clean 401.
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
