<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['dining_session_id', 'type', 'requested_at', 'resolved_at'])]
class ServiceRequest extends Model
{
    protected $casts = [
        'requested_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function diningSession(): BelongsTo
    {
        return $this->belongsTo(DiningSession::class);
    }
}
