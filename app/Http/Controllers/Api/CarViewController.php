<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CarViewController extends Controller
{
    // ── PUBLIC: "this visitor opened this car" ─────────────────────
    // POST /api/cars/{car}/view   { "visitor": "<random id kept in the visitor's browser>" }
    // Counts ONE view per visitor per car per day. Opening the page again, or refreshing, adds nothing.
    // This is a separate endpoint on purpose: CarController::show is also used by the dealer's edit form,
    // and must not count the dealer's own visits.
    public function store(Request $request, Car $car)
    {
        $data = $request->validate([
            'visitor' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        // Only listings the public can really see count: not sold, not hidden by the admin, owner active.
        if (! Car::visible()->whereKey($car->id)->exists()) {
            return response()->json(['counted' => false]);
        }

        // The raw id from the browser is never stored, only a one-way hash of it.
        $hash = hash('sha256', $data['visitor'] . '|' . config('app.key'));

        // insertOrIgnore returns 1 for a new row and 0 when this visitor already counted today.
        $inserted = DB::table('car_views')->insertOrIgnore([
            'car_id'       => $car->id,
            'visitor_hash' => $hash,
            'viewed_on'    => now()->toDateString(),
            'created_at'   => now(),
        ]);

        if ($inserted) {
            DB::table('cars')->where('id', $car->id)->update(['views' => DB::raw('COALESCE(views, 0) + 1')]);
        }

        return response()->json(['counted' => (bool) $inserted]);
    }
}
