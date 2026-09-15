<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodOrderItem extends Model
{
    protected $fillable = [
        'food_order_id',
        'food_item_id',
        'item_name',
        'quantity',
        'unit_price',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function foodOrder()
    {
        return $this->belongsTo(FoodOrder::class);
    }
}