<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventVenue extends Model
{
    protected $fillable = [
        'name',
        'capacity_standing',
        'capacity_seated',
        'base_rate',
        'description',
        'facilities',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity_standing' => 'integer',
            'capacity_seated' => 'integer',
            'base_rate' => 'decimal:2',
            'facilities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function bookings()
    {
        return $this->hasMany(EventBooking::class, 'venue_id');
    }
}