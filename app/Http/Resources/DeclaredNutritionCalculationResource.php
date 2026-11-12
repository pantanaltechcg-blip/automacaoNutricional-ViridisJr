<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DeclaredNutritionCalculation;
use App\Models\DeclaredNutritionCause;
use App\Models\DeclaredNutritionComponent;
use App\Models\DeclaredNutritionEnergyComponent;
use App\Models\DeclaredNutritionPercentage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin DeclaredNutritionCalculation */
class DeclaredNutritionCalculationResource extends JsonResource
{
    /** @var array<int, string> */
    private const QUALITATIVE_STATE_ORDER = ['TRACE', 'NA', 'ABSENT', 'REEVALUATION'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $causesByScope = $this->causes->groupBy('scope');

        return [
            'id' => $this->id,
            'serving_daily_value_calculation_id' => $this->serving_daily_value_calculation_id,
            'nutrition_calculation_id' => $this->source_nutrition_calculation_id,
            'recipe_id' => $this->source_recipe_id,
            'source_003_rules_version' => $this->source_003_rules_version,
            'source_004_rules_version' => $this->source_004_rules_version,
            'rules_version' => $this->rules_version,
            'is_label_ready' => (bool) $this->is_label_ready,
            'label_readiness_causes' => $this->formatCauses($causesByScope->get('LABEL_READINESS', collect())),
            'yield' => [
                'value' => (string) $this->source_yield_value,
                'unit' => $this->source_yield_unit,
            ],
            'portion' => [
                'value' => (string) $this->portion_value,
                'unit' => $this->portion_unit,
                'household_measure_mode' => $this->household_measure_mode,
                'household_measure' => $this->household_measure,
                'reference_technical_status' => $this->portion_reference_technical_status,
            ],
            'regulatory_profile' => [
                'code' => $this->regulatory_profile_code,
                'version' => $this->regulatory_profile_version,
                'technical_status' => $this->regulatory_profile_status,
                'normative_source' => $this->regulatory_profile_normative_source,
                'normative_version' => $this->regulatory_profile_normative_version,
            ],
            'declaration_profile' => [
                'code' => $this->declaration_profile_code,
                'version' => $this->declaration_profile_version,
                'title' => $this->declaration_profile_title,
                'technical_status' => $this->declaration_profile_status,
                'normative_source' => $this->declaration_profile_normative_source,
                'normative_version' => $this->declaration_profile_normative_version,
                'consulted_on' => $this->dateString($this->declaration_profile_consulted_on),
                'evidence' => $this->formatEvidence($this->resource, 'declaration_profile_'),
            ],
            'recalculation_policy' => $this->recalculation_policy_code === null ? null : [
                'policy_code' => $this->recalculation_policy_code,
                'normative_order_mode' => $this->recalculation_normative_order_mode,
                'technical_status' => $this->recalculation_policy_status,
                'requires_complete_energy_inputs' => $this->recalculation_requires_complete_energy_inputs,
                'source_reference' => $this->recalculation_policy_source_reference,
                'evidence' => $this->formatEvidence($this->resource, 'recalculation_policy_'),
            ],
            'components' => $this->components
                ->sortBy('map_code')
                ->values()
                ->map(fn (DeclaredNutritionComponent $component): array => $this->formatComponent(
                    $component,
                    $causesByScope->get('COMPONENT_REFERENCE_100', collect())->where('scope_key', $component->map_code),
                    $causesByScope->get('COMPONENT_PORTION', collect())->where('scope_key', $component->map_code),
                ))
                ->all(),
            'energy' => [
                'source_portion_derived_exact' => [
                    'value' => $this->ratio($this->source_portion_energy_numerator, $this->source_portion_energy_denominator),
                    'unit' => $this->source_portion_energy_unit,
                    'complete' => $this->source_portion_energy_is_complete,
                    'availability' => $this->source_portion_energy_availability,
                ],
                'reference_100' => [
                    'recalculated_exact' => $this->ratio($this->reference_100_exact_energy_numerator, $this->reference_100_exact_energy_denominator),
                    'nsq_applied' => (bool) $this->reference_100_energy_nsq_applied,
                    'declared' => $this->declaredValue(
                        $this->reference_100_declared_energy_string,
                        $this->reference_100_declared_energy_numerator,
                        $this->reference_100_declared_energy_denominator,
                    ),
                    'unit' => $this->reference_100_energy_unit,
                    'complete' => (bool) $this->reference_100_energy_is_complete,
                    'availability' => $this->reference_100_energy_availability,
                    'causes' => $this->formatCauses($causesByScope->get('ENERGY_REFERENCE_100', collect())),
                    'components' => $this->formatEnergyComponents(
                        $this->energyComponents->where('basis', 'REFERENCE_100'),
                    ),
                ],
                'portion' => [
                    'recalculated_exact' => $this->ratio($this->portion_exact_energy_numerator, $this->portion_exact_energy_denominator),
                    'nsq_applied' => (bool) $this->portion_energy_nsq_applied,
                    'declared' => $this->declaredValue(
                        $this->portion_declared_energy_string,
                        $this->portion_declared_energy_numerator,
                        $this->portion_declared_energy_denominator,
                    ),
                    'unit' => $this->portion_energy_unit,
                    'complete' => (bool) $this->portion_energy_is_complete,
                    'availability' => $this->portion_energy_availability,
                    'causes' => $this->formatCauses($causesByScope->get('ENERGY_PORTION', collect())),
                    'components' => $this->formatEnergyComponents(
                        $this->energyComponents->where('basis', 'PORTION'),
                    ),
                ],
            ],
            'daily_values' => $this->percentages
                ->sortBy('vdr_code')
                ->values()
                ->map(fn (DeclaredNutritionPercentage $pct): array => $this->formatPercentage(
                    $pct,
                    $causesByScope->get('DAILY_VALUE', collect())->where('scope_key', $pct->vdr_code),
                ))
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, DeclaredNutritionEnergyComponent>  $components
     * @return array<int, array<string, mixed>>
     */
    private function formatEnergyComponents(Collection $components): array
    {
        return $components
            ->sortBy('ene_code')
            ->values()
            ->map(fn (DeclaredNutritionEnergyComponent $component): array => [
                'ene_code' => $component->ene_code,
                'component' => $component->component_name,
                'map_code' => $component->map_code,
                'applicability_status' => $component->applicability_status,
                'participation' => $component->participation,
                'factor' => $this->ratio($component->factor_numerator, $component->factor_denominator),
                'factor_input_unit' => $component->factor_input_unit,
                'factor_output_unit' => $component->factor_output_unit,
                'input_declared' => $this->declaredValue(
                    $component->input_declared_string,
                    $component->input_declared_numerator,
                    $component->input_declared_denominator,
                ),
                'contribution' => $this->ratio($component->contribution_numerator, $component->contribution_denominator),
                'technical_status' => $component->ene_technical_status,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, DeclaredNutritionCause>  $causes
     * @return array<string, mixed>
     */
    private function formatPercentage(DeclaredNutritionPercentage $pct, Collection $causes): array
    {
        return [
            'vdr_code' => $pct->vdr_code,
            'component' => $pct->component_name,
            'map_code' => $pct->map_code,
            'vdr' => $this->ratio($pct->vdr_numerator, $pct->vdr_denominator),
            'vdr_unit' => $pct->vdr_unit,
            'technical_status' => $pct->vdr_technical_status,
            'source_derived_exact' => [
                'value' => $this->ratio(
                    $pct->source_derived_percentage_numerator,
                    $pct->source_derived_percentage_denominator,
                ),
                'availability' => $pct->source_derived_availability,
            ],
            'numerator_identity' => $pct->numerator_identity,
            'input_declared' => $this->declaredValue(
                $pct->input_declared_string,
                $pct->input_declared_numerator,
                $pct->input_declared_denominator,
            ),
            'unrounded_percentage' => $this->ratio(
                $pct->unrounded_percentage_numerator,
                $pct->unrounded_percentage_denominator,
            ),
            'rounding_rule' => $pct->rounding_rule_code === null ? null : [
                'rule_code' => $pct->rounding_rule_code,
                'technical_status' => $pct->rounding_rule_status,
                'tie_breaker' => $pct->rounding_tie_breaker,
                'step' => $this->ratio($pct->rounding_step_numerator, $pct->rounding_step_denominator),
                'decimal_places' => $pct->rounding_decimal_places,
            ],
            'declared_percentage' => $this->declaredValue(
                $pct->declared_percentage_string,
                $pct->declared_percentage_numerator,
                $pct->declared_percentage_denominator,
            ),
            'complete' => (bool) $pct->is_complete,
            'availability' => $pct->availability,
            'causes' => $this->formatCauses($causes),
        ];
    }

    /**
     * @param  Collection<int, DeclaredNutritionCause>  $ref100Causes
     * @param  Collection<int, DeclaredNutritionCause>  $portionCauses
     * @return array<string, mixed>
     */
    private function formatComponent(
        DeclaredNutritionComponent $component,
        Collection $ref100Causes,
        Collection $portionCauses,
    ): array {
        $presentStates = $component->states->pluck('state')->all();
        $orderedStates = array_values(array_intersect(self::QUALITATIVE_STATE_ORDER, $presentStates));

        return [
            'map_code' => $component->map_code,
            'component' => $component->component_name,
            'map_technical_status' => $component->map_technical_status,
            'nutrient' => $component->nutrient_id === null ? null : [
                'id' => $component->nutrient_id,
                'name' => $component->nutrient_name,
                'unit' => $component->source_unit,
            ],
            'regulatory_unit' => $component->regulatory_unit,
            'source_complete' => (bool) $component->source_is_complete,
            'qualitative_states' => $orderedStates,
            'has_missing_relation' => (bool) $component->source_has_missing_relation,
            'reference_100' => $this->formatComponentBasis($component, 'reference_100_', $ref100Causes),
            'portion' => $this->formatComponentBasis($component, 'portion_', $portionCauses),
        ];
    }

    /**
     * @param  Collection<int, DeclaredNutritionCause>  $causes
     * @return array<string, mixed>
     */
    private function formatComponentBasis(
        DeclaredNutritionComponent $component,
        string $prefix,
        Collection $causes,
    ): array {
        return [
            'source_exact' => $this->ratio($component->{"{$prefix}source_numerator"}, $component->{"{$prefix}source_denominator"}),
            'regulatory_exact' => $this->ratio($component->{"{$prefix}regulatory_numerator"}, $component->{"{$prefix}regulatory_denominator"}),
            'is_exact_zero' => (bool) $component->{"{$prefix}is_exact_zero"},
            'nsq_applied' => (bool) $component->{"{$prefix}nsq_applied"},
            'nsq_rule' => $component->{"{$prefix}nsq_rule_code"} === null ? null : [
                'rule_code' => $component->{"{$prefix}nsq_rule_code"},
                'technical_status' => $component->{"{$prefix}nsq_rule_status"},
            ],
            'rounding_rule' => $component->{"{$prefix}rounding_rule_code"} === null ? null : [
                'rule_code' => $component->{"{$prefix}rounding_rule_code"},
                'technical_status' => $component->{"{$prefix}rounding_rule_status"},
                'tie_breaker' => $component->{"{$prefix}rounding_tie_breaker"},
                'step' => $this->ratio(
                    $component->{"{$prefix}rounding_step_numerator"},
                    $component->{"{$prefix}rounding_step_denominator"},
                ),
                'decimal_places' => $component->{"{$prefix}rounding_decimal_places"},
            ],
            'declared' => $this->declaredValue(
                $component->{"{$prefix}declared_string"},
                $component->{"{$prefix}declared_numerator"},
                $component->{"{$prefix}declared_denominator"},
            ),
            'complete' => (bool) $component->{"{$prefix}is_complete"},
            'availability' => $component->{"{$prefix}availability"},
            'causes' => $this->formatCauses($causes),
        ];
    }

    /**
     * @param  Collection<int, DeclaredNutritionCause>  $causes
     * @return array<int, array<string, mixed>>
     */
    private function formatCauses(Collection $causes): array
    {
        return $causes
            ->sortBy(static fn (DeclaredNutritionCause $cause): string => "{$cause->scope}|{$cause->scope_key}|{$cause->code}")
            ->values()
            ->map(static fn (DeclaredNutritionCause $cause): array => [
                'scope' => $cause->scope,
                'scope_key' => $cause->scope_key,
                'category' => $cause->category,
                'code' => $cause->code,
                'detail' => $cause->detail,
                'source_reference' => $cause->source_reference,
            ])
            ->all();
    }

    /**
     * @return array{display: string, ratio: array{numerator: string, denominator: string}}|null
     */
    private function declaredValue(?string $display, mixed $numerator, mixed $denominator): ?array
    {
        $ratio = $this->ratio($numerator, $denominator);
        if ($display === null || $ratio === null) {
            return null;
        }

        return [
            'display' => $display,
            'ratio' => $ratio,
        ];
    }

    /**
     * @return array{numerator: string, denominator: string}|null
     */
    private function ratio(mixed $numerator, mixed $denominator): ?array
    {
        if ($numerator === null || $denominator === null) {
            return null;
        }

        return [
            'numerator' => (string) $numerator,
            'denominator' => (string) $denominator,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatEvidence(object $record, string $stem): ?array
    {
        $reference = $record->{"{$stem}evidence_reference"} ?? null;
        if ($reference === null) {
            return null;
        }

        return [
            'reference' => $reference,
            'source' => $record->{"{$stem}evidence_source"},
            'version' => $record->{"{$stem}evidence_version"},
            'scope' => $record->{"{$stem}evidence_scope"},
            'kind' => $record->{"{$stem}evidence_kind"},
            'validated_by' => $record->{"{$stem}validated_by"},
            'validated_on' => $this->dateString($record->{"{$stem}validated_on"}),
        ];
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return method_exists($value, 'toDateString') ? $value->toDateString() : (string) $value;
    }
}
