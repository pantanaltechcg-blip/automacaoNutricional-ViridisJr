<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddedSugarsEvaluation extends Model
{
    public const string RULES_VERSION = 'added-sugars-formulation-v1';

    /** @var list<string> */
    protected $fillable = [
        'nutrition_calculation_id',
        'formulation_id',
        'formulation_version_id',
        'validation_id',
        'source_recipe_id',
        'source_rules_version',
        'source_yield_value',
        'source_yield_unit',
        'formulation_version_number',
        'normative_source',
        'normative_version',
        'validation_status',
        'validation_evidence_type',
        'validation_evidence_reference',
        'validation_evidence_source',
        'validation_evidence_version',
        'validation_evidence_scope',
        'validated_by',
        'validated_on',
        'total_numerator',
        'total_denominator',
        'total_unit',
        'reference_basis',
        'reference_unit',
        'reference_numerator',
        'reference_denominator',
        'is_complete',
        'is_regulatory_eligible',
        'is_confirmed_zero',
        'rules_version',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'reference_basis' => '100',
        'rules_version' => self::RULES_VERSION,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'formulation_version_number' => 'integer',
            'validated_on' => 'date:Y-m-d',
            'is_complete' => 'boolean',
            'is_regulatory_eligible' => 'boolean',
            'is_confirmed_zero' => 'boolean',
        ];
    }

    public function nutritionCalculation(): BelongsTo
    {
        return $this->belongsTo(NutritionCalculation::class);
    }

    public function formulation(): BelongsTo
    {
        return $this->belongsTo(AddedSugarsFormulation::class, 'formulation_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AddedSugarsFormulationVersion::class, 'formulation_version_id');
    }

    public function validation(): BelongsTo
    {
        return $this->belongsTo(AddedSugarsFormulationValidation::class, 'validation_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AddedSugarsEvaluationItem::class, 'evaluation_id')
            ->orderBy('position');
    }

    public function causes(): HasMany
    {
        return $this->hasMany(AddedSugarsEvaluationCause::class, 'evaluation_id')
            ->orderBy('id');
    }
}
