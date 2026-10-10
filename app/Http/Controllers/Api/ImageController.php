<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarImage;
use App\Services\CarImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImageController extends Controller
{
    // ── Upload to Cloudinary (add photos to an existing car) ──
    public function upload(Request $request, Car $car, CarImageService $images)
    {
        $this->authorizeDealer($request, $car);

        $request->validate(CarImageService::rules(true));

        try {
            $uploaded = $images->upload($car, $request->file('images'));
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'Cloudinary upload failed',
                'error'   => $e->getMessage(),
            ], 500);
        }

        return response()->json($uploaded, 201);
    }

    // ── Delete one photo (also from Cloudinary) ───────────────
    public function destroy(Request $request, Car $car, CarImage $img, CarImageService $images)
    {
        $this->authorizeDealer($request, $car);

        if ($img->car_id !== $car->id) {
            abort(403, 'Image does not belong to this car.');
        }

        $images->deleteRemote((string) $img->getRawOriginal('path'));

        $img->delete();
        return response()->json(['message' => 'Image deleted.']);
    }

    // ── Reorder ────────────────────────────────────────────────
    public function reorder(Request $request, Car $car)
    {
        $this->authorizeDealer($request, $car);

        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        foreach ($request->order as $sortOrder => $imageId) {
            DB::table('car_images')
                ->where('id', $imageId)
                ->where('car_id', $car->id)
                ->update(['sort_order' => $sortOrder]);
        }

        return response()->json(['message' => 'Order updated.']);
    }

    private function authorizeDealer(Request $request, Car $car): void
    {
        if ($car->user_id !== $request->user()->id && ! $request->user()->isSuper()) {
            abort(403, 'Unauthorized.');
        }
    }
}
