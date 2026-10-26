<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The visitor's real IP address.
 *
 * On Render every request passes through Render's own servers first, so Laravel's normal
 * $request->ip() can show a private address inside Render (10.x.x.x) instead of the visitor.
 * Render sits behind Cloudflare, and Cloudflare puts the visitor's address in "CF-Connecting-IP".
 * We use that header when it holds a valid address, and fall back to Laravel's own answer otherwise.
 *
 * Do not use this for anything that must be exact until the test in the notes has been done
 * (it checks that a visitor cannot forge the header).
 */
class ClientIp
{
    public static function get(Request $request): string
    {
        $cf = trim((string) $request->headers->get('CF-Connecting-IP', ''));

        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf;
        }

        return (string) $request->ip();
    }

    /** Where the address came from, for the security log while we are verifying this. */
    public static function source(Request $request): string
    {
        $cf = trim((string) $request->headers->get('CF-Connecting-IP', ''));

        return ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) ? 'cf-connecting-ip' : 'request-ip';
    }
}
