<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user   = $request->user();
        $config = SiteConfig::all_config();

        return response()->json([
            'user'   => $user,
            'config' => $config,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name'                => ['sometimes', 'string', 'max:200'],
            'dealership_name'     => ['sometimes', 'string', 'max:200'],
            'phone'               => ['sometimes', 'string', 'max:30'],
            'whatsapp'            => ['nullable', 'string', 'max:30'],
            'email'               => ['sometimes', 'email', 'max:200'],
            'address'             => ['sometimes', 'string', 'max:400'],
            'city'                => ['nullable', 'string', 'max:100'],
            'country'             => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'about'               => ['sometimes', 'string'],
            'facebook'            => ['nullable', 'url'],
            'instagram'           => ['nullable', 'url'],
            'twitter'             => ['nullable', 'url'],
            'youtube'             => ['nullable', 'url'],
            'linkedin'            => ['nullable', 'url'],
            'hours'               => ['nullable', 'array'],
            'password'            => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (isset($data['name']))     $user->name     = $data['name'];
        if (isset($data['password'])) $user->password = Hash::make($data['password']);
        $user->save();

        // Store all config fields
        $configFields = [
            'dealership_name', 'phone', 'whatsapp', 'email',
            'address', 'city', 'country', 'registration_number',
            'about', 'facebook', 'instagram', 'twitter', 'youtube', 'linkedin',
            'hours',
        ];

        foreach ($configFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = is_array($data[$field]) ? json_encode($data[$field]) : ($data[$field] ?? '');
                SiteConfig::set($field, $value);
            }
        }

        return response()->json(['message' => 'Profile updated.', 'user' => $user]);
    }
}