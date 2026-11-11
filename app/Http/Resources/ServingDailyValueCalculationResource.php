<?php

namespace App\Http\Resources;

use App\Models\ServingDailyValueCalculation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServingDailyValueCalculationResource extends JsonResource
{
    /** @var ServingDailyValueCalculation */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->getKey(),
            'nutrition_calculation_id' => (int) $this->resource->nutrition_calculation_id,
            'recipe_id' => (int) $this->resource->source_recipe_id,
            'source_rules_version' => $this->resource->source_rules_version,
            'rules_version' => $this->resource->rules_version,
            'yield' => ['value' => (string) $this->resource->source_yield_value, 'unit' => $this->resource->source_yield_unit],
            'portion' => [
                'value' => (string) $this->resource->portion_value,
                'unit' => $this->resource->portion_unit,
                'household_measure_mode' => $this->resource->household_measure_mode,
                'household_measure' => $this->resource->household_measure,
                'reference' => [
                    'code' => $this->resource->portion_reference_code,
                    'version' => $this->resource->portion_reference_version,
                    'source' => $this->resource->portion_reference_source,
                    'technical_status' => $this->resource->portion_reference_technical_status,
                    'justification' => $this->resource->portion_justification,
                    'evidence' => $this->evidence($this->resource, 'portion_evidence_', 'portion_validated_'),
                ],
            ],
            'regulatory_profile' => [
                'code' => $this->resource->regulatory_profile_code,
                'version' => $this->resource->regulatory_profile_version,
                'title' => $this->resource->regulatory_profile_title,
                'technical_status' => $this->resource->regulatory_profile_status,
                'normative_source' => $this->resource->regulatory_profile_normative_source,
                'normative_version' => $this->resource->regulatory_profile_normative_version,
                'consulted_on' => (string) $this->resource->regulatory_profile_consulted_on,
                'evidence' => $this->evidence($this->resource, 'regulatory_profile_evidence_', 'regulatory_profile_validated_'),
            ],
            'nutrients' => $this->resource->nutrients->map(fn ($nutrient): array => [
                'nutrient' => ['id' => (int) $nutrient->nutrient_id, 'name' => $nutrient->nutrient_name, 'unit' => $nutrient->nutrient_unit],
                'source_total' => $this->nutrientValue($nutrient, 'source_'),
                'portion' => $this->nutrientValue($nutrient, 'portion_'),
            ])->values()->all(),
            'regulatory_results' => $this->resource->regulatoryResults->map(fn ($result): array => [
                'map_code' => $result->map_code,
                'component' => $result->component_name,
                'technical_status' => $result->technical_status,
                'source_reference' => $result->map_source_reference,
                'evidence' => $this->evidence($result, 'map_evidence_', 'map_validated_'),
                'conversion' => [
                    'status' => $result->conversion_status,
                    'source_unit' => $result->conversion_source_unit,
                    'target_unit' => $result->conversion_target_unit,
                    'factor' => $this->ratio($result->conversion_numerator, $result->conversion_denominator),
                    'evidence' => $this->evidence($result, 'conversion_evidence_', 'conversion_validated_'),
                ],
                'value' => $this->ratio($result->value_numerator, $result->value_denominator),
                'unit' => $result->regulatory_unit,
                'complete' => (bool) $result->is_complete,
                'availability' => $result->availability,
                'causes' => $this->causes('REGULATORY_VALUE', $result->map_code),
            ])->values()->all(),
            'energy' => [
                'value' => $this->ratio($this->resource->energy_numerator, $this->resource->energy_denominator),
                'unit' => $this->resource->energy_unit,
                'complete' => (bool) $this->resource->energy_is_complete,
                'availability' => $this->resource->energy_availability,
                'causes' => $this->causes('ENERGY', 'ENERGY'),
                'components' => $this->resource->energyComponents->map(fn ($component): array => [
                    'ene_code' => $component->ene_code,
                    'component' => $component->component_name,
                    'map_code' => $component->map_code,
                    'applicability' => $component->applicability_status,
                    'participation' => $component->participation,
                    'factor' => $this->ratio($component->factor_numerator, $component->factor_denominator),
                    'input' => $this->ratio($component->input_numerator, $component->input_denominator),
                    'contribution' => $this->ratio($component->contribution_numerator, $component->contribution_denominator),
                    'factor_input_unit' => $component->factor_input_unit,
                    'factor_output_unit' => $component->factor_output_unit,
                    'source_reference' => $component->source_reference,
                    'source_version' => $component->source_version,
                    'technical_status' => $component->technical_status,
                    'evidence' => $this->evidence($component, 'ene_evidence_', 'ene_validated_'),
                    'applicability_evidence' => $this->evidence($component, 'applicability_evidence_', 'applicability_validated_'),
                    'non_applicability_justification' => $component->non_applicability_justification,
                ])->values()->all(),
            ],
            'daily_values' => $this->resource->percentages->map(fn ($percentage): array => [
                'vdr_code' => $percentage->vdr_code,
                'component' => $percentage->component_name,
                'map_code' => $percentage->map_code,
                'vdr' => $this->ratio($percentage->vdr_numerator, $percentage->vdr_denominator),
                'vdr_unit' => $percentage->vdr_unit,
                'source_reference' => $percentage->vdr_source_reference,
                'source_version' => $percentage->vdr_source_version,
                'technical_status' => $percentage->technical_status,
                'evidence' => $this->evidence($percentage, 'vdr_evidence_', 'vdr_validated_'),
                'numerator_identity' => $percentage->numerator_identity,
                'numerator' => $this->ratio($percentage->numerator_numerator, $percentage->numerator_denominator),
                'value' => $this->ratio($percentage->percentage_numerator, $percentage->percentage_denominator),
                'complete' => (bool) $percentage->is_complete,
                'availability' => $percentage->availability,
                'causes' => $this->causes('DAILY_VALUE', $percentage->vdr_code),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function nutrientValue(object $nutrient, string $prefix): array
    {
        $statePrefix = $prefix === 'source_' ? 'source_' : '';

        return [
            'value' => $this->ratio($nutrient->{$prefix.'numerator'}, $nutrient->{$prefix.'denominator'}),
            'complete' => (bool) $nutrient->{$statePrefix.'is_complete'},
            'qualitative_states' => $nutrient->states->pluck('state')->sort()->values()->all(),
            'has_missing_relation' => (bool) $nutrient->{$statePrefix.'has_missing_relation'},
        ];
    }

    /** @return array{numerator: string, denominator: string}|null */
    private function ratio(mixed $numerator, mixed $denominator): ?array
    {
        return $numerator === null || $denominator === null ? null : ['numerator' => (string) $numerator, 'denominator' => (string) $denominator];
    }

    /** @return array<string, mixed>|null */
    private function evidence(object $model, string $prefix, string $validationPrefix): ?array
    {
        $reference = $model->{$prefix.'reference'};

        if ($reference === null) {
            return null;
        }

        return [
            'reference' => $reference,
            'source' => $model->{$prefix.'source'},
            'version' => $model->{$prefix.'version'},
            'scope' => $model->{$prefix.'scope'},
            'kind' => $model->{$prefix.'kind'},
            'validated_by' => $model->{$validationPrefix.'by'},
            'validated_on' => (string) $model->{$validationPrefix.'on'},
        ];
    }

    /** @return list<array<string, mixed>> */
    private function causes(string $scope, string $key): array
    {
        return $this->resource->causes->where('scope', $scope)->where('scope_key', $key)
            ->map(fn ($cause): array => ['code' => $cause->code, 'category' => $cause->category, 'detail' => $cause->detail, 'reference' => $cause->reference])
            ->values()->all();
    }
}
