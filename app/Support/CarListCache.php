<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A short memory for the PUBLIC car lists, so most visitors are answered without asking the database.
 *
 *  - The same list (same filters, sort and page) is reused for SECONDS seconds.
 *  - When a car is added, edited, sold, hidden or deleted through CarController, bump() makes every saved list
 *    out of date at once, so dealers see their change straight away.
 *  - Typed searches and price filters are always answered live (see listing()).
 *  - If the cache itself fails for any reason, the visitor is simply answered live, as before.
 */
class CarListCache
{
    // How long a saved list is reused. Photo changes and dealer suspensions do not call bump(), so they show within this time.
    private const SECONDS = 60;

    // Only these address parameters change what the public list shows.
    private const PARAMS = ['make', 'body_type', 'fuel', 'transmission', 'source_country', 'classic', 'sort', 'page', 'per_page'];

    // Pages deeper than this are never saved (stops thousands of entries being made with ?page=1, 2, 3 ...).
    private const MAX_PAGE = 20;

    private const VERSION_KEY = 'cars-cache-version';

    /** The public car list. $build runs the real search and must return a paginator. */
    public static function listing(Request $request, Closure $build)
    {
        if (trim((string) $request->input('search', '')) !== ''
            || $request->filled('min_price')
            || $request->filled('max_price')
            || (int) $request->input('page', 1) > self::MAX_PAGE) {
            return $build();
        }

        $params = [];
        foreach (self::PARAMS as $name) {
            $value = $request->input($name);
            if ($value === null || $value === '') {
                continue;
            }
            if (! is_scalar($value)) {
                return $build(); // something odd in the address: answer live
            }
            $params[$name] = (string) $value;
        }
        ksort($params);

        try {
            $key = 'cars:list:' . self::version() . ':' . md5((string) json_encode($params));
            $hit = Cache::get($key);
            if (is_array($hit)) {
                return $hit;
            }
        } catch (\Throwable $e) {
            return $build(); // the cache is not working: answer live
        }

        $result = $build()->toArray();

        // Save only real answers (cars were found), so random words in the address cannot fill the cache.
        if (! empty($result['data'])) {
            try {
                Cache::put($key, $result, self::SECONDS);
            } catch (\Throwable $e) {
                // not saved: fine
            }
        }

        return $result;
    }

    /** Any other small public answer (for example the featured cars). */
    public static function remember(string $name, Closure $build)
    {
        try {
            $key = 'cars:' . $name . ':' . self::version();
            $hit = Cache::get($key);
            if ($hit !== null) {
                return $hit;
            }
        } catch (\Throwable $e) {
            return $build();
        }

        $value = $build();

        try {
            Cache::put($key, $value, self::SECONDS);
        } catch (\Throwable $e) {
            // not saved: fine
        }

        return $value;
    }

    /** Call after any change to a car: every saved list becomes out of date at once. */
    public static function bump(): void
    {
        try {
            Cache::forever(self::VERSION_KEY, (string) Str::uuid());
        } catch (\Throwable $e) {
            // not saved: lists then refresh by themselves within SECONDS
        }
    }

    private static function version(): string
    {
        return (string) Cache::get(self::VERSION_KEY, '0');
    }
}
