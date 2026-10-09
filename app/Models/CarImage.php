<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CarImage extends Model
{
    protected $fillable = ['car_id', 'path', 'sort_order'];

    public $timestamps = true;

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function getUrlAttribute(): string
    {
        // Access raw attribute directly to avoid infinite recursion
        $path = $this->attributes['path'] ?? '';

        // If path is already a full URL, return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    protected $appends = ['url'];
    protected $hidden  = ['path'];
}
