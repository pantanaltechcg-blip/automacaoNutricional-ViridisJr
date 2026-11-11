<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegulatoryDailyValue extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(RegulatoryProfile::class, 'profile_id');
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(RegulatoryNutrientMapping::class, 'mapping_id');
    }
}
