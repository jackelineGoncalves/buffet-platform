<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'label'])]
class Tag extends Model
{
    public function dishes(): BelongsToMany
    {
        return $this->belongsToMany(Dish::class, 'dish_tag');
    }
}
