<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'manual_food_version_id',
    'nutrient_id',
    'amount_value',
    'status',
])]
class ManualFoodVersionNutrient extends Model
{
    public $timestamps = false;

    public const string STATUS_EXACT = FoodNutrient::STATUS_EXACT;

    public const string STATUS_TRACE = FoodNutrient::STATUS_TRACE;

    public const string STATUS_NA = FoodNutrient::STATUS_NA;

    public const string STATUS_ABSENT = FoodNutrient::STATUS_ABSENT;

    public const string STATUS_REEVALUATION = FoodNutrient::STATUS_REEVALUATION;

    public function version(): BelongsTo
    {
        return $this->belongsTo(ManualFoodVersion::class, 'manual_food_version_id');
    }

    public function nutrient(): BelongsTo
    {
        return $this->belongsTo(Nutrient::class);
    }
}
