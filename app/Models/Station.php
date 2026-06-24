<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'label', 'short_label', 'color'])]
class Station extends Model
{
    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }
}
