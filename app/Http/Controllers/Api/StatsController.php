<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /** Dealer-scoped stats: the dashboard cards and the analytics page */
    public function dealer(Request $request)
    {
        $userId = $request->user()->id;
        $days   = 30;
        $since  = Carbon::today()->subDays($days - 1);

        // A fresh query on this dealer's cars every time it is called.
        $mine   = fn () => DB::table('cars')->where('user_id', $userId);
        $carIds = $mine()->pluck('id');

        // The inbox follows the SAME rule as MessageController: enquiries about my cars + messages
        // assigned to me, minus the ones I have deleted. So the numbers match the bell and the inbox.
        $inbox = fn () => DB::table('messages')
            ->whereNull('dealer_deleted_at')
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhereIn('car_id', $carIds));

        // Enquiries = messages about my cars. Deleting one from the inbox does not change this
        // (unless it was deleted by both sides and so removed for good).
        $enquiries = fn () => DB::table('messages')->whereIn('car_id', $carIds);

        // ── top cars: views, views in the last 30 days, enquiries ──
        $top    = $mine()->select('id', 'make', 'model', 'year', 'views')
                         ->orderByDesc('views')->orderByDesc('id')->limit(5)->get();
        $topIds = $top->pluck('id');

        $enquiriesByCar = DB::table('messages')->whereIn('car_id', $topIds)
            ->groupBy('car_id')->selectRaw('car_id, COUNT(*) AS n')->pluck('n', 'car_id');

        $views30ByCar = DB::table('car_views')->whereIn('car_id', $topIds)
            ->where('viewed_on', '>=', $since->toDateString())
            ->groupBy('car_id')->selectRaw('car_id, COUNT(*) AS n')->pluck('n', 'car_id');

        $topCars = $top->map(fn ($c) => [
            'id'        => $c->id,
            'make'      => $c->make,
            'model'     => $c->model,
            'year'      => $c->year,
            'views'     => (int) $c->views,
            'views_30d' => (int) ($views30ByCar[$c->id] ?? 0),
            'enquiries' => (int) ($enquiriesByCar[$c->id] ?? 0),
        ])->values()->all();

        // ── last 30 days, one entry per day (days with nothing are filled with 0) ──
        $viewRows = DB::table('car_views')
            ->join('cars', 'cars.id', '=', 'car_views.car_id')
            ->where('cars.user_id', $userId)
            ->where('car_views.viewed_on', '>=', $since->toDateString())
            ->groupBy('car_views.viewed_on')
            ->selectRaw('car_views.viewed_on AS day, COUNT(*) AS n')
            ->pluck('n', 'day');

        $enquiryRows = $enquiries()
            ->where('created_at', '>=', $since)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS n')
            ->pluck('n', 'day');

        $daily = [];
        for ($i = 0; $i < $days; $i++) {
            $date    = $since->copy()->addDays($i)->toDateString();
            $daily[] = [
                'date'      => $date,
                'views'     => (int) ($viewRows[$date] ?? 0),
                'enquiries' => (int) ($enquiryRows[$date] ?? 0),
            ];
        }

        return response()->json([
            'total_cars'           => $mine()->count(),
            // available = not sold (the dealer's switch); hidden_by_admin_cars = the admin's switch
            'available_cars'       => $mine()->where('is_available', true)->count(),
            'sold_cars'            => $mine()->where('is_available', false)->count(),
            'hidden_by_admin_cars' => $mine()->where('hidden_by_admin', true)->count(),
            'featured_cars'        => $mine()->where('is_featured', true)->count(),
            'total_views'          => (int) $mine()->sum('views'),
            'views_30d'            => array_sum(array_column($daily, 'views')),
            'enquiries_30d'        => array_sum(array_column($daily, 'enquiries')),
            'total_enquiries'      => $enquiries()->count(),
            'inventory_value'      => (int) $mine()->where('is_available', true)->sum('price'),
            'top_cars'             => $topCars,
            'daily'                => $daily,
            'unread_messages'      => $inbox()->where('dealer_is_read', false)->count(),
            'total_messages'       => $inbox()->count(),
            'total_testimonials'   => DB::table('testimonials')->count(),
            'pending_testimonials' => DB::table('testimonials')->where('status', 'pending')->count(),
        ]);
    }

    /** Platform-wide stats for super admin */
    public function super()
    {
        return response()->json([
            'total_cars'           => DB::table('cars')->count(),
            // available = not sold. live = what the public can actually see right now.
            'available_cars'       => DB::table('cars')->where('is_available', true)->count(),
            'sold_cars'            => DB::table('cars')->where('is_available', false)->count(),
            'hidden_cars'          => DB::table('cars')->where('hidden_by_admin', true)->count(),
            'live_cars'            => Car::visible()->count(),
            'total_messages'       => DB::table('messages')->count(),
            'unread_messages'      => DB::table('messages')->whereNull('super_deleted_at')->where('super_is_read', false)->count(),
            'total_dealers'        => DB::table('users')->where('role', 'dealer')->count(),
            'active_dealers'       => DB::table('users')->where('role', 'dealer')->where('is_active', true)->count(),
            'total_testimonials'   => DB::table('testimonials')->count(),
            'pending_testimonials' => DB::table('testimonials')->where('status', 'pending')->count(),
        ]);
    }
}
