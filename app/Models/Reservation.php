<?php

namespace App\Models;

use App\Services\PricingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Reservation extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PARTIAL = 'partial';
    public const PAYMENT_PAID = 'paid';

    protected $fillable = [
        'code',
        'guest_id',
        'user_id',
        'room_id',
        'status',
        'source',
        'check_in_date',
        'check_out_date',
        'adults',
        'children',
        'room_rate',
        'nights',
        'subtotal',
        'discount',
        'tax_rate',
        'tax_amount',
        'extra_person_fee',
        'total_amount',
        'paid_amount',
        'payment_status',
        'special_requests',
        'notes',
        'check_in_at',
        'check_out_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'adults' => 'integer',
            'children' => 'integer',
            'nights' => 'integer',
            'room_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'extra_person_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation) {
            $reservation->code = static::generateCode();
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = 'RSV-' . strtoupper(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function charges()
    {
        return $this->hasMany(ServiceCharge::class)->where('status', 'billed');
    }

    public function getChargesTotalAttribute(): float
    {
        return round((float) $this->charges()->sum('amount'), 2);
    }

    public function getGrandTotalAttribute(): float
    {
        return round((float) $this->total_amount + $this->charges_total, 2);
    }

    public function getBalanceAttribute(): float
    {
        return round($this->grand_total - (float) $this->paid_amount, 2);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_CONFIRMED, self::STATUS_CHECKED_IN]);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_CHECKED_IN]);
    }

    public function recalculate(?RoomType $roomType = null): void
    {
        $nights = $this->check_in_date->diffInDays($this->check_out_date);
        if ($nights < 1) {
            $nights = 1;
        }

        $type = $roomType ?? $this->room?->roomType;

        $extraPersonRate = $type?->extra_person_rate ?? 0;
        $capacity = $type?->capacity ?? 1;

        $pricing = PricingService::compute(
            $nights,
            (float) $this->room_rate,
            (float) $extraPersonRate,
            (int) $capacity,
            (int) $this->adults,
            (float) $this->discount,
            (float) $this->tax_rate
        );

        $this->nights = $pricing['nights'];
        $this->subtotal = $pricing['subtotal'];
        $this->extra_person_fee = $pricing['extra_person_fee'];
        $this->tax_amount = $pricing['tax_amount'];
        $this->total_amount = $pricing['total'];

        $this->refreshPaymentStatus();
    }

    public function refreshPaymentStatus(): void
    {
        $total = $this->grand_total;
        $paid = (float) $this->paid_amount;

        if ($total <= 0 || $paid >= $total - 0.009) {
            $this->payment_status = self::PAYMENT_PAID;
        } elseif ($paid > 0) {
            $this->payment_status = self::PAYMENT_PARTIAL;
        } else {
            $this->payment_status = self::PAYMENT_UNPAID;
        }
    }
}