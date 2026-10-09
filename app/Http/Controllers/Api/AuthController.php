<?php

namespace App\Http\Controllers\Api;

use App\Models\SecurityLog;
use App\Http\Controllers\Controller;
use App\Models\User;
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
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
                'meta'       => $meta ?: null,
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
    public function superLogin(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $envEmail = config('app.super_admin_email', env('SUPER_ADMIN_EMAIL'));
        $envPass  = config('app.super_admin_password', env('SUPER_ADMIN_PASSWORD'));

        if ($request->email === $envEmail && $request->password === $envPass) {
            $user = User::firstOrCreate(
                ['email' => $envEmail],
                [
                    'name'      => 'Super Admin',
                    'password'  => Hash::make($envPass),
                    'role'      => 'super',
                    'is_active' => true,
                ]
            );

            $user->tokens()->delete();
$token =    $user->createToken('super-token', ['super'])->plainTextToken;
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

    
        $user = User::where('email', $request->email)->where('role', 'super')->first();
        if ($user && Hash::check($request->password, $user->password)) {
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

        $this->logSecurityEvent('login_fail', null, $request, ['email' => $request->email, 'role' => 'super']);
        throw ValidationException::withMessages([
            'email' => ['Access denied.'],
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
}