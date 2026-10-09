<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    protected $fillable = [
        'user_id',
        'listing_type',
        'make',
        'model',
        'year',
        'price',
        'rental_price_per_day',
        'location',
        'source_country',
        'is_classic',
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
        'drive_side',
        'vin',
        'previous_owners',
        'registration_status',
        'description',
        'features',
        'is_featured',
        'is_available',
    ];

    protected $casts = [
        'features'     => 'array',
        'is_featured'  => 'boolean',
        'is_available' => 'boolean',
        'is_classic'   => 'boolean',
    ];

    // Included automatically whenever a car is returned as JSON.
    protected $appends = ['vehicle_code'];

    // Shown to dealers and inside enquiries, e.g. BB-0012.
    public function getVehicleCodeAttribute(): string
    {
        return sprintf('BB-%04d', $this->id);
    }

    public function images()
    {
        return $this->hasMany(CarImage::class)->orderBy('sort_order');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
