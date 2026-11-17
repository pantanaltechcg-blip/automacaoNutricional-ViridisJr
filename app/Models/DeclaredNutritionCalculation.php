<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeclaredNutritionCalculation extends Model
{
    public const UPDATED_AT = null;

    public const string RULES_VERSION = 'declared-values-rounding-v1';

    protected $guarded = [];

    protected $attributes = ['rules_version' => self::RULES_VERSION];

    public function servingDailyValueCalculation(): BelongsTo
    {
        return $this->belongsTo(ServingDailyValueCalculation::class, 'serving_daily_value_calculation_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(DeclaredNutritionComponent::class, 'declared_nutrition_calculation_id');
    }

    public function energyComponents(): HasMany
    {
        return $this->hasMany(DeclaredNutritionEnergyComponent::class, 'declared_nutrition_calculation_id');
    }

    public function percentages(): HasMany
    {
        return $this->hasMany(DeclaredNutritionPercentage::class, 'declared_nutrition_calculation_id');
    }

    public function causes(): HasMany
    {
        return $this->hasMany(DeclaredNutritionCause::class, 'declared_nutrition_calculation_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'recalculation_requires_complete_energy_inputs' => 'boolean',
            'source_portion_energy_is_complete' => 'boolean',
            'reference_100_energy_nsq_applied' => 'boolean',
            'reference_100_energy_is_complete' => 'boolean',
            'portion_energy_nsq_applied' => 'boolean',
            'portion_energy_is_complete' => 'boolean',
            'is_label_ready' => 'boolean',
        ];
    }
}
