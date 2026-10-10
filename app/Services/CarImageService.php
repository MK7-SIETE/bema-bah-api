<?php

namespace App\Services;

use App\Models\Car;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CarImageService
{
    public const MAX_FILES = 15;
    public const MAX_KB    = 5120; // 5 MB per photo

    /** Validation rules for an images[] upload (used by every endpoint that accepts photos). */
    public static function rules(bool $required): array
    {
        return [
            'images' => $required
                ? ['required', 'array', 'min:1', 'max:' . self::MAX_FILES]
                : ['nullable', 'array', 'max:' . self::MAX_FILES],
            'images.*' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (! $value instanceof UploadedFile || ! $value->isValid()) {
                        $reason = $value instanceof UploadedFile
                            ? $value->getErrorMessage()
                            : 'File was not received by the server at all.';
                        $fail("Upload rejected before validation: {$reason}");
                        return;
                    }

                    if ($value->getSize() > self::MAX_KB * 1024) {
                        $fail('The ' . $attribute . ' may not be larger than 5MB.');
                        return;
                    }

                    if (! self::isValidImageSignature($value->getRealPath())) {
                        $fail('The ' . $attribute . ' must be a valid jpeg, png, AVIF or webp image.');
                    }
                },
            ],
        ];
    }

    /**
     * Upload photos to Cloudinary and save them against the car.
     * All or nothing: if one photo fails, the ones already stored are removed again.
     *
     * @param  UploadedFile[]  $files
     * @return array<int, array<string, mixed>>
     */
    public function upload(Car $car, array $files): array
    {
        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey    = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');

        if (! $cloudName || ! $apiKey || ! $apiSecret) {
            throw new \RuntimeException('Image storage (Cloudinary) is not configured on the server.');
        }

        $folder    = trim((string) config('services.cloudinary.folder', 'bema-bah/cars'), '/') . '/' . $car->id;
        $sortOrder = (int) (DB::table('car_images')->where('car_id', $car->id)->max('sort_order') ?? 0);
        $uploaded  = [];

        try {
            foreach ($files as $file) {
                $timestamp = time();
                $signature = sha1("folder={$folder}&timestamp={$timestamp}{$apiSecret}");

                $response = Http::timeout(60)
                    ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                    ->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                        'api_key'   => $apiKey,
                        'timestamp' => $timestamp,
                        'signature' => $signature,
                        'folder'    => $folder,
                    ]);

                if (! $response->successful()) {
                    throw new \RuntimeException(
                        'Cloudinary rejected an image: ' . ($response->json('error.message') ?? 'HTTP ' . $response->status())
                    );
                }

                $url = $response->json('secure_url');
                $sortOrder++;

                $imageId = DB::table('car_images')->insertGetId([
                    'car_id'     => $car->id,
                    'path'       => $url,
                    'sort_order' => $sortOrder,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $uploaded[] = [
                    'id'         => $imageId,
                    'car_id'     => $car->id,
                    'sort_order' => $sortOrder,
                    'url'        => $url,
                    'public_id'  => $response->json('public_id'),
                ];
            }
        } catch (\Throwable $e) {
            foreach ($uploaded as $img) {
                $this->deleteRemote($img['url']);
                DB::table('car_images')->where('id', $img['id'])->delete();
            }

            throw $e instanceof \RuntimeException
                ? $e
                : new \RuntimeException('Image upload failed.', 0, $e);
        }

        return $uploaded;
    }

    /** Remove every photo of a car from Cloudinary (database rows go with the car). */
    public function deleteAllFor(Car $car): void
    {
        foreach ($car->images as $img) {
            $this->deleteRemote((string) $img->getRawOriginal('path'));
        }
    }

    public function deleteRemote(string $url): void
    {
        if (! str_contains($url, 'cloudinary.com')) {
            return;
        }

        try {
            $cloudName = config('services.cloudinary.cloud_name');
            $apiKey    = config('services.cloudinary.api_key');
            $apiSecret = config('services.cloudinary.api_secret');

            preg_match('/upload\/(?:v\d+\/)?(.+)\.[a-z0-9]+$/i', $url, $matches);
            if (empty($matches[1])) {
                return;
            }

            $publicId  = $matches[1];
            $timestamp = time();
            $signature = sha1("public_id={$publicId}&timestamp={$timestamp}{$apiSecret}");

            Http::timeout(30)->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy", [
                'public_id' => $publicId,
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
            ]);
        } catch (\Throwable $e) {
            report($e); // never block the request because a remote delete failed
        }
    }

    private static function isValidImageSignature(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $header = fread($handle, 12);
        fclose($handle);

        if ($header === false || strlen($header) < 12) {
            return false;
        }

        $isJpeg = substr($header, 0, 2) === "\xFF\xD8";
        $isPng  = substr($header, 0, 8) === "\x89PNG\r\n\x1a\n";
        $isWebp = substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP';
        $isAvif = substr($header, 4, 4) === 'ftyp' && in_array(substr($header, 8, 4), ['avif', 'avis'], true);

        return $isJpeg || $isPng || $isWebp || $isAvif;
    }
}
