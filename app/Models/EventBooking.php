<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventBooking extends Model
{
    public const TYPE_WEDDING = 'wedding';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_SEMINAR = 'seminar';
    public const TYPE_BIRTHDAY = 'birthday';
    public const TYPE_CORPORATE = 'corporate';
    public const TYPE_AYCE = 'ayce';
    public const TYPE_PRIVATE_PARTY = 'private_party';
    public const TYPE_OTHER = 'other';

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PARTIAL = 'partial';
    public const PAYMENT_PAID = 'paid';

    public const EVENT_TYPES = [
        self::TYPE_WEDDING => 'Pernikahan / Wedding',
        self::TYPE_MEETING => 'Rapat / Meeting',
        self::TYPE_SEMINAR => 'Seminar',
        self::TYPE_BIRTHDAY => 'Ulang Tahun',
        self::TYPE_CORPORATE => 'Corporate Event',
        self::TYPE_AYCE => 'All You Can Eat',
        self::TYPE_PRIVATE_PARTY => 'Pesta Privat',
        self::TYPE_OTHER => 'Lainnya',
    ];

    protected $fillable = [
        'code',
        'event_type',
        'title',
        'venue_id',
        'ayce_package_id',
        'contact_name',
        'contact_phone',
        'contact_email',
        'start_date',
        'end_date',
        'pax',
        'days',
        'venue_rate',
        'price_per_pax',
        'addons',
        'subtotal',
        'discount',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'payment_status',
        'status',
        'assigned_to',
        'setup_at',
        'notes',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'pax' => 'integer',
            'days' => 'integer',
            'venue_rate' => 'decimal:2',
            'price_per_pax' => 'decimal:2',
            'addons' => 'array',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'setup_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EventBooking $booking) {
            $booking->code = static::generateCode();
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = 'EVT-' . strtoupper(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function venue()
    {
        return $this->belongsTo(EventVenue::class, 'venue_id');
    }

    public function aycePackage()
    {
        return $this->belongsTo(AycePackage::class, 'ayce_package_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function payments()
    {
        return $this->hasMany(EventPayment::class);
    }

    public function getBalanceAttribute(): float
    {
        return round((float) $this->total_amount - (float) $this->paid_amount, 2);
    }

    public function getAddonsTotalAttribute(): float
    {
        return round((float) collect($this->addons ?? [])->sum('amount'), 2);
    }

    public function recalculate(?EventVenue $venue = null): void
    {
        $venue = $venue ?? $this->venue;

        $days = $this->start_date->diffInDays($this->end_date) + 1;
        $this->days = max(1, $days);

        $venueRate = (float) ($this->venue_rate > 0 ? $this->venue_rate : ($venue?->base_rate ?? 0));
        $this->venue_rate = $venueRate;

        $isAyce = $this->event_type === self::TYPE_AYCE;
        if (! $isAyce) {
            $this->price_per_pax = 0;
        }

        $venueCost = round($venueRate * $this->days, 2);
        $ayceCost = round((float) $this->price_per_pax * (int) $this->pax, 2);
        $addonsCost = $this->addons_total;

        $subtotal = round($venueCost + $ayceCost + $addonsCost, 2);
        $this->subtotal = $subtotal;

        $discount = round((float) $this->discount, 2);
        $taxAmount = round(($subtotal - $discount) * (float) $this->tax_rate / 100, 2);
        $this->tax_amount = $taxAmount;
        $this->total_amount = round($subtotal - $discount + $taxAmount, 2);

        $this->refreshPaymentStatus();
    }

    public function refreshPaymentStatus(): void
    {
        $total = (float) $this->total_amount;
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