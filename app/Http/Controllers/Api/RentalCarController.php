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

class RentalCarController extends Controller
{
    use SearchesCars;

    // ── PUBLIC: browse rental cars (typo-tolerant search + "load more" paging) ──
    public function publicIndex(Request $request)
    {
        $query = Car::where('listing_type', 'rental')->visible();

        if ($request->location)  $query->where('location', 'like', '%' . $request->location . '%');
        if ($request->body_type) $query->where('body_type', $request->body_type);
        if ($request->max_price) $query->where('rental_price_per_day', '<=', (int) $request->max_price);

        $perPage = min(max((int) $request->input('per_page', 12), 1), 48);

        return response()->json(
            $this->searchAndPaginate($query, $request, $perPage, ['images'])
        );
    }

    // ── PUBLIC: single rental car ──────────────────────
    public function publicShow(Car $car)
    {
        $shown = Car::visible()
            ->where('listing_type', 'rental')
            ->whereKey($car->id)
            ->exists();

        abort_unless($shown, 404);
        return response()->json($car->load('images'));
    }

    // ── RENTAL ADMIN: own rental cars (search by text or vehicle ID, filters, page numbers) ──
    public function index(Request $request)
    {
        $query = Car::where('user_id', $request->user()->id)
                    ->where('listing_type', 'rental');
        $this->applyStaffFilters($query, $request);

        return response()->json(
            $this->searchAndPaginate($query, $request, 15, ['images'], true)
        );
    }

    public function store(Request $request, CarImageService $images)
    {
        $data = $request->validate($this->rules(true) + CarImageService::rules(false));

        $files = $request->file('images', []);
        unset($data['images']);

        $data = CarInput::clean($data);
        $data['user_id']      = $request->user()->id;
        $data['listing_type'] = 'rental';
        $data['price']        = null;
        $data['mileage']      = $data['mileage'] ?? 0;
        $data['condition']    = $data['condition'] ?? 'Used';

        $car = Car::create($data);

        if (! empty($files)) {
            try {
                $images->upload($car, $files);
            } catch (\RuntimeException $e) {
                $car->delete();
                return response()->json([
                    'message' => 'The car was not saved because its photos could not be uploaded. ' . $e->getMessage(),
                ], 500);
            }
        }

        return response()->json($car->load('images'), 201);
    }

    public function update(Request $request, Car $car)
    {
        if (! $this->owns($request, $car)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = CarInput::clean($request->validate($this->rules(false)));

        $car->update($data);
        return response()->json($car->load('images'));
    }

    public function destroy(Request $request, Car $car, CarImageService $images)
    {
        if (! $this->owns($request, $car)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $urls = DB::table('car_images')->where('car_id', $car->id)->pluck('path');

        $car->delete(); // image rows are removed with the car
        $images->deleteRemoteAfterResponse($urls);

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
            'body_type'            => [$req, 'string', Rule::in(Car::BODY_TYPES)],
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
