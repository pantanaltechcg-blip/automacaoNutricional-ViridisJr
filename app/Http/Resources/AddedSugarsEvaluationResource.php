<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AddedSugarsEvaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddedSugarsEvaluationResource extends JsonResource
{
    /** @var AddedSugarsEvaluation */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->getKey(),
            'nutrition_calculation_id' => (int) $this->resource->nutrition_calculation_id,
            'formulation_id' => (int) $this->resource->formulation_id,
            'formulation_version_id' => (int) $this->resource->formulation_version_id,
            'formulation_version_number' => (int) $this->resource->formulation_version_number,
            'source_recipe_id' => (int) $this->resource->source_recipe_id,
            'source_rules_version' => $this->resource->source_rules_version,
            'rules_version' => $this->resource->rules_version,
            'normative_source' => $this->resource->normative_source,
            'normative_version' => $this->resource->normative_version,
            'yield' => [
                'value' => (string) $this->resource->source_yield_value,
                'unit' => $this->resource->source_yield_unit,
            ],
            'validation' => [
                'status' => $this->resource->validation_status,
                'evidence_type' => $this->resource->validation_evidence_type,
                'evidence_reference' => $this->resource->validation_evidence_reference,
                'evidence_source' => $this->resource->validation_evidence_source,
                'evidence_version' => $this->resource->validation_evidence_version,
                'evidence_scope' => $this->resource->validation_evidence_scope,
                'validated_by' => $this->resource->validated_by,
                'validated_on' => $this->resource->validated_on?->format('Y-m-d'),
            ],
            'total' => [
                'value' => $this->ratio($this->resource->total_numerator, $this->resource->total_denominator),
                'unit' => $this->resource->total_unit,
            ],
            'reference' => [
                'basis' => $this->resource->reference_basis,
                'unit' => $this->resource->reference_unit,
                'value' => $this->ratio($this->resource->reference_numerator, $this->resource->reference_denominator),
            ],
            'is_complete' => (bool) $this->resource->is_complete,
            'is_regulatory_eligible' => (bool) $this->resource->is_regulatory_eligible,
            'is_confirmed_zero' => (bool) $this->resource->is_confirmed_zero,
            'items' => $this->resource->items->map(fn ($item): array => [
                'id' => (int) $item->getKey(),
                'nutrition_calculation_ingredient_id' => (int) $item->nutrition_calculation_ingredient_id,
                'formulation_ingredient_id' => $item->formulation_ingredient_id !== null ? (int) $item->formulation_ingredient_id : null,
                'position' => (int) $item->position,
                'food_id' => (int) $item->food_id,
                'food_source' => $item->food_source,
                'ingredient_quantity' => (string) $item->ingredient_quantity,
                'ingredient_unit' => $item->ingredient_unit,
                'ingredient_type' => $item->ingredient_type,
                'classification' => $item->classification,
                'amount_basis' => $item->amount_basis,
                'amount_value' => $item->amount_value !== null ? (string) $item->amount_value : null,
                'contribution' => $this->ratio($item->contribution_numerator, $item->contribution_denominator),
                'is_excluded_normative' => (bool) $item->is_excluded_normative,
                'is_complete' => (bool) $item->is_complete,
                'evidence_count' => (int) $item->evidence_count,
            ])->values()->all(),
            'causes' => $this->resource->causes->map(fn ($cause): array => [
                'id' => (int) $cause->getKey(),
                'evaluation_item_id' => $cause->evaluation_item_id !== null ? (int) $cause->evaluation_item_id : null,
                'cause_type' => $cause->cause_type,
                'cause_code' => $cause->cause_code,
                'detail' => $cause->detail,
            ])->values()->all(),
        ];
    }

    /** @return ?array{numerator: string, denominator: string} */
    private function ratio(?string $numerator, ?string $denominator): ?array
    {
        if ($numerator === null || $denominator === null) {
            return null;
        }

        return [
            'numerator' => $numerator,
            'denominator' => $denominator,
        ];
    }
}
