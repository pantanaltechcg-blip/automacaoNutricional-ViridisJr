<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['recipe_id', 'yield_value', 'yield_unit', 'rules_version'])]
class NutritionCalculation extends Model
{
    public const UPDATED_AT = null;

    public const string RULES_VERSION = 'nutrition-calculation-v1';

    protected $attributes = [
        'rules_version' => self::RULES_VERSION,
    ];

    public function ingredients(): HasMany
    {
        return $this->hasMany(NutritionCalculationIngredient::class);
    }

    public function nutrients(): HasMany
    {
        return $this->hasMany(NutritionCalculationNutrient::class);
    }
}
