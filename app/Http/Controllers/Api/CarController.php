<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Http\Request;

class CarController extends Controller
{
    // ── PUBLIC: browse cars ────────────────────────────
    public function index(Request $request)
    {
        $query = Car::with('images')->where('is_available', true);

        if ($request->search) {
            $s = '%' . $request->search . '%';
            $query->where(function($q) use ($s) {
                $q->where('make', 'like', $s)
                  ->orWhere('model', 'like', $s)
                  ->orWhere('body_type', 'like', $s)
                  ->orWhere('fuel', 'like', $s);
            });
        }
        if ($request->make)         $query->where('make', $request->make);
        if ($request->body_type)    $query->where('body_type', $request->body_type);
        if ($request->fuel)         $query->where('fuel', $request->fuel);
        if ($request->transmission) $query->where('transmission', $request->transmission);
        if ($request->min_price)    $query->where('price', '>=', $request->min_price);
        if ($request->max_price)    $query->where('price', '<=', $request->max_price);

        $cars = $query->latest()->get();
        return response()->json($cars);
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

    // ── DEALER: create car ─────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'make'         => ['required', 'string', 'max:100'],
            'model'        => ['required', 'string', 'max:100'],
            'year'         => ['required', 'integer', 'min:1990', 'max:2030'],
            'price'        => ['required', 'integer', 'min:0'],
            'mileage'      => ['nullable', 'integer', 'min:0'],
            'fuel'         => ['required', 'string', 'max:50'],
            'transmission' => ['required', 'string', 'max:50'],
            'color'        => ['nullable', 'string', 'max:80'],
            'body_type'    => ['required', 'string', 'max:80'],
            'condition'    => ['required', 'string', 'max:80'],
            'engine'       => ['nullable', 'string', 'max:80'],
            'power'        => ['nullable', 'string', 'max:80'],
            'torque'       => ['nullable', 'string', 'max:80'],
            'seats'        => ['nullable', 'integer', 'min:1', 'max:20'],
            'doors'        => ['nullable', 'integer', 'min:1', 'max:10'],
            'drive'        => ['nullable', 'string', 'max:20'],
            'description'  => ['nullable', 'string'],
            'features'     => ['nullable', 'array'],
            'features.*'   => ['string'],
            'is_featured'  => ['boolean'],
            'is_available' => ['boolean'],
        ]);

        $data['user_id'] = $request->user()->id;

        $car = Car::create($data);
        return response()->json($car->load('images'), 201);
    }

    // ── DEALER: update car ─────────────────────────────
    public function update(Request $request, Car $car)
    {
        if ($car->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'make'         => ['sometimes', 'string', 'max:100'],
            'model'        => ['sometimes', 'string', 'max:100'],
            'year'         => ['sometimes', 'integer', 'min:1990', 'max:2030'],
            'price'        => ['sometimes', 'integer', 'min:0'],
            'mileage'      => ['nullable', 'integer', 'min:0'],
            'fuel'         => ['sometimes', 'string', 'max:50'],
            'transmission' => ['sometimes', 'string', 'max:50'],
            'color'        => ['nullable', 'string', 'max:80'],
            'body_type'    => ['sometimes', 'string', 'max:80'],
            'condition'    => ['sometimes', 'string', 'max:80'],
            'engine'       => ['nullable', 'string', 'max:80'],
            'power'        => ['nullable', 'string', 'max:80'],
            'torque'       => ['nullable', 'string', 'max:80'],
            'seats'        => ['nullable', 'integer'],
            'doors'        => ['nullable', 'integer'],
            'drive'        => ['nullable', 'string', 'max:20'],
            'description'  => ['nullable', 'string'],
            'features'     => ['nullable', 'array'],
            'features.*'   => ['string'],
            'is_featured'  => ['boolean'],
            'is_available' => ['boolean'],
        ]);

        $car->update($data);
        return response()->json($car->load('images'));
    }

    // ── DEALER: delete car ─────────────────────────────
    public function destroy(Request $request, Car $car)
    {
        if ($car->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        foreach ($car->images as $img) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($img->path);
            $img->delete();
        }
        $car->delete();
        return response()->json(['message' => 'Car deleted.']);
    }

    // ── DEALER: toggle availability ────────────────────
    public function toggle(Request $request, Car $car)
    {
        if ($car->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $car->update(['is_available' => !$car->is_available]);
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
}