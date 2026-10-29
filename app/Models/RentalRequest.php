<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalRequest extends Model
{
    public const STATUSES       = ['new', 'forwarded', 'confirmed', 'declined', 'completed'];
    public const DELIVERY_TYPES = ['pickup', 'delivery'];
    public const TRIP_TYPES     = ['in_city', 'out_of_city'];

    protected $fillable = [
        'car_id',
        'rental_admin_id',
        'car_label',
        'customer_name',
        'customer_phone',
        'customer_email',
        'pickup_date',
        'days',
        'return_date',
        'delivery_type',
        'delivery_address',
        'with_driver',
        'trip_type',
        'destination',
        'pickup_location',   // old requests only (before the trip details existed)
        'notes',
        'status',
        'forwarded_at',
    ];

    protected $casts = [
        'pickup_date'  => 'date',
        'return_date'  => 'date',
        'days'         => 'integer',
        'with_driver'  => 'boolean',
        'forwarded_at' => 'datetime',
    ];

    // Message sent to the rental admin by email and WhatsApp.
    // Requests made before the trip details existed (days is empty) keep the old layout.
    public function messageText(): string
    {
        $lines = [
            'New rental request: ' . ($this->car_label ?: 'Car #' . $this->car_id),
            '',
            'Customer: ' . $this->customer_name,
            'Phone: ' . $this->customer_phone,
            'Email: ' . ($this->customer_email ?: '-'),
        ];

        if ($this->days) {
            $lines[] = 'Start date: ' . $this->pickup_date->format('d M Y');
            $lines[] = 'Days: ' . $this->days . ' (back on ' . $this->return_date->format('d M Y') . ')';
            $lines[] = $this->delivery_type === 'delivery'
                ? 'Delivery to: ' . ($this->delivery_address ?: '-')
                : "Pick-up: collect at the car's location" . ($this->car?->location ? ' (' . $this->car->location . ')' : '');
            $lines[] = 'Driver: ' . ($this->with_driver ? 'with a driver' : 'self-drive');
            $lines[] = $this->trip_type === 'out_of_city'
                ? 'Trip: out of the city' . ($this->destination ? ' (to ' . $this->destination . ')' : '')
                : 'Trip: within the city';
        } else {
            $lines[] = 'Pickup: ' . $this->pickup_date->format('d M Y');
            $lines[] = 'Return: ' . $this->return_date->format('d M Y');
            $lines[] = 'Pickup location: ' . ($this->pickup_location ?: '-');
        }

        $lines[] = 'Notes: ' . ($this->notes ?: '-');
        $lines[] = '';
        $lines[] = 'Please contact the customer to confirm availability and price.';

        return implode("\n", $lines);
    }

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function rentalAdmin()
    {
        return $this->belongsTo(User::class, 'rental_admin_id');
    }
}
