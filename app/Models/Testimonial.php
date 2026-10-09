<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = ['name', 'car_bought', 'message', 'rating', 'status'];

    protected $casts = [
        'rating' => 'integer',
    ];
}