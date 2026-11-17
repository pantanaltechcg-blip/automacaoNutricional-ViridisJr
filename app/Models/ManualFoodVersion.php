<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'manual_food_registration_id',
    'version_number',
    'name',
    'category',
    'source',
    'source_code',
    'reference_unit',
    'evidence_reference',
    'evidence_source',
    'registered_by',
    'registered_on',
    'change_reason',
])]
class ManualFoodVersion extends Model
{
    public const UPDATED_AT = null;

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ManualFoodRegistration::class, 'manual_food_registration_id');
    }

    public function nutrients(): HasMany
    {
        return $this->hasMany(ManualFoodVersionNutrient::class)->orderBy('nutrient_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
        ];
    }
}
