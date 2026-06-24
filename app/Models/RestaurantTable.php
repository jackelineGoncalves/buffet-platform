<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'seats'])]
class RestaurantTable extends Model
{
    public function diningSessions(): HasMany
    {
        return $this->hasMany(DiningSession::class);
    }
}
