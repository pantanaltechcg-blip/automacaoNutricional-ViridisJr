<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'nutrition_calculation_ingredient_id',
    'nutrition_calculation_nutrient_id',
    'food_nutrient_id',
    'input_status',
    'input_amount_value',
    'is_missing_relation',
    'value_numerator',
    'value_denominator',
])]
class NutritionCalculationContribution extends Model
{
    public $timestamps = false;

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(
            NutritionCalculationIngredient::class,
            'nutrition_calculation_ingredient_id',
        );
    }

    public function nutrient(): BelongsTo
    {
        return $this->belongsTo(
            NutritionCalculationNutrient::class,
            'nutrition_calculation_nutrient_id',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_missing_relation' => 'boolean',
        ];
    }
}
