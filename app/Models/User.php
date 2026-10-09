<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',       // dealer | super | rental_admin
        'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
    ];

    public function isDealer(): bool  { return $this->role === 'dealer'; }
    public function isSuper(): bool   { return $this->role === 'super'; }
    public function isRentalAdmin(): bool { return $this->role === 'rental_admin'; }
    public function isActive(): bool  { return $this->is_active; }

    // Digits-only number ready for wa.me links. Local Zambian 0XXXXXXXXX becomes 260XXXXXXXXX.
    public function whatsappNumber(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '260' . substr($digits, 1);
        }
        return $digits;
    }

    public function cars()
    {
        return $this->hasMany(Car::class);
    }
}
