<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegulatoryEnergyComponent extends Model
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

    public function applicabilities(): HasMany
    {
        return $this->hasMany(RegulatoryEnergyApplicability::class, 'energy_component_id');
    }
}
