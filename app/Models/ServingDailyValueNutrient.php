<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServingDailyValueNutrient extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(ServingDailyValueCalculation::class, 'serving_daily_value_calculation_id');
    }

    public function sourceNutrient(): BelongsTo
    {
        return $this->belongsTo(NutritionCalculationNutrient::class, 'nutrition_calculation_nutrient_id');
    }

    public function states(): HasMany
    {
        return $this->hasMany(ServingDailyValueNutrientState::class);
    }

    protected function casts(): array
    {
        return ['source_is_complete' => 'boolean', 'source_has_missing_relation' => 'boolean', 'is_complete' => 'boolean', 'has_missing_relation' => 'boolean'];
    }
}
