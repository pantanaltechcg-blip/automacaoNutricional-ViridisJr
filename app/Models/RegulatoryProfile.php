<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegulatoryProfile extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public function mappings(): HasMany
    {
        return $this->hasMany(RegulatoryNutrientMapping::class, 'profile_id');
    }

    public function energyComponents(): HasMany
    {
        return $this->hasMany(RegulatoryEnergyComponent::class, 'profile_id');
    }

    public function dailyValues(): HasMany
    {
        return $this->hasMany(RegulatoryDailyValue::class, 'profile_id');
    }
}
