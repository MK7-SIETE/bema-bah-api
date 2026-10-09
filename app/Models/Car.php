<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    protected $fillable = [
        'user_id',
        'make',
        'model',
        'year',
        'price',
        'mileage',
        'fuel',
        'transmission',
        'color',
        'body_type',
        'condition',
        'engine',
        'power',
        'torque',
        'seats',
        'doors',
        'drive',
        'description',
        'features',
        'is_featured',
        'is_available',
    ];

    protected $casts = [
        'features'     => 'array',
        'is_featured'  => 'boolean',
        'is_available' => 'boolean',
    ];

    public function images()
    {
        return $this->hasMany(CarImage::class)->orderBy('sort_order');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}