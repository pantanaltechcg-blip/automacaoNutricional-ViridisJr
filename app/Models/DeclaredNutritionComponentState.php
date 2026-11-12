<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeclaredNutritionComponentState extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function component(): BelongsTo
    {
        return $this->belongsTo(DeclaredNutritionComponent::class, 'declared_nutrition_component_id');
    }
}
