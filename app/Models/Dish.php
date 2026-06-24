<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'name',
    'description',
    'prep_minutes',
    'category_id',
    'station_id',
    'price',
    'per_round_limit',
    'is_available',
    'is_custom',
    'sort_order',
])]
class Dish extends Model
{
    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'is_custom' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'dish_tag');
    }
}
