<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'dining_session_id',
    'guests',
    'buffet_total',
    'extras_total',
    'waste_total',
    'tax',
    'total',
    'paid_at',
])]
class Payment extends Model
{
    protected $casts = [
        'buffet_total' => 'decimal:2',
        'extras_total' => 'decimal:2',
        'waste_total' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function diningSession(): BelongsTo
    {
        return $this->belongsTo(DiningSession::class);
    }
}
