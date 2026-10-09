<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalRequest extends Model
{
    public const STATUSES = ['new', 'forwarded', 'confirmed', 'declined', 'completed'];

    protected $fillable = [
        'car_id',
        'rental_admin_id',
        'car_label',
        'customer_name',
        'customer_phone',
        'customer_email',
        'pickup_date',
        'return_date',
        'pickup_location',
        'notes',
        'status',
        'forwarded_at',
    ];

    protected $casts = [
        'pickup_date'  => 'date',
        'return_date'  => 'date',
        'forwarded_at' => 'datetime',
    ];

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function rentalAdmin()
    {
        return $this->belongsTo(User::class, 'rental_admin_id');
    }
}
