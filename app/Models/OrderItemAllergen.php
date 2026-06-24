<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_item_id', 'allergen_id', 'label'])]
class OrderItemAllergen extends Model
{
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function allergen(): BelongsTo
    {
        return $this->belongsTo(Allergen::class);
    }
}
