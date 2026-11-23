<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddedSugarsEvaluationItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'evaluation_id',
        'nutrition_calculation_ingredient_id',
        'formulation_ingredient_id',
        'position',
        'food_id',
        'food_source',
        'ingredient_quantity',
        'ingredient_unit',
        'ingredient_type',
        'classification',
        'amount_basis',
        'amount_value',
        'contribution_numerator',
        'contribution_denominator',
        'is_excluded_normative',
        'is_complete',
        'evidence_count',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_excluded_normative' => 'boolean',
            'is_complete' => 'boolean',
            'evidence_count' => 'integer',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(AddedSugarsEvaluation::class, 'evaluation_id');
    }

    public function nutritionCalculationIngredient(): BelongsTo
    {
        return $this->belongsTo(NutritionCalculationIngredient::class);
    }

    public function formulationIngredient(): BelongsTo
    {
        return $this->belongsTo(AddedSugarsFormulationIngredient::class, 'formulation_ingredient_id');
    }
}
