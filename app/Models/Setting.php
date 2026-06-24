<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['buffet_price', 'waste_fee', 'session_minutes', 'last_call_minutes', 'tax_rate'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return [
            'buffet_price' => 'decimal:2',
            'waste_fee' => 'decimal:2',
            'tax_rate' => 'decimal:4',
        ];
    }
}
