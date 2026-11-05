<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroImage extends Model
{
    // Which page a photo belongs to. The home page slideshow and the rentals page slider each have their own set.
    public const PAGES        = ['home', 'rentals'];
    public const MAX_PER_PAGE = 6;

    // content_hash must be listed here, otherwise Laravel silently drops it and duplicate uploads are never detected.
    // title and subtitle are the optional caption shown on the home page hero.
    protected $fillable = ['filename', 'original_name', 'url', 'sort_order', 'content_hash', 'page', 'title', 'subtitle'];
}
