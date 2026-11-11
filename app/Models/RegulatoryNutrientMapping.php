<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegulatoryNutrientMapping extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(RegulatoryProfile::class, 'profile_id');
    }

    public function nutrient(): BelongsTo
    {
        return $this->belongsTo(Nutrient::class);
    }

    public function energyComponents(): HasMany
    {
        return $this->hasMany(RegulatoryEnergyComponent::class, 'mapping_id');
    }

    public function dailyValues(): HasMany
    {
        return $this->hasMany(RegulatoryDailyValue::class, 'mapping_id');
    }
}
