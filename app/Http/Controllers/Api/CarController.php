<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Services\CarImageService;
use App\Support\CarInput;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CarController extends Controller
{
    // ── PUBLIC: browse cars for sale ───────────────────
    public function index(Request $request)
    {
        $query = Car::with('images')->where('is_available', true)->where('listing_type', 'sale');

        if ($request->search) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('make', 'like', $s)
                  ->orWhere('model', 'like', $s)
                  ->orWhere('body_type', 'like', $s)
                  ->orWhere('fuel', 'like', $s);
            });
        }
        if ($request->make)           $query->where('make', $request->make);
        if ($request->body_type)      $query->where('body_type', $request->body_type);
        if ($request->fuel)           $query->where('fuel', $request->fuel);
        if ($request->transmission)   $query->where('transmission', $request->transmission);
        if ($request->source_country) $query->where('source_country', $request->source_country);
        if ($request->min_price)      $query->where('price', '>=', $request->min_price);
        if ($request->max_price)      $query->where('price', '<=', $request->max_price);
        if ($request->boolean('classic')) $query->where('is_classic', true);

        return response()->json($query->latest()->get());
    }

    // ── PUBLIC: classic cars section ───────────────────
    public function classics(Request $request)
    {
        $request->merge(['classic' => 1]);
        return $this->index($request);
    }

    // ── PUBLIC: single car ─────────────────────────────
    public function show(Car $car)
    {
        return response()->json($car->load('images'));
    }

    // ── PUBLIC: featured cars ──────────────────────────
    public function featured()
    {
        $cars = Car::with('images')
                   ->where('is_available', true)
                   ->where('listing_type', 'sale')
                   ->where('is_featured', true)
                   ->latest()
                   ->limit(6)
                   ->get();
        return response()->json($cars);
    }

    // ── DEALER: all cars scoped to this dealer ─────────
    public function dealerIndex(Request $request)
    {
        return response()->json(
            Car::with('images')
               ->where('user_id', $request->user()->id)
               ->latest()
               ->get()
        );
    }

    // ── DEALER: create car (details and photos in ONE request) ──
    public function store(Request $request, CarImageService $images)
    {
        $data = $request->validate($this->rules(true) + CarImageService::rules(false));

        $files = $request->file('images', []);
        unset($data['images']);

        $data = CarInput::clean($data);
        $data['user_id']      = $request->user()->id;
        $data['listing_type'] = 'sale';
        $data['mileage']      = $data['mileage'] ?? 0;

        $car = Car::create($data);

        if (! empty($files)) {
            try {
                $images->upload($car, $files);
            } catch (\RuntimeException $e) {
                // All or nothing: do not leave a car without the photos the dealer chose.
                $car->delete();
                return response()->json([
                    'message' => 'The car was not saved because its photos could not be uploaded. ' . $e->getMessage(),
                ], 500);
            }
        }

        return response()->json($car->load('images'), 201);
    }

    // ── DEALER: update car ─────────────────────────────
    public function update(Request $request, Car $car)
    {
        if ($car->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = CarInput::clean($request->validate($this->rules(false)));

        $car->update($data);
        return response()->json($car->load('images'));
    }

    // ── DEALER: delete car (and its photos on Cloudinary) ──
    public function destroy(Request $request, Car $car, CarImageService $images)
    {
        if ($car->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $images->deleteAllFor($car);
        $car->delete(); // image rows are removed with the car

        return response()->json(['message' => 'Car deleted.']);
    }

    // ── DEALER: toggle availability ────────────────────
    public function toggle(Request $request, Car $car)
    {
        if ($car->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $car->update(['is_available' => ! $car->is_available]);
        return response()->json($car);
    }

    // ── SUPER: all cars across all dealers ─────────────
    public function superIndex()
    {
        return response()->json(
            Car::with(['images', 'user:id,name,email'])
               ->latest()
               ->get()
        );
    }

    private function rules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'make'                => [$req, 'string', 'max:100'],
            'model'               => [$req, 'string', 'max:100'],
            'year'                => [$req, 'integer', 'min:1900', 'max:' . ((int) date('Y') + 1)],
            'price'               => [$req, 'integer', 'min:0'],
            'mileage'             => ['nullable', 'integer', 'min:0'],
            'fuel'                => [$req, 'string', 'max:50'],
            'transmission'        => [$req, 'string', 'max:50'],
            'color'               => ['nullable', 'string', 'max:80'],
            'body_type'           => [$req, 'string', Rule::in(Car::BODY_TYPES)],
            'condition'           => [$req, 'string', 'max:80'],
            'engine'              => ['nullable', 'string', 'max:80'],
            'power'               => ['nullable', 'string', 'max:80'],
            'torque'              => ['nullable', 'string', 'max:80'],
            'seats'               => ['nullable', 'integer', 'min:1', 'max:60'],
            'doors'               => ['nullable', 'integer', 'min:1', 'max:10'],
            'drive'               => ['nullable', 'string', 'max:20'],
            'drive_side'          => ['nullable', 'string', 'max:40'],
            'vin'                 => ['nullable', 'string', 'max:30'],
            'previous_owners'     => ['nullable', 'integer', 'min:0', 'max:20'],
            'registration_status' => ['nullable', 'string', 'max:60'],
            'source_country'      => ['nullable', Rule::in(Car::SOURCE_COUNTRIES)],
            'is_classic'          => ['boolean'],
            'description'         => ['nullable', 'string', 'max:5000'],
            'features'            => ['nullable', 'array'],
            'features.*'          => ['string'],
            'is_featured'         => ['boolean'],
            'is_available'        => ['boolean'],
        ];
    }
}
