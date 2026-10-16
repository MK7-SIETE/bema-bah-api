<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\SearchesCars;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Services\CarImageService;
use App\Support\CarInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CarController extends Controller
{
    use SearchesCars;

    // ── PUBLIC: browse cars for sale (typo-tolerant search + "load more" paging) ──
    public function index(Request $request)
    {
        $query = Car::visible()->where('listing_type', 'sale');

        if ($request->make)           $query->where('make', $request->make);
        if ($request->body_type)      $query->where('body_type', $request->body_type);
        if ($request->fuel)           $query->where('fuel', $request->fuel);
        if ($request->transmission)   $query->where('transmission', $request->transmission);
        if ($request->source_country) $query->where('source_country', $request->source_country);
        if ($request->min_price)      $query->where('price', '>=', $request->min_price);
        if ($request->max_price)      $query->where('price', '<=', $request->max_price);
        if ($request->boolean('classic')) $query->where('is_classic', true);

        // SETTING 3: cars per batch for customers (12 by default, never more than 48)
        $perPage = min(max((int) $request->input('per_page', 12), 1), 48);

        return response()->json(
            $this->searchAndPaginate($query, $request, $perPage, ['images'])
        );
    }

    // ── PUBLIC: classic cars section ───────────────────
    public function classics(Request $request)
    {
        $request->merge(['classic' => 1]);
        return $this->index($request);
    }

    // ── PUBLIC: single car ─────────────────────────────
    // NOTE: this is also what the dealer's edit form loads, so it still returns hidden cars.
    public function show(Car $car)
    {
        return response()->json($car->load('images'));
    }

    // ── PUBLIC: featured cars ──────────────────────────
    public function featured()
    {
        $cars = Car::with('images')
                   ->visible()
                   ->where('listing_type', 'sale')
                   ->where('is_featured', true)
                   ->latest()
                   ->limit(6)
                   ->get();
        return response()->json($cars);
    }

    // ── DEALER: this dealer's cars (search by text or vehicle ID, filters, page numbers) ──
    public function dealerIndex(Request $request)
    {
        $query = Car::where('user_id', $request->user()->id);
        $this->applyStaffFilters($query, $request);

        // SETTING 4: cars per page in the staff lists
        return response()->json(
            $this->searchAndPaginate($query, $request, 15, ['images'], true)
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

    // ── DEALER or SUPER: update car ────────────────────
    // The super admin sends only the switches it controls (is_available, is_featured, is_classic).
    public function update(Request $request, Car $car)
    {
        if (! $this->canManage($request, $car)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = CarInput::clean($request->validate($this->rules(false)));

        $car->update($data);
        return response()->json($car->load('images'));
    }

    // ── DEALER or SUPER: delete car (its photos on Cloudinary are removed after the response) ──
    public function destroy(Request $request, Car $car, CarImageService $images)
    {
        if (! $this->canManage($request, $car)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $urls = DB::table('car_images')->where('car_id', $car->id)->pluck('path');

        $car->delete(); // image rows are removed with the car
        $images->deleteRemoteAfterResponse($urls);

        return response()->json(['message' => 'Car deleted.']);
    }

    // ── DEALER or SUPER: toggle availability (hide / show on the public site) ──
    public function toggle(Request $request, Car $car)
    {
        if (! $this->canManage($request, $car)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $car->update(['is_available' => ! $car->is_available]);
        return response()->json($car);
    }

    // ── SUPER: one action on many cars, in ONE database call ──
    // POST { "ids": [1,2,3], "action": "hide" | "show" | "feature" | "unfeature" | "classic" | "unclassic" | "delete" }
    public function bulk(Request $request, CarImageService $images)
    {
        $data = $request->validate([
            'ids'    => ['required', 'array', 'min:1', 'max:100'],
            'ids.*'  => ['integer'],
            'action' => ['required', Rule::in(['hide', 'show', 'feature', 'unfeature', 'classic', 'unclassic', 'delete'])],
        ]);

        $switches = [
            'hide'      => ['is_available' => false],
            'show'      => ['is_available' => true],
            'feature'   => ['is_featured'  => true],
            'unfeature' => ['is_featured'  => false],
            'classic'   => ['is_classic'   => true],
            'unclassic' => ['is_classic'   => false],
        ];

        if ($data['action'] === 'delete') {
            $urls     = DB::table('car_images')->whereIn('car_id', $data['ids'])->pluck('path');
            $affected = Car::whereIn('id', $data['ids'])->delete();
            $images->deleteRemoteAfterResponse($urls);
        } else {
            $affected = Car::whereIn('id', $data['ids'])->update($switches[$data['action']]);
        }

        return response()->json(['affected' => $affected]);
    }

    // ── SUPER: all cars across all dealers (search by text or vehicle ID, filters, page numbers) ──
    public function superIndex(Request $request)
    {
        $query = Car::query();
        $this->applyStaffFilters($query, $request, true);

        return response()->json(
            $this->searchAndPaginate($query, $request, 15, ['images', 'user:id,name,email,is_active'], true)
        );
    }

    // The ONE ownership rule for car actions: the owner, or the super admin.
    private function canManage(Request $request, Car $car): bool
    {
        $user = $request->user();
        return $user->isSuper() || $car->user_id === $user->id;
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
