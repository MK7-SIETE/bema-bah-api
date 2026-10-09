<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroImage extends Model
{
    protected $fillable = ['filename', 'original_name', 'url', 'sort_order'];
}
