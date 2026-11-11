<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServingDailyValueRegulatoryResult extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(ServingDailyValueCalculation::class, 'serving_daily_value_calculation_id');
    }

    public function nutrient(): BelongsTo
    {
        return $this->belongsTo(ServingDailyValueNutrient::class, 'serving_daily_value_nutrient_id');
    }

    protected function casts(): array
    {
        return ['is_complete' => 'boolean'];
    }
}
