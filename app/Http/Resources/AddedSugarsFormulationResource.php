<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AddedSugarsFormulation;
use App\Models\AddedSugarsFormulationVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddedSugarsFormulationResource extends JsonResource
{
    /** @var AddedSugarsFormulation */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $versions = $this->resource->versions;
        $currentVersion = $versions->firstWhere('version_number', $this->resource->current_version_number);

        return [
            'id' => (int) $this->resource->getKey(),
            'recipe_id' => (int) $this->resource->recipe_id,
            'current_version_number' => (int) $this->resource->current_version_number,
            'current_version' => $currentVersion instanceof AddedSugarsFormulationVersion
                ? $this->serializeVersion($currentVersion)
                : null,
            'versions' => $versions->map(
                fn (AddedSugarsFormulationVersion $version): array => $this->serializeVersion($version),
            )->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeVersion(AddedSugarsFormulationVersion $version): array
    {
        $latestValidation = $version->validations->last();

        return [
            'id' => (int) $version->getKey(),
            'version_number' => (int) $version->version_number,
            'normative_source' => $version->normative_source,
            'normative_version' => $version->normative_version,
            'registered_by' => $version->registered_by,
            'registered_on' => $version->registered_on?->format('Y-m-d'),
            'change_reason' => $version->change_reason,
            'validation_status' => $latestValidation?->decision_status ?? 'PENDING',
            'validations' => $version->validations->map(
                fn ($validation): array => (new AddedSugarsFormulationValidationResource($validation))->toArray(request()),
            )->values()->all(),
            'ingredients' => $version->ingredients->map(fn ($ingredient): array => [
                'id' => (int) $ingredient->getKey(),
                'position' => (int) $ingredient->position,
                'food_id' => (int) $ingredient->food_id,
                'ingredient_type' => $ingredient->ingredient_type,
                'classification' => $ingredient->classification,
                'amount_basis' => $ingredient->amount_basis,
                'amount_value' => $ingredient->amount_value !== null ? (string) $ingredient->amount_value : null,
                'amount_unit' => $ingredient->amount_unit,
                'exclusion_justification' => $ingredient->exclusion_justification,
                'conflict_notes' => $ingredient->conflict_notes,
                'evidences' => $ingredient->evidences->map(fn ($evidence): array => [
                    'id' => (int) $evidence->getKey(),
                    'source_type' => $evidence->source_type,
                    'source_name' => $evidence->source_name,
                    'document_reference' => $evidence->document_reference,
                    'reported_amount_basis' => $evidence->reported_amount_basis,
                    'reported_amount_value' => $evidence->reported_amount_value !== null ? (string) $evidence->reported_amount_value : null,
                    'reported_amount_unit' => $evidence->reported_amount_unit,
                    'only_total_sugars_reported' => (bool) $evidence->only_total_sugars_reported,
                    'recorded_by' => $evidence->recorded_by,
                    'recorded_on' => $evidence->recorded_on?->format('Y-m-d'),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
