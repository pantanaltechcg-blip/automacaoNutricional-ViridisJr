<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegulatoryEnergyApplicability extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function nutritionCalculation(): BelongsTo
    {
        return $this->belongsTo(NutritionCalculation::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(RegulatoryProfile::class, 'profile_id');
    }

    public function energyComponent(): BelongsTo
    {
        return $this->belongsTo(RegulatoryEnergyComponent::class);
    }
}
