<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['dining_session_id', 'number', 'round', 'status', 'placed_at'])]
class Order extends Model
{
    protected $attributes = [
        'status' => 'new',
    ];

    protected $casts = [
        'placed_at' => 'datetime',
    ];

    public function diningSession(): BelongsTo
    {
        return $this->belongsTo(DiningSession::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function syncStatusFromItems(): self
    {
        $statuses = $this->orderItems()->pluck('status');

        $status = match (true) {
            $statuses->isEmpty() => 'new',
            $statuses->contains('firing') => 'prep',
            $statuses->every(fn ($itemStatus) => $itemStatus === 'served') => 'served',
            default => 'ready',
        };

        $this->update(['status' => $status]);

        return $this;
    }
}
