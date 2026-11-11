<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServingDailyValueResultCause extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(ServingDailyValueCalculation::class, 'serving_daily_value_calculation_id');
    }
}
