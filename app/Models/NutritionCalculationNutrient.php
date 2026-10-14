<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nutrition_calculation_id',
    'nutrient_id',
    'nutrient_name',
    'nutrient_unit',
    'total_numerator',
    'total_denominator',
    'is_complete',
    'has_missing_relation',
    'reference_basis',
    'reference_unit',
    'reference_numerator',
    'reference_denominator',
])]
class NutritionCalculationNutrient extends Model
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

    public function states(): HasMany
    {
        return $this->hasMany(NutritionCalculationNutrientState::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_complete' => 'boolean',
            'has_missing_relation' => 'boolean',
        ];
    }
}
