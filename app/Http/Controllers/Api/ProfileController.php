<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    // The fields that change the PUBLIC website (contact details, social links, opening hours...).
    // Only the super admin may change these. Any dealer who sends them is simply ignored.
    private const SITE_FIELDS = [
        'dealership_name', 'phone', 'whatsapp', 'email',
        'address', 'city', 'country', 'registration_number',
        'about', 'facebook', 'instagram', 'twitter', 'youtube', 'linkedin',
        'hours',
    ];

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
        $user    = $request->user();
        $isSuper = $user->isSuper();

        // Everyone may change their OWN name and password.
        $rules = [
            'name'             => ['sometimes', 'string', 'max:200'],
            'current_password' => ['required_with:password', 'string', 'max:100'],
            'password'         => ['nullable', 'string', 'min:8', 'max:100', 'confirmed'],
        ];

        // Only the super admin may change what customers see on the public site.
        // Because dealers have no rules for these fields, validate() never returns them.
        if ($isSuper) {
            $rules += [
                'dealership_name'     => ['sometimes', 'string', 'max:200'],
                'phone'               => ['sometimes', 'string', 'max:30'],
                'whatsapp'            => ['nullable', 'string', 'max:30'],
                'email'               => ['sometimes', 'email', 'max:200'],
                'address'             => ['sometimes', 'string', 'max:400'],
                'city'                => ['nullable', 'string', 'max:100'],
                'country'             => ['nullable', 'string', 'max:100'],
                'registration_number' => ['nullable', 'string', 'max:100'],
                'about'               => ['sometimes', 'string', 'max:5000'],
                'facebook'            => ['nullable', 'url:http,https', 'max:300'],
                'instagram'           => ['nullable', 'url:http,https', 'max:300'],
                'twitter'             => ['nullable', 'url:http,https', 'max:300'],
                'youtube'             => ['nullable', 'url:http,https', 'max:300'],
                'linkedin'            => ['nullable', 'url:http,https', 'max:300'],
                'hours'               => ['nullable', 'array', 'max:50'],
            ];
        }

        $data = $request->validate($rules);

        if (isset($data['hours']) && strlen((string) json_encode($data['hours'])) > 5000) {
            throw ValidationException::withMessages(['hours' => ['The opening hours are too long.']]);
        }

        // A new password is accepted only together with the CURRENT password.
        // (A stolen login token alone can then not be used to lock the real owner out.)
        if (! empty($data['password'])
            && ! Hash::check((string) ($data['current_password'] ?? ''), (string) $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['The current password is not correct.']]);
        }

        // An empty box means "leave it alone". Never save an empty name or an empty password.
        $passwordChanged = false;
        if (isset($data['name']) && $data['name'] !== '') {
            $user->name = $data['name'];
        }
        if (! empty($data['password'])) {
            $user->password  = Hash::make($data['password']);
            $passwordChanged = true;
        }
        $user->save();

        // After a password change, every other signed-in session of this account is signed out.
        if ($passwordChanged) {
            $current = $user->currentAccessToken();
            $user->tokens()
                ->when($current && isset($current->id), fn ($q) => $q->where('id', '!=', $current->id))
                ->delete();
        }

        if ($isSuper) {
            foreach (self::SITE_FIELDS as $field) {
                if (array_key_exists($field, $data)) {
                    $value = is_array($data[$field]) ? json_encode($data[$field]) : ($data[$field] ?? '');
                    SiteConfig::set($field, $value);
                }
            }
        }

        return response()->json(['message' => 'Profile updated.', 'user' => $user]);
    }
}
