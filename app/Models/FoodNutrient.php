<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['food_id', 'nutrient_id', 'amount_value', 'status'])]
class FoodNutrient extends Model
{
    public const string STATUS_EXACT = 'EXACT';

    public const string STATUS_TRACE = 'TRACE';

    public const string STATUS_NA = 'NA';

    public const string STATUS_ABSENT = 'ABSENT';

    public const string STATUS_REEVALUATION = 'REEVALUATION';

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    public function nutrient(): BelongsTo
    {
        return $this->belongsTo(Nutrient::class);
    }
}
