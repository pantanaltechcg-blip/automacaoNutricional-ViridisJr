<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServingDailyValueNutrientState extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function nutrient(): BelongsTo
    {
        return $this->belongsTo(ServingDailyValueNutrient::class, 'serving_daily_value_nutrient_id');
    }
}
