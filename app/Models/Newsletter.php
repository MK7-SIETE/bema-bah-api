<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Newsletter extends Model
{
    protected $fillable = ['subject', 'type', 'body', 'car_id', 'status', 'recipients_count', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function car()
    {
        return $this->belongsTo(Car::class);
    }
}
