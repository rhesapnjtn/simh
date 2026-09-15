<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AycePackage extends Model
{
    protected $fillable = [
        'name',
        'price_per_pax',
        'min_pax',
        'max_pax',
        'duration_minutes',
        'description',
        'includes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_per_pax' => 'decimal:2',
            'min_pax' => 'integer',
            'max_pax' => 'integer',
            'duration_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}