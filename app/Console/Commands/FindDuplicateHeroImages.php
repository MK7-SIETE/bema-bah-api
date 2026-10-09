<?php

namespace App\Console\Commands;

use App\Models\HeroImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FindDuplicateHeroImages extends Command
{
    protected $signature = 'hero-images:dedupe {--delete : Actually delete the duplicates found (Cloudinary + DB row). Without this flag, it only lists them.}';
    protected $description = 'Find hero images that are byte-for-byte duplicates already stored on Cloudinary, and optionally remove the extras.';

    public function handle(): int
    {
        $images = HeroImage::orderBy('id')->get();

        if ($images->isEmpty()) {
            $this->info('No hero images found.');
            return self::SUCCESS;
        }

        $this->info("Hashing {$images->count()} remote images...");

        $hashes = [];
        $duplicates = [];

        $bar = $this->output->createProgressBar($images->count());
        $bar->start();

        foreach ($images as $image) {
            $response = Http::timeout(30)->get($image->url);

            if (!$response->successful()) {
                $this->newLine();
                $this->warn("Could not download #{$image->id} ({$image->url}), skipping.");
                $bar->advance();
                continue;
            }

            $hash = hash('sha256', $response->body());

            if (isset($hashes[$hash])) {
                $duplicates[] = ['keep' => $hashes[$hash], 'remove' => $image];
            } else {
                $hashes[$hash] = $image;
                if (!$image->content_hash) {
                    $image->update(['content_hash' => $hash]); // backfill while we're here
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if (empty($duplicates)) {
            $this->info('No duplicates found.');
            return self::SUCCESS;
        }

        $this->warn(count($duplicates) . ' duplicate(s) found:');
        foreach ($duplicates as $dup) {
            $this->line("  Keeping #{$dup['keep']->id} ({$dup['keep']->original_name}) — removing #{$dup['remove']->id} ({$dup['remove']->original_name})");
        }

        if (!$this->option('delete')) {
            $this->newLine();
            $this->comment('Dry run only. Re-run with --delete to actually remove the duplicates above.');
            return self::SUCCESS;
        }

        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey    = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');

        foreach ($duplicates as $dup) {
            $publicId  = $dup['remove']->filename;
            $timestamp = time();
            $signature = sha1("public_id={$publicId}&timestamp={$timestamp}{$apiSecret}");

            Http::post("https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy", [
                'public_id' => $publicId,
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
            ]);

            $dup['remove']->delete();
        }

        $this->info(count($duplicates) . ' duplicate(s) removed from Cloudinary and the database.');
        return self::SUCCESS;
    }
}
