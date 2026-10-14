<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['nutrition_calculation_nutrient_id', 'state'])]
class NutritionCalculationNutrientState extends Model
{
    public $timestamps = false;

    public function nutrient(): BelongsTo
    {
        return $this->belongsTo(
            NutritionCalculationNutrient::class,
            'nutrition_calculation_nutrient_id',
        );
    }
}
