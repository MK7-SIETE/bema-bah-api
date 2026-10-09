<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Http\Request;

class RentalCarController extends Controller
{
    // ── PUBLIC: browse rental cars ─────────────────────
    public function publicIndex(Request $request)
    {
        $query = Car::with('images')
            ->where('listing_type', 'rental')
            ->where('is_available', true);

        if ($request->search) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('make', 'like', $s)
                  ->orWhere('model', 'like', $s)
                  ->orWhere('body_type', 'like', $s)
                  ->orWhere('location', 'like', $s);
            });
        }
        if ($request->location)  $query->where('location', 'like', '%' . $request->location . '%');
        if ($request->body_type) $query->where('body_type', $request->body_type);
        if ($request->max_price) $query->where('rental_price_per_day', '<=', (int) $request->max_price);

        return response()->json($query->latest()->limit(100)->get());
    }

    // ── PUBLIC: single rental car ──────────────────────
    public function publicShow(Car $car)
    {
        abort_unless($car->listing_type === 'rental' && $car->is_available, 404);
        return response()->json($car->load('images'));
    }

    // ── RENTAL ADMIN: own rental cars ──────────────────
    public function index(Request $request)
    {
        return response()->json(
            Car::with('images')
               ->where('user_id', $request->user()->id)
               ->where('listing_type', 'rental')
               ->latest()
               ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(true));

        $data['user_id']      = $request->user()->id;
        $data['listing_type'] = 'rental';
        $data['price']        = null;
        $data['mileage']      = $data['mileage'] ?? 0;
        $data['condition']    = $data['condition'] ?? 'Used';

        $car = Car::create($data);
        return response()->json($car->load('images'), 201);
    }

    public function update(Request $request, Car $car)
    {
        if (! $this->owns($request, $car)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate($this->rules(false));

        if (array_key_exists('mileage', $data))   $data['mileage'] = $data['mileage'] ?? 0;
        if (array_key_exists('condition', $data) && $data['condition'] === null) unset($data['condition']);

        $car->update($data);
        return response()->json($car->load('images'));
    }

    public function destroy(Request $request, Car $car)
    {
        if (! $this->owns($request, $car)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Note: photos stored on Cloudinary are cleaned up in a later patch.
        $car->images()->delete();
        $car->delete();
        return response()->json(['message' => 'Rental car deleted.']);
    }

    public function toggle(Request $request, Car $car)
    {
        if (! $this->owns($request, $car)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $car->update(['is_available' => ! $car->is_available]);
        return response()->json($car);
    }

    private function owns(Request $request, Car $car): bool
    {
        return $car->user_id === $request->user()->id && $car->listing_type === 'rental';
    }

    private function rules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'make'                 => [$req, 'string', 'max:100'],
            'model'                => [$req, 'string', 'max:100'],
            'year'                 => [$req, 'integer', 'min:1900', 'max:' . ((int) date('Y') + 1)],
            'rental_price_per_day' => [$req, 'integer', 'min:1'],
            'location'             => [$req, 'string', 'max:150'],
            'body_type'            => [$req, 'string', 'max:80'],
            'fuel'                 => [$req, 'string', 'max:50'],
            'transmission'         => [$req, 'string', 'max:50'],
            'condition'            => ['nullable', 'string', 'max:80'],
            'color'                => ['nullable', 'string', 'max:80'],
            'mileage'              => ['nullable', 'integer', 'min:0'],
            'engine'               => ['nullable', 'string', 'max:80'],
            'seats'                => ['nullable', 'integer', 'min:1', 'max:60'],
            'doors'                => ['nullable', 'integer', 'min:1', 'max:10'],
            'drive'                => ['nullable', 'string', 'max:20'],
            'drive_side'           => ['nullable', 'in:LHD,RHD'],
            'description'          => ['nullable', 'string', 'max:5000'],
            'features'             => ['nullable', 'array'],
            'features.*'           => ['string'],
            'is_available'         => ['boolean'],
        ];
    }
}
