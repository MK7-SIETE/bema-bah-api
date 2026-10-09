<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'car_id',
        'user_id',   // dealer who owns this message (set from car owner on save)
        'is_read',
        'type',      // general | car_enquiry | import_request
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function car()
    {
        return $this->belongsTo(Car::class)->withDefault();
    }

    // The dealer this message belongs to
    public function dealer()
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault();
    }
}
