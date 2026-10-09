<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarImage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ImageController extends Controller
{
    // ── Upload to Cloudinary ───────────────────────────────────
    public function upload(Request $request, Car $car)
    {
        $this->authorizeDealer($request, $car);

        $request->validate([
            'images'   => ['required', 'array', 'min:1', 'max:15'],
            'images.*' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!$value instanceof UploadedFile || !$value->isValid()) {
                        $reason = $value instanceof UploadedFile
                            ? $value->getErrorMessage()
                            : 'File was not received by the server at all.';
                        $fail("Upload rejected before validation: {$reason}");
                        return;
                    }

                    if ($value->getSize() > 5120 * 1024) {
                        $fail('The ' . $attribute . ' may not be larger than 5MB.');
                        return;
                    }

                    if (!$this->isValidImageSignature($value->getRealPath())) {
                        $fail('The ' . $attribute . ' must be a valid jpeg, png, AVIF or webp image.');
                    }
                },
            ],
        ]);

        $uploaded  = [];
        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey    = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');

        foreach ($request->file('images') as $file) {
            $timestamp = time();
            $folder    = 'mukuba-motors/cars/' . $car->id;
            $signature = sha1("folder={$folder}&timestamp={$timestamp}{$apiSecret}");

            $response = Http::attach(
                'file',
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName()
            )->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
                'folder'    => $folder,
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Cloudinary upload failed',
                    'error'   => $response->json(),
                ], 500);
            }

            $cloudUrl  = $response->json('secure_url');
            $publicId  = $response->json('public_id');
            $sortOrder = (DB::table('car_images')->where('car_id', $car->id)->max('sort_order') ?? 0) + 1;

            $imageId = DB::table('car_images')->insertGetId([
                'car_id'     => $car->id,
                'path'       => $cloudUrl,
                'sort_order' => $sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $uploaded[] = [
                'id'         => $imageId,
                'car_id'     => $car->id,
                'sort_order' => $sortOrder,
                'url'        => $cloudUrl,
                'public_id'  => $publicId,
            ];
        }

        return response()->json($uploaded, 201);
    }

    // ── Delete from Cloudinary ─────────────────────────────────
    public function destroy(Request $request, Car $car, CarImage $img)
    {
        $this->authorizeDealer($request, $car);

        if ($img->car_id !== $car->id) {
            abort(403, 'Image does not belong to this car.');
        }

        $path = $img->attributes['path'] ?? '';
        if (str_contains($path, 'cloudinary.com')) {
            $this->deleteFromCloudinary($path);
        }

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

    // ── Helpers ────────────────────────────────────────────────
    private function authorizeDealer(Request $request, Car $car): void
    {
        if ($car->user_id !== $request->user()->id && ! $request->user()->isSuper()) {
            abort(403, 'Unauthorized.');
        }
    }

    private function isValidImageSignature(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $header = fread($handle, 12);
        fclose($handle);

        if (strlen($header) < 12) {
            return false;
        }

        $isJpeg = substr($header, 0, 2) === "\xFF\xD8";
        $isPng  = substr($header, 0, 8) === "\x89PNG\r\n\x1a\n";
        $isWebp = substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP';

        return $isJpeg || $isPng || $isWebp || $isAvif;
    }

    private function deleteFromCloudinary(string $url): void
    {
        try {
            $cloudName = config('services.cloudinary.cloud_name');
            $apiKey    = config('services.cloudinary.api_key');
            $apiSecret = config('services.cloudinary.api_secret');

            preg_match('/upload\/(?:v\d+\/)?(.+)\.[a-z]+$/i', $url, $matches);
            if (empty($matches[1])) return;

            $publicId  = $matches[1];
            $timestamp = time();
            $signature = sha1("public_id={$publicId}&timestamp={$timestamp}{$apiSecret}");

            Http::post("https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy", [
                'public_id' => $publicId,
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
            ]);
        } catch (\Throwable $e) {
            // Don't fail the request if Cloudinary delete fails
        }
    }
}
