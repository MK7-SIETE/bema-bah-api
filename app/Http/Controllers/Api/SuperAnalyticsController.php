<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SuperAnalyticsController extends Controller
{
    // GET /api/super/analytics                -> the whole platform (cars for sale)
    // GET /api/super/analytics?dealer=<id>    -> only that dealer's cars and messages
    //
    // The answer has the same shape as the dealer's analytics, plus "dealers" (one row per dealer,
    // only when no single dealer is picked) and "dealer_name" on each top car.
    public function index(Request $request)
    {
        $days  = 30;
        $since = Carbon::today()->subDays($days - 1);

        $dealerId = (int) $request->input('dealer');
        if ($dealerId > 0 && ! DB::table('users')->where('id', $dealerId)->where('role', 'dealer')->exists()) {
            return response()->json(['message' => 'Dealer not found.'], 404);
        }
        $dealerId = $dealerId > 0 ? $dealerId : null;

        // Cars for sale, optionally only one dealer's. A fresh query every time it is called.
        $cars = fn () => DB::table('cars')
            ->where('cars.listing_type', 'sale')
            ->when($dealerId, fn ($q) => $q->where('cars.user_id', $dealerId));

        // Messages, with the owner worked out the same way everywhere:
        // the dealer the message was assigned to, or else the owner of the car it is about.
        $messages = fn () => DB::table('messages')
            ->leftJoin('cars', 'cars.id', '=', 'messages.car_id')
            ->when($dealerId, fn ($q) => $q->whereRaw('COALESCE(messages.user_id, cars.user_id) = ?', [$dealerId]));

        // ── top cars: views, views in the last 30 days, enquiries, and the dealer ──
        $top = $cars()
            ->join('users', 'users.id', '=', 'cars.user_id')
            ->select('cars.id', 'cars.make', 'cars.model', 'cars.year', 'cars.views', 'users.name AS dealer_name')
            ->orderByDesc('cars.views')->orderByDesc('cars.id')
            ->limit(5)->get();
        $topIds = $top->pluck('id');

        $enquiriesByCar = DB::table('messages')->whereIn('car_id', $topIds)
            ->groupBy('car_id')->selectRaw('car_id, COUNT(*) AS n')->pluck('n', 'car_id');

        $views30ByCar = DB::table('car_views')->whereIn('car_id', $topIds)
            ->where('viewed_on', '>=', $since->toDateString())
            ->groupBy('car_id')->selectRaw('car_id, COUNT(*) AS n')->pluck('n', 'car_id');

        $topCars = $top->map(fn ($c) => [
            'id'          => $c->id,
            'make'        => $c->make,
            'model'       => $c->model,
            'year'        => $c->year,
            'dealer_name' => $c->dealer_name,
            'views'       => (int) $c->views,
            'views_30d'   => (int) ($views30ByCar[$c->id] ?? 0),
            'enquiries'   => (int) ($enquiriesByCar[$c->id] ?? 0),
        ])->values()->all();

        // ── last 30 days, one entry per day (days with nothing are filled with 0) ──
        $viewRows = DB::table('car_views')
            ->join('cars', 'cars.id', '=', 'car_views.car_id')
            ->where('cars.listing_type', 'sale')
            ->when($dealerId, fn ($q) => $q->where('cars.user_id', $dealerId))
            ->where('car_views.viewed_on', '>=', $since->toDateString())
            ->groupBy('car_views.viewed_on')
            ->selectRaw('car_views.viewed_on AS day, COUNT(*) AS n')
            ->pluck('n', 'day');

        $enquiryRows = $messages()
            ->where('messages.created_at', '>=', $since)
            ->groupBy(DB::raw('DATE(messages.created_at)'))
            ->selectRaw('DATE(messages.created_at) AS day, COUNT(*) AS n')
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

        // ── one row per dealer (only for the whole-platform view) ──
        $dealers = [];
        if (! $dealerId) {
            $carStats = DB::table('cars')->where('listing_type', 'sale')
                ->groupBy('user_id')
                ->selectRaw('user_id, COUNT(*) AS cars, COALESCE(SUM(views), 0) AS views, SUM(CASE WHEN is_available = 1 AND hidden_by_admin = 0 THEN 1 ELSE 0 END) AS live')
                ->get()->keyBy('user_id');

            $views30ByDealer = DB::table('car_views')
                ->join('cars', 'cars.id', '=', 'car_views.car_id')
                ->where('cars.listing_type', 'sale')
                ->where('car_views.viewed_on', '>=', $since->toDateString())
                ->groupBy('cars.user_id')
                ->selectRaw('cars.user_id AS user_id, COUNT(*) AS n')
                ->pluck('n', 'user_id');

            $enquiriesByDealer = DB::table('messages')
                ->leftJoin('cars', 'cars.id', '=', 'messages.car_id')
                ->groupByRaw('COALESCE(messages.user_id, cars.user_id)')
                ->selectRaw('COALESCE(messages.user_id, cars.user_id) AS owner_id, COUNT(*) AS n')
                ->pluck('n', 'owner_id');

            $dealers = DB::table('users')->where('role', 'dealer')->orderBy('name')
                ->get(['id', 'name', 'is_active'])
                ->map(fn ($d) => [
                    'id'        => $d->id,
                    'name'      => $d->name,
                    'is_active' => (bool) $d->is_active,
                    'cars'      => (int) ($carStats[$d->id]->cars ?? 0),
                    'live_cars' => (int) ($carStats[$d->id]->live ?? 0),
                    'views'     => (int) ($carStats[$d->id]->views ?? 0),
                    'views_30d' => (int) ($views30ByDealer[$d->id] ?? 0),
                    'enquiries' => (int) ($enquiriesByDealer[$d->id] ?? 0),
                ])->values()->all();
        }

        return response()->json([
            'dealer'               => $dealerId,
            'total_cars'           => $cars()->count(),
            'available_cars'       => $cars()->where('is_available', true)->count(),
            'sold_cars'            => $cars()->where('is_available', false)->count(),
            'hidden_by_admin_cars' => $cars()->where('hidden_by_admin', true)->count(),
            'live_cars'            => Car::visible()->where('listing_type', 'sale')
                                          ->when($dealerId, fn ($q) => $q->where('user_id', $dealerId))->count(),
            'total_views'          => (int) $cars()->sum('views'),
            'views_30d'            => array_sum(array_column($daily, 'views')),
            'enquiries_30d'        => array_sum(array_column($daily, 'enquiries')),
            'total_enquiries'      => $messages()->count(),
            'inventory_value'      => (int) $cars()->where('is_available', true)->sum('price'),
            'top_cars'             => $topCars,
            'daily'                => $daily,
            'dealers'              => $dealers,
        ]);
    }
}
