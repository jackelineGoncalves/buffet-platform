<?php

namespace App\Models;

use App\Exceptions\TableOccupiedException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\QueryException;

#[Fillable([
    'restaurant_table_id',
    'guests',
    'status',
    'waste_count',
    'opened_at',
    'closed_at',
])]
class DiningSession extends Model
{
    protected $attributes = [
        'status' => 'seated',
        'waste_count' => 0,
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            $occupied = static::query()
                ->where('restaurant_table_id', $session->restaurant_table_id)
                ->where('status', '!=', 'closed')
                ->exists();

            if ($occupied) {
                throw new TableOccupiedException('This table already has an active dining session.');
            }
        });
    }

    /**
     * The pre-check in `creating` above is a fast-path for the common case, but it
     * cannot prevent a race between two concurrent inserts. The real guarantee comes
     * from the partial unique index added in the
     * `add_one_active_session_per_table_constraint` migration; this catches its
     * violation and translates it into the same exception callers already expect.
     */
    protected function performInsert(Builder $query): bool
    {
        try {
            return parent::performInsert($query);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'dining_sessions_one_active_per_table')) {
                throw new TableOccupiedException('This table already has an active dining session.', previous: $e);
            }

            throw $e;
        }
    }
}
