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
        'is_read',   // OLD shared flag - no longer used by the code; dropped in a later migration
        'type',      // general | car_enquiry | import_request
    ];

    // Each side (the dealer and the super admin) keeps its OWN read flag and its OWN delete stamp.
    // They are not mass-assignable on purpose: the controller sets them explicitly.
    protected $casts = [
        'is_read'           => 'boolean',
        'dealer_is_read'    => 'boolean',
        'super_is_read'     => 'boolean',
        'dealer_deleted_at' => 'datetime',
        'super_deleted_at'  => 'datetime',
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
