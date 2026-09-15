<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Guest extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'id_type',
        'id_number',
        'address',
        'nationality',
        'is_vip',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_vip' => 'boolean',
        ];
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    protected static function booted(): void
    {
        static::saving(function (Guest $guest) {
            $guest->first_name = Str::title($guest->first_name ?? '');
            $guest->last_name = Str::title($guest->last_name ?? '');
        });
    }
}