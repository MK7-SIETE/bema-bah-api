<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SystemHealthController extends Controller
{
    public function index()
    {
        // Database connectivity
        $dbStatus = 'ok';
        $dbLatencyMs = null;
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $dbLatencyMs = round((microtime(true) - $start) * 1000, 1);
        } catch (\Throwable $e) {
            $dbStatus = 'error';
        }

        // Cache connectivity
        $cacheStatus = 'ok';
        try {
            Cache::put('_health_check', 1, 5);
            Cache::get('_health_check');
        } catch (\Throwable $e) {
            $cacheStatus = 'error';
        }

        // Storage
        $diskTotal = null;
        $diskFree  = null;
        $diskUsed  = null;
        $diskPct   = null;
        try {
            $path      = storage_path();
            $diskTotal = disk_total_space($path);
            $diskFree  = disk_free_space($path);
            $diskUsed  = $diskTotal - $diskFree;
            $diskPct   = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0;
        } catch (\Throwable) {}

        // PHP / Laravel info
        $phpVersion     = PHP_VERSION;
        $laravelVersion = app()->version();
        $environment    = config('app.env');
        $debugMode      = config('app.debug');
        $uptime         = $this->getUptime();

        // Queue (basic check — just whether the driver is configured)
        $queueDriver = config('queue.default');

        return response()->json([
            'status' => ($dbStatus === 'ok' && $cacheStatus === 'ok') ? 'healthy' : 'degraded',
            'timestamp' => now()->toISOString(),
            'services' => [
                'database' => [
                    'status'     => $dbStatus,
                    'latency_ms' => $dbLatencyMs,
                    'driver'     => config('database.default'),
                ],
                'cache' => [
                    'status' => $cacheStatus,
                    'driver' => config('cache.default'),
                ],
                'queue' => [
                    'status' => 'ok',
                    'driver' => $queueDriver,
                ],
            ],
            'storage' => [
                'total_bytes' => $diskTotal,
                'used_bytes'  => $diskUsed,
                'free_bytes'  => $diskFree,
                'used_pct'    => $diskPct,
            ],
            'runtime' => [
                'php_version'     => $phpVersion,
                'laravel_version' => $laravelVersion,
                'environment'     => $environment,
                'debug_mode'      => $debugMode,
                'uptime'          => $uptime,
            ],
        ]);
    }

    private function getUptime(): ?string
    {
        try {
            if (PHP_OS_FAMILY === 'Linux') {
                $uptime = (float) file_get_contents('/proc/uptime');
                $days   = floor($uptime / 86400);
                $hours  = floor(($uptime % 86400) / 3600);
                $mins   = floor(($uptime % 3600) / 60);
                return "{$days}d {$hours}h {$mins}m";
            }
        } catch (\Throwable) {}
        return null;
    }
}