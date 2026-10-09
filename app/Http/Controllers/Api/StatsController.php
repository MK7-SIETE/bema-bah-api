<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /** Dealer-scoped stats */
    public function dealer()
    {
        $userId = auth()->id();

        $carIds = DB::table('cars')->where('user_id', $userId)->pluck('id');

        $topCars = DB::table('cars')
            ->where('user_id', $userId)
            ->select('id', 'make', 'model', 'views')
            ->orderByDesc('views')
            ->limit(5)
            ->get();

        return response()->json([
            'total_cars'           => DB::table('cars')->where('user_id', $userId)->count(),
            'available_cars'       => DB::table('cars')->where('user_id', $userId)->where('is_available', true)->count(),
            'sold_cars'            => DB::table('cars')->where('user_id', $userId)->where('is_available', false)->count(),
            'featured_cars'        => DB::table('cars')->where('user_id', $userId)->where('is_featured', true)->count(),
            'total_views'          => (int) DB::table('cars')->where('user_id', $userId)->sum('views'),
            'inventory_value'      => (int) DB::table('cars')->where('user_id', $userId)->where('is_available', true)->sum('price'),
            'top_cars'             => $topCars,
            'unread_messages'      => DB::table('messages')->whereIn('car_id', $carIds)->where('is_read', false)->count(),
            'total_messages'       => DB::table('messages')->whereIn('car_id', $carIds)->count(),
            'total_testimonials'   => DB::table('testimonials')->count(),
            'pending_testimonials' => DB::table('testimonials')->where('status', 'pending')->count(),
        ]);
    }

    /** Platform-wide stats for super admin */
    public function super()
    {
        return response()->json([
            'total_cars'           => DB::table('cars')->count(),
            'available_cars'       => DB::table('cars')->where('is_available', true)->count(),
            'total_messages'       => DB::table('messages')->count(),
            'unread_messages'      => DB::table('messages')->where('is_read', false)->count(),
            'total_dealers'        => DB::table('users')->where('role', 'dealer')->count(),
            'active_dealers'       => DB::table('users')->where('role', 'dealer')->where('is_active', true)->count(),
            'total_testimonials'   => DB::table('testimonials')->count(),
            'pending_testimonials' => DB::table('testimonials')->where('status', 'pending')->count(),
        ]);
    }
}
