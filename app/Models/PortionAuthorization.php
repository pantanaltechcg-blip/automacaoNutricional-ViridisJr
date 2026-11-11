<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortionAuthorization extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public function nutritionCalculation(): BelongsTo
    {
        return $this->belongsTo(NutritionCalculation::class);
    }
}
