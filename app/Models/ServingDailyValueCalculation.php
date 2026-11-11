<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServingDailyValueCalculation extends Model
{
    public const UPDATED_AT = null;

    public const string RULES_VERSION = 'serving-daily-values-v1';

    protected $guarded = [];

    protected $attributes = ['rules_version' => self::RULES_VERSION];

    public function nutrients(): HasMany
    {
        return $this->hasMany(ServingDailyValueNutrient::class);
    }

    public function regulatoryResults(): HasMany
    {
        return $this->hasMany(ServingDailyValueRegulatoryResult::class);
    }

    public function energyComponents(): HasMany
    {
        return $this->hasMany(ServingDailyValueEnergyComponent::class);
    }

    public function percentages(): HasMany
    {
        return $this->hasMany(ServingDailyValuePercentage::class);
    }

    public function causes(): HasMany
    {
        return $this->hasMany(ServingDailyValueResultCause::class);
    }
}
