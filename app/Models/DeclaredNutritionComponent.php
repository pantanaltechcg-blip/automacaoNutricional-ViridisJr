<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeclaredNutritionComponent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(DeclaredNutritionCalculation::class, 'declared_nutrition_calculation_id');
    }

    public function states(): HasMany
    {
        return $this->hasMany(DeclaredNutritionComponentState::class, 'declared_nutrition_component_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source_is_complete' => 'boolean',
            'source_has_missing_relation' => 'boolean',
            'reference_100_is_exact_zero' => 'boolean',
            'reference_100_nsq_applied' => 'boolean',
            'reference_100_is_complete' => 'boolean',
            'portion_is_exact_zero' => 'boolean',
            'portion_nsq_applied' => 'boolean',
            'portion_is_complete' => 'boolean',
        ];
    }
}
