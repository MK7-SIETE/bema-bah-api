<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteConfig;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SuperAdminController extends Controller
{
    public function dealers()
    {
        return response()->json(
            User::where('role', 'dealer')->orderBy('created_at', 'desc')->get()
        );
    }

    public function createDealer(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:200'],
            'email'    => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'role'      => 'dealer',
            'is_active' => true,
        ]);

        return response()->json($user, 201);
    }

    public function suspend(int $id)
    {
        $user = User::where('role', 'dealer')->findOrFail($id);
        $user->update(['is_active' => ! $user->is_active]);
        return response()->json([
            'message'   => $user->is_active ? 'Account activated.' : 'Account suspended.',
            'is_active' => $user->is_active,
        ]);
    }

    public function deleteDealer(int $id)
    {
        $user = User::where('role', 'dealer')->findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'Dealer account deleted.']);
    }

    public function getConfig()
    {
        return response()->json(SiteConfig::all_config());
    }

    public function updateConfig(Request $request)
    {
        $data = $request->validate([
            'dealership_name' => ['sometimes', 'string', 'max:200'],
            'phone'           => ['sometimes', 'string', 'max:30'],
            'email'           => ['sometimes', 'email'],
            'address'         => ['sometimes', 'string'],
            'about'           => ['sometimes', 'string'],
            'facebook'        => ['nullable', 'url'],
            'instagram'       => ['nullable', 'url'],
            'twitter'         => ['nullable', 'url'],
        ]);

        foreach ($data as $key => $value) {
            SiteConfig::set($key, $value ?? '');
        }

        return response()->json(['message' => 'Site config updated.', 'config' => SiteConfig::all_config()]);
    }
}
