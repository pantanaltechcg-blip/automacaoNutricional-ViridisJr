<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NutritionCalculation;
use App\Models\PortionAuthorization;
use App\Models\RegulatoryDailyValue;
use App\Models\RegulatoryEnergyApplicability;
use App\Models\RegulatoryEnergyComponent;
use App\Models\RegulatoryNutrientMapping;
use App\Models\RegulatoryProfile;
use Illuminate\Validation\ValidationException;

class ServingRegulatoryGateService
{
    /** @param array{value: string, unit: string, household_measure: ?string} $portion */
    public function assertPortionAuthorized(
        NutritionCalculation $calculation,
        PortionAuthorization $authorization,
        array $portion,
    ): void {
        $valid = (int) $authorization->nutrition_calculation_id === (int) $calculation->getKey()
            && $authorization->technical_status === 'AUTHORIZED'
            && $this->nonEmpty($authorization->reference_source)
            && $authorization->evidence_version === $authorization->reference_version
            && $this->hasEvidence(
                $authorization,
                'evidence_',
                "nutrition-calculation:{$calculation->getKey()}/portion:{$authorization->reference_code}@{$authorization->reference_version}",
            )
            && (string) $authorization->getRawOriginal('portion_value') === $portion['value']
            && $authorization->portion_unit === $portion['unit']
            && $calculation->yield_unit === $portion['unit'];

        $measureMatches = match ($authorization->household_measure_mode) {
            'PROVIDED' => $portion['household_measure'] !== null
                && $portion['household_measure'] === $authorization->household_measure,
            'WAIVED', 'NOT_APPLICABLE' => $portion['household_measure'] === null
                && $this->nonEmpty($authorization->justification),
            default => false,
        };

        if (! $valid || ! $measureMatches) {
            throw ValidationException::withMessages([
                'portion_authorization_id' => 'A autorização de porção não é tecnicamente válida para esta execução e entrada.',
            ]);
        }
    }

    public function profileAuthorized(RegulatoryProfile $profile): bool
    {
        return $profile->technical_status === 'AUTHORIZED'
            && $this->nonEmpty($profile->normative_source)
            && $this->nonEmpty($profile->normative_version)
            && $profile->evidence_version === $profile->version
            && $this->hasEvidence($profile, 'evidence_', $this->profileScope($profile));
    }

    public function mappingAuthorized(RegulatoryNutrientMapping $mapping): bool
    {
        $mapping->loadMissing(['profile', 'nutrient']);
        $profile = $mapping->profile;
        $nutrient = $mapping->nutrient;

        if (! $profile instanceof RegulatoryProfile
            || ! $this->profileAuthorized($profile)
            || $mapping->technical_status !== 'AUTHORIZED'
            || $mapping->nutrient_id === null
            || $nutrient === null
            || ! $this->nonEmpty($mapping->source_reference)
            || ! $this->nonEmpty($mapping->source_unit)
            || ! $this->nonEmpty($mapping->regulatory_unit)
            || $mapping->source_unit !== $nutrient->unit
            || $mapping->evidence_version !== $profile->version
            || ! $this->hasEvidence($mapping, 'evidence_', $this->mappingScope($mapping, $profile))) {
            return false;
        }

        $conversionScope = $this->conversionScope($mapping, $profile);

        return match ($mapping->conversion_status) {
            'NOT_REQUIRED' => $mapping->conversion_numerator === null
                && $mapping->conversion_denominator === null
                && $mapping->source_unit === $mapping->regulatory_unit
                && $mapping->conversion_evidence_version === $profile->version
                && $this->hasEvidence($mapping, 'conversion_evidence_', $conversionScope),
            'AUTHORIZED' => $mapping->conversion_numerator !== null
                && $mapping->conversion_denominator !== null
                && $mapping->conversion_source_unit === $mapping->source_unit
                && $mapping->conversion_target_unit === $mapping->regulatory_unit
                && $mapping->conversion_evidence_version === $profile->version
                && $this->hasEvidence($mapping, 'conversion_evidence_', $conversionScope),
            default => false,
        };
    }

    public function energyComponentAuthorized(RegulatoryEnergyComponent $component): bool
    {
        $component->loadMissing(['profile', 'mapping.profile', 'mapping.nutrient']);
        $profile = $component->profile;
        $mapping = $component->mapping;

        return $profile instanceof RegulatoryProfile
            && $mapping instanceof RegulatoryNutrientMapping
            && (int) $mapping->profile_id === (int) $profile->getKey()
            && $this->profileAuthorized($profile)
            && $this->mappingAuthorized($mapping)
            && $component->technical_status === 'AUTHORIZED'
            && $component->mapping_id !== null
            && $component->factor_numerator !== null
            && $component->factor_denominator !== null
            && $component->factor_input_unit === $mapping->regulatory_unit
            && $component->factor_output_unit === 'kcal'
            && $this->nonEmpty($component->source_reference)
            && $component->evidence_version === $profile->version
            && $this->hasEvidence($component, 'evidence_', $this->energyScope($component, $profile, $mapping))
            && ($component->overlap_group === null
                ? $component->overlap_rule_status === 'NOT_REQUIRED'
                : $component->overlap_rule_status === 'AUTHORIZED' && $this->nonEmpty($component->overlap_rule_code));
    }

    public function applicabilityAuthorized(
        RegulatoryEnergyApplicability $applicability,
        NutritionCalculation $calculation,
        RegulatoryEnergyComponent $component,
    ): bool {
        $component->loadMissing('profile');
        $profile = $component->profile;

        return $profile instanceof RegulatoryProfile
            && $this->profileAuthorized($profile)
            && in_array($applicability->status, ['APPLICABLE', 'NOT_APPLICABLE'], true)
            && (int) $applicability->nutrition_calculation_id === (int) $calculation->getKey()
            && (int) $applicability->profile_id === (int) $profile->getKey()
            && (int) $applicability->energy_component_id === (int) $component->getKey()
            && $this->nonEmpty($applicability->source_reference)
            && $applicability->evidence_version === $profile->version
            && $this->hasEvidence(
                $applicability,
                'evidence_',
                "nutrition-calculation:{$calculation->getKey()}/{$this->energyScope($component, $profile, $component->mapping)}",
            )
            && ($applicability->status !== 'NOT_APPLICABLE' || $this->nonEmpty($applicability->justification));
    }

    public function dailyValueAuthorized(RegulatoryDailyValue $dailyValue): bool
    {
        $dailyValue->loadMissing(['profile', 'mapping.profile', 'mapping.nutrient']);
        $profile = $dailyValue->profile;
        $mapping = $dailyValue->mapping;

        return $profile instanceof RegulatoryProfile
            && $mapping instanceof RegulatoryNutrientMapping
            && (int) $mapping->profile_id === (int) $profile->getKey()
            && $this->profileAuthorized($profile)
            && $this->mappingAuthorized($mapping)
            && $dailyValue->technical_status === 'AUTHORIZED'
            && $dailyValue->mapping_id !== null
            && $dailyValue->vdr_numerator !== null
            && $dailyValue->vdr_denominator !== null
            && $dailyValue->unit === $mapping->regulatory_unit
            && $this->nonEmpty($dailyValue->source_reference)
            && $dailyValue->evidence_version === $profile->version
            && $this->hasEvidence($dailyValue, 'evidence_', $this->dailyValueScope($dailyValue, $profile, $mapping));
    }

    public function noNormativeDailyValueAuthorized(RegulatoryDailyValue $dailyValue): bool
    {
        $dailyValue->loadMissing('profile');
        $profile = $dailyValue->profile;

        return $profile instanceof RegulatoryProfile
            && $this->profileAuthorized($profile)
            && $dailyValue->technical_status === 'NO_NORMATIVE_VDR'
            && $dailyValue->mapping_id === null
            && $dailyValue->vdr_numerator === null
            && $dailyValue->vdr_denominator === null
            && $this->nonEmpty($dailyValue->source_reference)
            && $dailyValue->evidence_version === $profile->version
            && $this->hasEvidence(
                $dailyValue,
                'evidence_',
                "{$this->profileScope($profile)}/vdr:{$dailyValue->vdr_code}/no-normative-vdr",
            );
    }

    public function hasEvidence(object $record, string $prefix, string $expectedScope): bool
    {
        foreach (['reference', 'source', 'version', 'scope', 'kind'] as $field) {
            if (! $this->nonEmpty($record->{$prefix.$field} ?? null)) {
                return false;
            }
        }

        $stem = str_replace('evidence_', '', $prefix);
        $validatedBy = $record->{$stem.'validated_by'} ?? null;
        $validatedOn = $record->{$stem.'validated_on'} ?? null;
        $kind = $record->{$prefix.'kind'};
        $scope = $record->{$prefix.'scope'};

        if (! $this->nonEmpty($validatedBy) || $validatedOn === null) {
            return false;
        }

        if ($kind === 'TEST_FIXTURE' && ! app()->environment('testing')) {
            return false;
        }

        return in_array($kind, ['DOCUMENTED', 'TEST_FIXTURE'], true)
            && is_string($scope)
            && trim($scope) === $expectedScope;
    }

    private function profileScope(RegulatoryProfile $profile): string
    {
        return "profile:{$profile->code}@{$profile->version}";
    }

    private function mappingScope(RegulatoryNutrientMapping $mapping, RegulatoryProfile $profile): string
    {
        return "{$this->profileScope($profile)}/map:{$mapping->map_code}/nutrient:{$mapping->nutrient_id}";
    }

    private function conversionScope(RegulatoryNutrientMapping $mapping, RegulatoryProfile $profile): string
    {
        return "{$this->mappingScope($mapping, $profile)}/conversion:{$mapping->source_unit}->{$mapping->regulatory_unit}";
    }

    private function energyScope(
        RegulatoryEnergyComponent $component,
        RegulatoryProfile $profile,
        ?RegulatoryNutrientMapping $mapping,
    ): string {
        $mapCode = $mapping?->map_code ?? 'missing';

        return "{$this->profileScope($profile)}/ene:{$component->ene_code}/map:{$mapCode}";
    }

    private function dailyValueScope(
        RegulatoryDailyValue $dailyValue,
        RegulatoryProfile $profile,
        RegulatoryNutrientMapping $mapping,
    ): string {
        return "{$this->profileScope($profile)}/vdr:{$dailyValue->vdr_code}/map:{$mapping->map_code}";
    }

    private function nonEmpty(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
