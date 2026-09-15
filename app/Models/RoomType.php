<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RoomType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'base_rate',
        'extra_person_rate',
        'capacity',
        'amenities',
        'image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_rate' => 'decimal:2',
            'extra_person_rate' => 'decimal:2',
            'capacity' => 'integer',
            'amenities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (RoomType $roomType) {
            if (empty($roomType->slug)) {
                $roomType->slug = Str::slug($roomType->name);
            }
        });
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}