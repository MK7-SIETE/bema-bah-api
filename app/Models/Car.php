<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    // Each body type has its own icon on the site.
    public const BODY_TYPES = [
        'Sedan', 'SUV', 'Pickup', 'Van', 'Minibus',
        'Hatchback', 'Coupe', 'Wagon', 'Convertible', 'Truck',
    ];

    public const SOURCE_COUNTRIES = ['Local', 'Australia', 'UAE', 'South Africa'];

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
    public function getVehicleCodeAttribute(): ?string
    {
        return $this->id ? sprintf('BB-%04d', $this->id) : null;
    }

    // Accepts "Right-hand drive (RHD)", "rhd", "LHD" ... and stores RHD or LHD.
    public function setDriveSideAttribute($value): void
    {
        $text = strtoupper((string) $value);

        if (str_contains($text, 'RHD')) {
            $this->attributes['drive_side'] = 'RHD';
        } elseif (str_contains($text, 'LHD')) {
            $this->attributes['drive_side'] = 'LHD';
        } else {
            $this->attributes['drive_side'] = $value === '' ? null : $value;
        }
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
