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

    // Message sent to the rental admin by email and WhatsApp.
    public function messageText(): string
    {
        $lines = [
            'New rental request: ' . ($this->car_label ?: 'Car #' . $this->car_id),
            '',
            'Customer: ' . $this->customer_name,
            'Phone: ' . $this->customer_phone,
            'Email: ' . ($this->customer_email ?: '-'),
            'Pickup: ' . $this->pickup_date->format('d M Y'),
            'Return: ' . $this->return_date->format('d M Y'),
            'Pickup location: ' . ($this->pickup_location ?: '-'),
            'Notes: ' . ($this->notes ?: '-'),
            '',
            'Please contact the customer to confirm availability.',
        ];

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
