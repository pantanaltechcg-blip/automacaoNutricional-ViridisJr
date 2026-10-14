<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nutrition_calculation_id',
    'recipe_ingredient_id',
    'position',
    'quantity',
    'unit',
    'food_id',
    'food_source',
    'food_source_code',
])]
class NutritionCalculationIngredient extends Model
{
    public $timestamps = false;

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(NutritionCalculation::class, 'nutrition_calculation_id');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(NutritionCalculationContribution::class);
    }
}
