<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HeroImageController extends Controller
{
    // PUBLIC — all hero images
    public function index()
    {
        return response()->json(
            HeroImage::orderBy('sort_order')->get()
        );
    }

    // SUPER — list all
    public function superIndex()
    {
        return response()->json(
            HeroImage::orderBy('sort_order')->get()
        );
    }

    // SUPER — upload to Cloudinary
    public function store(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
        ]);

        $file = $request->file('image');
        $contentHash = hash_file('sha256', $file->getRealPath());

        $existing = HeroImage::where('content_hash', $contentHash)->first();
        if ($existing) {
            return response()->json([
                'message' => 'This image has already been uploaded.',
                'hero'    => $existing,
            ], 409);
        }

        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey    = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');
        $folder    = config('services.cloudinary.hero_folder', 'mukuba-motors/heroes');
        $timestamp = time();
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

        $cloudUrl = $response->json('secure_url');
        $publicId = $response->json('public_id');

        try {
            $hero = DB::transaction(function () use ($publicId, $file, $cloudUrl, $contentHash) {
                $nextSort = (HeroImage::lockForUpdate()->max('sort_order') ?? 0) + 1;

                return HeroImage::create([
                    'filename'      => $publicId,
                    'original_name' => $file->getClientOriginalName(),
                    'url'           => $cloudUrl,
                    'sort_order'    => $nextSort,
                    'content_hash'  => $contentHash,
                ]);
            });
        } catch (\Throwable $e) {
            $cleanupTimestamp = time();
            $cleanupSignature = sha1("public_id={$publicId}&timestamp={$cleanupTimestamp}{$apiSecret}");
            Http::post("https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy", [
                'public_id' => $publicId,
                'api_key'   => $apiKey,
                'timestamp' => $cleanupTimestamp,
                'signature' => $cleanupSignature,
            ]);

            Log::error('HeroImage DB write failed after Cloudinary upload; asset cleaned up.', [
                'public_id' => $publicId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to save hero image.'], 500);
        }

        return response()->json($hero, 201);
    }

    // SUPER — delete from Cloudinary
    public function destroy(HeroImage $heroImage)
    {
        try {
            $cloudName = config('services.cloudinary.cloud_name');
            $apiKey    = config('services.cloudinary.api_key');
            $apiSecret = config('services.cloudinary.api_secret');
            $publicId  = $heroImage->filename;
            $timestamp = time();
            $signature = sha1("public_id={$publicId}&timestamp={$timestamp}{$apiSecret}");

            $response = Http::post("https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy", [
                'public_id' => $publicId,
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
            ]);

            if (!$response->successful()) {
                Log::warning('Cloudinary destroy failed', [
                    'public_id' => $publicId,
                    'response'  => $response->json(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Cloudinary destroy threw an exception', [
                'public_id' => $heroImage->filename,
                'error'     => $e->getMessage(),
            ]);
        }

        $heroImage->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
