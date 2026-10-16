<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\SiteConfig;
use App\Models\User;
use App\Services\CarImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SuperAdminController extends Controller
{
    public function dealers()
    {
        return response()->json(
            User::where('role', 'dealer')->withCount('cars')->orderBy('created_at', 'desc')->get()
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

    // Suspending a dealer hides all of their cars from the public site (see Car::visible()).
    public function suspend(int $id)
    {
        $user = User::where('role', 'dealer')->findOrFail($id);
        $user->update(['is_active' => ! $user->is_active]);
        return response()->json([
            'message'   => $user->is_active ? 'Account activated.' : 'Account suspended.',
            'is_active' => $user->is_active,
        ]);
    }

    public function deleteDealer(int $id, CarImageService $images)
    {
        $user = User::where('role', 'dealer')->findOrFail($id);
        $this->deleteAccount($user, $images);
        return response()->json(['message' => 'Dealer account deleted.']);
    }

    // ── Rental admins ──────────────────────────────────
    public function rentalAdmins()
    {
        return response()->json(
            User::where('role', 'rental_admin')->withCount('cars')->orderBy('created_at', 'desc')->get()
        );
    }

    public function createRentalAdmin(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:200'],
            'email'    => ['required', 'email', 'unique:users'],
            'phone'    => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'],
            'password'  => Hash::make($data['password']),
            'role'      => 'rental_admin',
            'is_active' => true,
        ]);

        return response()->json($user, 201);
    }

    public function updateRentalAdmin(Request $request, int $id)
    {
        $user = User::where('role', 'rental_admin')->findOrFail($id);

        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:200'],
            'phone' => ['sometimes', 'string', 'max:30'],
        ]);

        $user->update($data);
        return response()->json($user);
    }

    public function suspendRentalAdmin(int $id)
    {
        $user = User::where('role', 'rental_admin')->findOrFail($id);
        $user->update(['is_active' => ! $user->is_active]);
        return response()->json([
            'message'   => $user->is_active ? 'Account activated.' : 'Account suspended.',
            'is_active' => $user->is_active,
        ]);
    }

    public function deleteRentalAdmin(int $id, CarImageService $images)
    {
        $user = User::where('role', 'rental_admin')->findOrFail($id);
        $this->deleteAccount($user, $images); // their rental cars are deleted with them
        return response()->json(['message' => 'Rental admin account deleted.']);
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
            'whatsapp'        => ['sometimes', 'nullable', 'string', 'max:30'],
            'email'           => ['sometimes', 'email'],
            'address'         => ['sometimes', 'string'],
            'city'            => ['sometimes', 'nullable', 'string', 'max:100'],
            'country'         => ['sometimes', 'nullable', 'string', 'max:100'],
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

    // Delete a dealer or rental admin together with their cars.
    // The photo addresses are read BEFORE the delete (the database removes the car rows with the account),
    // and the photos are removed from Cloudinary after the response, so the request stays fast.
    private function deleteAccount(User $user, CarImageService $images): void
    {
        $urls = DB::table('car_images')
            ->whereIn('car_id', Car::select('id')->where('user_id', $user->id))
            ->pluck('path');

        $user->delete();

        $images->deleteRemoteAfterResponse($urls);
    }
}
