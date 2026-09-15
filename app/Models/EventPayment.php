<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPayment extends Model
{
    protected $fillable = [
        'event_booking_id',
        'user_id',
        'amount',
        'method',
        'reference',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(EventBooking::class, 'event_booking_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}