<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CarFilterController extends Controller
{
    // PUBLIC: what the home page search box can offer (brands, models, years, source countries),
    // taken from the cars that are really for sale and visible right now.
    // The answer is kept for 60 seconds, so many visitors cost the database one lookup a minute.
    public function index()
    {
        try {
            $data = Cache::remember('car-filters', 60, fn () => $this->compute());
        } catch (\Throwable $e) {
            // If the cache is not working, answer straight from the database instead of failing.
            $data = $this->compute();
        }

        return response()->json($data);
    }

    private function compute(): array
    {
        $cars = fn () => Car::visible()->where('listing_type', 'sale');

        // Brands, the most stocked first
        $makes = $cars()
            ->select('make', DB::raw('COUNT(*) as total'))
            ->whereNotNull('make')->where('make', '!=', '')
            ->groupBy('make')
            ->orderByDesc('total')->orderBy('make')
            ->get()
            ->map(fn ($row) => ['name' => trim((string) $row->make), 'count' => (int) $row->total])
            ->values()
            ->all();

        // Models, grouped by brand: { "Toyota": ["Corolla", "Hilux"], ... }
        $models = [];
        $rows = $cars()
            ->select('make', 'model')
            ->whereNotNull('make')->whereNotNull('model')
            ->where('make', '!=', '')->where('model', '!=', '')
            ->distinct()
            ->orderBy('make')->orderBy('model')
            ->get();
        foreach ($rows as $row) {
            $models[trim((string) $row->make)][] = trim((string) $row->model);
        }

        $years = $cars()
            ->whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->values()
            ->all();

        $countries = $cars()
            ->whereNotNull('source_country')->where('source_country', '!=', '')
            ->distinct()
            ->orderBy('source_country')
            ->pluck('source_country')
            ->values()
            ->all();

        return [
            'makes'     => $makes,
            'models'    => (object) $models, // an empty list must still be sent as {}
            'years'     => $years,
            'countries' => $countries,
        ];
    }
}
