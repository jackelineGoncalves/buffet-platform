<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id',
    'dish_id',
    'name',
    'station_id',
    'unit_price',
    'qty',
    'status',
    'note',
])]
class OrderItem extends Model
{
    protected $attributes = [
        'status' => 'firing',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function orderItemAllergens(): HasMany
    {
        return $this->hasMany(OrderItemAllergen::class);
    }
}
