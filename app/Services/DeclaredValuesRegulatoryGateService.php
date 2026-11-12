<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RegulatoryDeclarationProfile;
use App\Models\RegulatoryDeclarationRequirement;
use App\Models\RegulatoryNonSignificantRule;
use App\Models\RegulatoryRecalculationPolicy;
use App\Models\RegulatoryRoundingRule;
use App\Models\RegulatoryRoundingRuleRange;
use App\Models\ServingDailyValueCalculation;
use App\Models\ServingDailyValueEnergyComponent;
use App\Models\ServingDailyValuePercentage;
use App\Models\ServingDailyValueRegulatoryResult;
use Brick\Math\BigRational;
use Illuminate\Validation\ValidationException;

class DeclaredValuesRegulatoryGateService
{
    public function __construct(
        private ServingRegulatoryGateService $servingGates,
        private NutritionCalculationArithmetic $arithmetic,
    ) {}

    public function assertProfileMatchesSource(
        RegulatoryDeclarationProfile $declarationProfile,
        ServingDailyValueCalculation $source,
    ): void {
        $matches = $declarationProfile->regulatory_profile_code === $source->regulatory_profile_code
            && $declarationProfile->regulatory_profile_version === $source->regulatory_profile_version
            && $declarationProfile->normative_source === $source->regulatory_profile_normative_source
            && $declarationProfile->normative_version === $source->regulatory_profile_normative_version
            && ($declarationProfile->scope_recipe_id === null
                || (int) $declarationProfile->scope_recipe_id === (int) $source->source_recipe_id)
            && ($declarationProfile->scope_nutrition_calculation_id === null
                || (int) $declarationProfile->scope_nutrition_calculation_id === (int) $source->nutrition_calculation_id);

        if (! $matches) {
            throw ValidationException::withMessages([
                'regulatory_declaration_profile_id' => 'O pacote de declaração selecionado diverge do perfil regulatório, versão normativa ou escopo da execução de origem.',
            ]);
        }
    }

    public function profileAuthorized(RegulatoryDeclarationProfile $profile): bool
    {
        return $profile->technical_status === 'AUTHORIZED'
            && $this->nonEmpty($profile->regulatory_profile_code)
            && $this->nonEmpty($profile->regulatory_profile_version)
            && $this->nonEmpty($profile->normative_source)
            && $this->nonEmpty($profile->normative_version)
            && $profile->evidence_version === $profile->version
            && $this->servingGates->hasEvidence($profile, 'evidence_', $this->profileScope($profile));
    }

    public function nonSignificantRuleAuthorized(
        RegulatoryDeclarationProfile $profile,
        ?RegulatoryNonSignificantRule $rule,
    ): bool {
        if (! $rule instanceof RegulatoryNonSignificantRule) {
            return false;
        }

        if ((int) $rule->declaration_profile_id !== (int) $profile->getKey()) {
            return false;
        }

        return $this->profileAuthorized($profile)
            && $rule->technical_status === 'AUTHORIZED'
            && in_array($rule->comparison_operator, ['LTE', 'LT'], true)
            && $rule->threshold_numerator !== null
            && $rule->threshold_denominator !== null
            && $this->nonEmpty($rule->threshold_unit)
            && $this->nonEmpty($rule->declared_string)
            && $rule->declared_numerator !== null
            && $rule->declared_denominator !== null
            && $this->nonEmpty($rule->source_reference)
            && $rule->evidence_version === $profile->version
            && $this->servingGates->hasEvidence(
                $rule,
                'evidence_',
                "{$this->profileScope($profile)}/nsq:{$rule->rule_code}",
            );
    }

    public function nonSignificantRuleNotApplicableValid(
        RegulatoryDeclarationProfile $profile,
        RegulatoryNonSignificantRule $rule,
    ): bool {
        return (int) $rule->declaration_profile_id === (int) $profile->getKey()
            && $this->profileAuthorized($profile)
            && $rule->technical_status === 'NOT_APPLICABLE'
            && $this->nonEmpty($rule->justification)
            && $rule->evidence_version === $profile->version
            && $this->servingGates->hasEvidence(
                $rule,
                'evidence_',
                "{$this->profileScope($profile)}/nsq:{$rule->rule_code}",
            );
    }

    public function roundingRuleAuthorized(
        RegulatoryDeclarationProfile $profile,
        ?RegulatoryRoundingRule $rule,
    ): bool {
        if (! $rule instanceof RegulatoryRoundingRule) {
            return false;
        }

        if ((int) $rule->declaration_profile_id !== (int) $profile->getKey()) {
            return false;
        }

        $rule->loadMissing('ranges');
        $ranges = $rule->ranges;

        if (
            ! $this->profileAuthorized($profile)
            || $rule->technical_status !== 'AUTHORIZED'
            || $rule->tie_breaker !== 'HALF_UP'
            || ! $this->nonEmpty($rule->unit)
            || ! $this->nonEmpty($rule->source_reference)
            || $rule->evidence_version !== $profile->version
            || ! $this->servingGates->hasEvidence(
                $rule,
                'evidence_',
                "{$this->profileScope($profile)}/rounding:{$rule->rule_code}",
            )
            || $ranges->isEmpty()
        ) {
            return false;
        }

        foreach ($ranges as $range) {
            if (! $this->rangeIsValid($range)) {
                return false;
            }
        }

        $count = $ranges->count();
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($this->rangesOverlap($ranges[$i], $ranges[$j])) {
                    return false;
                }
            }
        }

        return true;
    }

    public function resolveRange(RegulatoryRoundingRule $rule, BigRational $value): ?RegulatoryRoundingRuleRange
    {
        $rule->loadMissing('ranges');
        $matches = [];

        foreach ($rule->ranges as $range) {
            if ($this->rangeMatches($range, $value)) {
                $matches[] = $range;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    public function recalculationPolicyAuthorized(
        RegulatoryDeclarationProfile $profile,
        ?RegulatoryRecalculationPolicy $policy,
    ): bool {
        if (! $policy instanceof RegulatoryRecalculationPolicy) {
            return false;
        }

        if ((int) $policy->declaration_profile_id !== (int) $profile->getKey()) {
            return false;
        }

        return $this->profileAuthorized($profile)
            && $policy->technical_status === 'AUTHORIZED'
            && $policy->normative_order_mode === 'DECLARE_COMPONENTS_THEN_ENERGY_AND_VD'
            && $policy->energy_reference_100_input_source === 'DECLARED_REFERENCE_100'
            && $policy->energy_portion_input_source === 'DECLARED_PORTION'
            && $policy->daily_value_input_source === 'DECLARED_PORTION_AND_DECLARED_ENERGY'
            && (bool) $policy->requires_complete_energy_inputs === true
            && $this->nonEmpty($policy->source_reference)
            && $policy->evidence_version === $profile->version
            && $this->servingGates->hasEvidence(
                $policy,
                'evidence_',
                "{$this->profileScope($profile)}/policy:{$policy->policy_code}",
            );
    }

    public function energyMatrixAuthorized(ServingDailyValueCalculation $source): bool
    {
        if ($source->regulatory_profile_status !== 'AUTHORIZED' || $source->energyComponents->isEmpty()) {
            return false;
        }

        foreach ($source->energyComponents as $component) {
            if (! $this->sourceEnergyComponentAuthorized($source, $component)) {
                return false;
            }
        }

        return true;
    }

    public function sourceEnergyComponentAuthorized(
        ServingDailyValueCalculation $source,
        ServingDailyValueEnergyComponent $component,
    ): bool {
        if ($source->regulatory_profile_status !== 'AUTHORIZED' || $component->technical_status !== 'AUTHORIZED') {
            return false;
        }

        if (! in_array($component->applicability_status, ['APPLICABLE', 'NOT_APPLICABLE'], true)) {
            return false;
        }

        if ($component->applicability_status === 'NOT_APPLICABLE') {
            if ($component->participation !== 'NON_APPLICABLE' || ! $this->nonEmpty($component->non_applicability_justification)) {
                return false;
            }
        } elseif (
            $component->participation !== 'INCLUDED'
            || ! $this->nonEmpty($component->map_code)
            || $component->factor_numerator === null
            || $component->factor_denominator === null
            || ! $this->nonEmpty($component->factor_input_unit)
            || $component->factor_output_unit !== 'kcal'
        ) {
            return false;
        }

        if ($component->overlap_group === null) {
            if ($component->overlap_rule_status !== 'NOT_REQUIRED') {
                return false;
            }
        } elseif ($component->overlap_rule_status !== 'AUTHORIZED' || ! $this->nonEmpty($component->overlap_rule_code)) {
            return false;
        }

        $mapCode = $component->map_code ?? 'missing';
        $eneScope = "profile:{$source->regulatory_profile_code}@{$source->regulatory_profile_version}/ene:{$component->ene_code}/map:{$mapCode}";
        $appScope = "nutrition-calculation:{$source->nutrition_calculation_id}/{$eneScope}";

        return $this->nonEmpty($component->source_reference)
            && $this->servingGates->hasEvidence($component, 'ene_evidence_', $eneScope)
            && $this->servingGates->hasEvidence($component, 'applicability_evidence_', $appScope);
    }

    public function sourceDailyValueAuthorized(
        ServingDailyValueCalculation $source,
        ServingDailyValuePercentage $percentage,
    ): bool {
        if (
            $source->regulatory_profile_status !== 'AUTHORIZED'
            || $percentage->technical_status !== 'AUTHORIZED'
            || $percentage->vdr_numerator === null
            || $percentage->vdr_denominator === null
            || ! $this->nonEmpty($percentage->vdr_unit)
            || ! $this->nonEmpty($percentage->vdr_source_reference)
        ) {
            return false;
        }

        $expectedScope = $percentage->map_code !== null
            ? "profile:{$source->regulatory_profile_code}@{$source->regulatory_profile_version}/vdr:{$percentage->vdr_code}/map:{$percentage->map_code}"
            : "profile:{$source->regulatory_profile_code}@{$source->regulatory_profile_version}/vdr:{$percentage->vdr_code}/energy";

        return $this->servingGates->hasEvidence($percentage, 'vdr_evidence_', $expectedScope);
    }

    public function sourceNoNormativeVdrAuthorized(
        ServingDailyValueCalculation $source,
        ServingDailyValuePercentage $percentage,
    ): bool {
        if (
            $source->regulatory_profile_status !== 'AUTHORIZED'
            || $percentage->technical_status !== 'NO_NORMATIVE_VDR'
            || ! $this->nonEmpty($percentage->vdr_source_reference)
        ) {
            return false;
        }

        return $this->servingGates->hasEvidence(
            $percentage,
            'vdr_evidence_',
            "profile:{$source->regulatory_profile_code}@{$source->regulatory_profile_version}/vdr:{$percentage->vdr_code}/no-normative-vdr",
        );
    }

    public function requirementAuthorized(
        RegulatoryDeclarationProfile $profile,
        RegulatoryDeclarationRequirement $requirement,
    ): bool {
        if ((int) $requirement->declaration_profile_id !== (int) $profile->getKey()) {
            return false;
        }

        if (
            ! $this->profileAuthorized($profile)
            || $requirement->technical_status !== 'AUTHORIZED'
            || ! in_array($requirement->applicability_status, ['APPLICABLE', 'NOT_APPLICABLE'], true)
            || ! $this->nonEmpty($requirement->source_reference)
            || $requirement->evidence_version !== $profile->version
        ) {
            return false;
        }

        if ($requirement->applicability_status === 'NOT_APPLICABLE' && ! $this->nonEmpty($requirement->justification)) {
            return false;
        }

        return $this->servingGates->hasEvidence(
            $requirement,
            'evidence_',
            "{$this->profileScope($profile)}/requirement:{$requirement->requirement_code}",
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @param  array<string, mixed>  $energyRootFields
     * @param  array<int, array<string, mixed>>  $percentages
     * @return array{ready: bool, causes: array<int, array<string, mixed>>}
     */
    public function evaluateLabelReadiness(
        ServingDailyValueCalculation $source,
        RegulatoryDeclarationProfile $profile,
        array $components,
        array $energyRootFields,
        array $percentages,
    ): array {
        $causes = [];
        $componentsByMap = collect($components)->keyBy('map_code');
        $percentagesByVdr = collect($percentages)->keyBy('vdr_code');

        if (! $this->profileAuthorized($profile)) {
            $causes[] = $this->readinessCause('LABEL_READINESS', 'REGULATORY_BLOCK', 'DECLARATION_PROFILE_BLOCKED');
        }

        if (! $this->recalculationPolicyAuthorized($profile, $profile->recalculationPolicy)) {
            $causes[] = $this->readinessCause(
                'LABEL_READINESS',
                'POLICY_BLOCK',
                'RECALCULATION_POLICY_BLOCKED',
                $profile->recalculationPolicy?->source_reference,
            );
        }

        if (! $this->sourcePortionAuthorized($source)) {
            $causes[] = $this->readinessCause('LABEL_READINESS', 'REGULATORY_BLOCK', 'PORTION_NOT_AUTHORIZED');
        }

        if ($profile->requirements->isEmpty()) {
            $causes[] = $this->readinessCause('LABEL_READINESS', 'MISSING_SOURCE', 'NO_DECLARATION_REQUIREMENTS');

            return ['ready' => false, 'causes' => $causes];
        }

        foreach ($profile->requirements->sortBy('requirement_code') as $requirement) {
            if (! $this->requirementAuthorized($profile, $requirement)) {
                $category = $requirement->applicability_status === 'UNKNOWN'
                    ? 'UNKNOWN_APPLICABILITY'
                    : 'REGULATORY_BLOCK';
                $causes[] = $this->readinessCause(
                    $requirement->requirement_code,
                    $category,
                    'REQUIREMENT_NOT_AUTHORIZED',
                    $requirement->source_reference,
                );

                continue;
            }

            if ($requirement->applicability_status === 'NOT_APPLICABLE') {
                continue;
            }

            if ($requirement->target_kind === 'COMPONENT') {
                $comp = $requirement->map_code !== null ? $componentsByMap->get($requirement->map_code) : null;
                $ready = $comp !== null
                    && ($comp['reference_100_availability'] ?? null) === 'AVAILABLE'
                    && ($comp['portion_availability'] ?? null) === 'AVAILABLE';

                if (! $ready) {
                    $causes[] = $this->readinessCause(
                        $requirement->requirement_code,
                        'REGULATORY_BLOCK',
                        'REQUIRED_COMPONENT_UNAVAILABLE',
                        $requirement->source_reference,
                        "{$requirement->map_code} indisponível ou incompleto em 100 g/100 ml ou porção.",
                    );
                }
            } elseif ($requirement->target_kind === 'ENERGY') {
                $ready = ($energyRootFields['reference_100_energy_availability'] ?? null) === 'AVAILABLE'
                    && ($energyRootFields['portion_energy_availability'] ?? null) === 'AVAILABLE';

                if (! $ready) {
                    $causes[] = $this->readinessCause(
                        $requirement->requirement_code,
                        'REGULATORY_BLOCK',
                        'REQUIRED_ENERGY_UNAVAILABLE',
                        $requirement->source_reference,
                        'Energia regulatória final indisponível em 100 g/100 ml ou porção.',
                    );
                }
            } elseif ($requirement->target_kind === 'DAILY_VALUE') {
                $pct = $requirement->vdr_code !== null ? $percentagesByVdr->get($requirement->vdr_code) : null;
                $ready = $pct !== null
                    && in_array($pct['availability'] ?? null, ['AVAILABLE', 'NO_NORMATIVE_VDR'], true);

                if (! $ready) {
                    $causes[] = $this->readinessCause(
                        $requirement->requirement_code,
                        'REGULATORY_BLOCK',
                        'REQUIRED_VD_UNAVAILABLE',
                        $requirement->source_reference,
                        "{$requirement->vdr_code} indisponível para declaração.",
                    );
                }
            }
        }

        return ['ready' => $causes === [], 'causes' => $causes];
    }

    /** @return array<string, mixed> */
    private function readinessCause(
        string $scopeKey,
        string $category,
        string $code,
        ?string $sourceReference = null,
        ?string $detail = null,
    ): array {
        return [
            'scope' => 'LABEL_READINESS',
            'scope_key' => $scopeKey,
            'category' => $category,
            'code' => $code,
            'detail' => $detail,
            'source_reference' => $sourceReference,
        ];
    }

    public function sourcePortionAuthorized(ServingDailyValueCalculation $source): bool
    {
        if ($source->portion_reference_technical_status !== 'AUTHORIZED') {
            return false;
        }

        if ($source->household_measure_mode === 'PROVIDED' && ! $this->nonEmpty($source->household_measure)) {
            return false;
        }

        if ($source->household_measure_mode === 'JUSTIFIED_OMISSION' && ! $this->nonEmpty($source->portion_justification)) {
            return false;
        }

        return $this->servingGates->hasEvidence(
            $source,
            'portion_evidence_',
            "nutrition-calculation:{$source->nutrition_calculation_id}/portion:{$source->portion_reference_code}@{$source->portion_reference_version}",
        );
    }

    public function sourceMappingAuthorized(
        ServingDailyValueCalculation $source,
        ServingDailyValueRegulatoryResult $result,
    ): bool {
        if ($source->regulatory_profile_status !== 'AUTHORIZED' || $result->technical_status !== 'AUTHORIZED') {
            return false;
        }

        if ($result->nutrient_id === null || ! $this->nonEmpty($result->map_source_reference)) {
            return false;
        }

        return $this->servingGates->hasEvidence(
            $result,
            'map_evidence_',
            "profile:{$source->regulatory_profile_code}@{$source->regulatory_profile_version}/map:{$result->map_code}/nutrient:{$result->nutrient_id}",
        );
    }

    public function sourceConversionAuthorized(
        ServingDailyValueCalculation $source,
        ServingDailyValueRegulatoryResult $result,
    ): bool {
        if ($result->conversion_status === 'NOT_REQUIRED') {
            return $result->source_unit !== null && $result->source_unit === $result->regulatory_unit;
        }

        if (
            $result->conversion_status !== 'AUTHORIZED'
            || $result->conversion_numerator === null
            || $result->conversion_denominator === null
            || ! $this->nonEmpty($result->conversion_source_unit)
            || ! $this->nonEmpty($result->conversion_target_unit)
        ) {
            return false;
        }

        return $result->conversion_source_unit === $result->source_unit
            && $result->conversion_target_unit === $result->regulatory_unit
            && $this->servingGates->hasEvidence(
                $result,
                'conversion_evidence_',
                "profile:{$source->regulatory_profile_code}@{$source->regulatory_profile_version}/map:{$result->map_code}/nutrient:{$result->nutrient_id}/conversion:{$result->conversion_source_unit}->{$result->conversion_target_unit}",
            );
    }

    public function profileScope(RegulatoryDeclarationProfile $profile): string
    {
        return "declaration-profile:{$profile->code}@{$profile->version}";
    }

    private function rangeIsValid(RegulatoryRoundingRuleRange $range): bool
    {
        $step = $this->arithmetic->fromRatio((string) $range->step_numerator, (string) $range->step_denominator);
        if ($step->getSign() <= 0 || (int) $range->decimal_places < 0) {
            return false;
        }

        if ($range->min_numerator !== null && $range->max_numerator !== null) {
            $min = $this->arithmetic->fromRatio((string) $range->min_numerator, (string) $range->min_denominator);
            $max = $this->arithmetic->fromRatio((string) $range->max_numerator, (string) $range->max_denominator);
            $cmp = $this->arithmetic->compare($min, $max);

            if ($cmp > 0 || ($cmp === 0 && ! ($range->min_inclusive && $range->max_inclusive))) {
                return false;
            }
        }

        return true;
    }

    private function rangeMatches(RegulatoryRoundingRuleRange $range, BigRational $value): bool
    {
        if ($range->min_numerator !== null) {
            $min = $this->arithmetic->fromRatio((string) $range->min_numerator, (string) $range->min_denominator);
            $cmpMin = $this->arithmetic->compare($value, $min);
            if ($cmpMin < 0 || ($cmpMin === 0 && ! $range->min_inclusive)) {
                return false;
            }
        }

        if ($range->max_numerator !== null) {
            $max = $this->arithmetic->fromRatio((string) $range->max_numerator, (string) $range->max_denominator);
            $cmpMax = $this->arithmetic->compare($value, $max);
            if ($cmpMax > 0 || ($cmpMax === 0 && ! $range->max_inclusive)) {
                return false;
            }
        }

        return true;
    }

    private function rangesOverlap(RegulatoryRoundingRuleRange $left, RegulatoryRoundingRuleRange $right): bool
    {
        if ($this->endsStrictlyBefore($left, $right) || $this->endsStrictlyBefore($right, $left)) {
            return false;
        }

        return true;
    }

    private function endsStrictlyBefore(RegulatoryRoundingRuleRange $first, RegulatoryRoundingRuleRange $second): bool
    {
        if ($first->max_numerator === null || $second->min_numerator === null) {
            return false;
        }

        $firstMax = $this->arithmetic->fromRatio((string) $first->max_numerator, (string) $first->max_denominator);
        $secondMin = $this->arithmetic->fromRatio((string) $second->min_numerator, (string) $second->min_denominator);
        $cmp = $this->arithmetic->compare($firstMax, $secondMin);

        if ($cmp < 0) {
            return true;
        }

        return $cmp === 0 && ! ($first->max_inclusive && $second->min_inclusive);
    }

    private function nonEmpty(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
