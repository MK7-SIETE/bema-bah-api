<?php

namespace App\Http\Controllers\Api;

use App\Models\SecurityLog;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ClientIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private function logSecurityEvent(string $type, ?int $userId, Request $request, array $meta = []): void
    {
        try {
            SecurityLog::create([
                'user_id'    => $userId,
                'event_type' => $type,
                'ip_address' => ClientIp::get($request),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
                // ip_source / remote / xff are TEMPORARY: they let us confirm which address is trustworthy on Render.
                'meta'       => array_merge($meta, [
                    'ip_source' => ClientIp::source($request),
                    'remote'    => (string) $request->ip(),
                    'xff'       => substr((string) $request->headers->get('X-Forwarded-For', ''), 0, 120),
                ]),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Never let logging break auth
        }
    }

    // ── Dealer login ───────────────────────────────────
    public function dealerLogin(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)
                    ->where('role', 'dealer')
                    ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->logSecurityEvent('login_fail', null, $request, ['email' => $request->email, 'role' => 'dealer']);
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        if (! $user->is_active) {
            $this->logSecurityEvent('login_fail', $user->id, $request, ['reason' => 'account_suspended']);
            return response()->json(['message' => 'Account suspended. Contact your administrator.'], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('dealer-token', ['dealer'])->plainTextToken;
        $this->logSecurityEvent('login_success', $user->id, $request);

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }

    public function dealerLogout(Request $request)
    {
        $this->logSecurityEvent('logout', $request->user()->id, $request);
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function dealerMe(Request $request)
    {
        return response()->json($request->user());
    }

    // ── Super admin login ──────────────────────────────
    // The super admin account lives in the database, and its SAVED (hashed) password is the only
    // one that counts once the account exists. So changing the password on the Profile page
    // really replaces the old one. The SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD server settings
    // are used ONLY on the very first run, to create the account when none exists yet.
    // If the saved password is ever lost, reset it with "php artisan tinker" (do NOT delete the
    // row: deleting a user can delete everything linked to it):
    //   App\Models\User::where('role','super')->first()->update(['password' => 'a-new-strong-password']);
    public function superLogin(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $email    = (string) $request->email;
        $password = (string) $request->password;

        $user = User::where('email', $email)->where('role', 'super')->first();

        if ($user) {
            if (! Hash::check($password, $user->password)) {
                $user = null;
            }
        } elseif (! User::where('role', 'super')->exists() && ! User::where('email', $email)->exists()) {
            // First run only: there is NO super admin account at all yet. Once one exists,
            // the server settings can never create a second one (there is only ever one super admin).
            $envEmail = (string) config('app.super_admin_email', env('SUPER_ADMIN_EMAIL'));
            $envPass  = (string) config('app.super_admin_password', env('SUPER_ADMIN_PASSWORD'));

            if ($envEmail !== '' && $envPass !== ''
                && hash_equals(strtolower($envEmail), strtolower($email))
                && hash_equals($envPass, $password)) {
                $user = User::create([
                    'name'      => 'Super Admin',
                    'email'     => $envEmail,
                    'password'  => Hash::make($envPass),
                    'role'      => 'super',
                    'is_active' => true,
                ]);
            }
        }

        if (! $user) {
            $this->logSecurityEvent('login_fail', null, $request, ['email' => $request->email, 'role' => 'super']);
            throw ValidationException::withMessages([
                'email' => ['Access denied.'],
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('super-token', ['super'])->plainTextToken;
        $this->logSecurityEvent('login_success', $user->id, $request);

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }

    public function superLogout(Request $request)
    {
        $this->logSecurityEvent('logout', $request->user()->id, $request);
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function superMe(Request $request)
    {
        return response()->json($request->user());
    }

    // ── Rental admin login ─────────────────────────────
    public function rentalLogin(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)
                    ->where('role', 'rental_admin')
                    ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->logSecurityEvent('login_fail', null, $request, ['email' => $request->email, 'role' => 'rental_admin']);
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        if (! $user->is_active) {
            $this->logSecurityEvent('login_fail', $user->id, $request, ['reason' => 'account_suspended']);
            return response()->json(['message' => 'Account suspended. Contact your administrator.'], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('rental-token', ['rental'])->plainTextToken;
        $this->logSecurityEvent('login_success', $user->id, $request);

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role'  => $user->role,
            ],
        ]);
    }

    public function rentalLogout(Request $request)
    {
        $this->logSecurityEvent('logout', $request->user()->id, $request);
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function rentalMe(Request $request)
    {
        return response()->json($request->user());
    }
}
