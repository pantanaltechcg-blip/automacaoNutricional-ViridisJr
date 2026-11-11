<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NutritionCalculation;
use App\Models\PortionAuthorization;
use App\Models\RegulatoryEnergyApplicability;
use App\Models\RegulatoryProfile;
use App\Models\ServingDailyValueCalculation;
use Brick\Math\BigRational;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class ServingDailyValueCalculationService
{
    public function __construct(
        private NutritionCalculationArithmetic $arithmetic,
        private ServingRegulatoryGateService $gates,
    ) {}

    /**
     * @param array{
     *   portion: array{value: string, unit: string, household_measure: ?string},
     *   portion_authorization_id: int,
     *   regulatory_profile_id: int
     * } $input
     */
    public function create(NutritionCalculation $calculation, array $input): ServingDailyValueCalculation
    {
        return DB::transaction(function () use ($calculation, $input): ServingDailyValueCalculation {
            $this->useRepeatableReadIsolation();

            $source = NutritionCalculation::query()
                ->with(['nutrients' => fn ($query) => $query->orderBy('nutrient_id'), 'nutrients.states'])
                ->findOrFail($calculation->getKey());
            $authorization = PortionAuthorization::query()->findOrFail($input['portion_authorization_id']);
            $profile = RegulatoryProfile::query()
                ->with([
                    'mappings' => fn ($query) => $query->with(['profile', 'nutrient'])->orderBy('map_code'),
                    'energyComponents' => fn ($query) => $query
                        ->with(['profile', 'mapping.profile', 'mapping.nutrient'])
                        ->orderBy('ene_code'),
                    'dailyValues' => fn ($query) => $query
                        ->with(['profile', 'mapping.profile', 'mapping.nutrient'])
                        ->orderBy('vdr_code'),
                ])
                ->findOrFail($input['regulatory_profile_id']);

            $this->gates->assertPortionAuthorized($source, $authorization, $input['portion']);
            $nutrientRows = $this->nutrientRows($source, $input['portion']['value']);
            $regulatory = $this->regulatoryRows($profile, $nutrientRows);
            $applicabilities = RegulatoryEnergyApplicability::query()
                ->where('nutrition_calculation_id', $source->getKey())
                ->where('profile_id', $profile->getKey())
                ->get()
                ->keyBy('energy_component_id');
            $energy = $this->energyRows(
                $source,
                $profile,
                $regulatory['rows'],
                $regulatory['authorized'],
                $applicabilities,
            );
            $percentages = $this->percentageRows($profile, $regulatory['rows'], $regulatory['authorized']);

            $root = ServingDailyValueCalculation::query()->create([
                'nutrition_calculation_id' => $source->getKey(),
                'source_recipe_id' => $source->recipe_id,
                'source_rules_version' => $source->rules_version,
                'source_yield_value' => $source->getRawOriginal('yield_value'),
                'source_yield_unit' => $source->yield_unit,
                'portion_value' => $input['portion']['value'],
                'portion_unit' => $input['portion']['unit'],
                'household_measure_mode' => $authorization->household_measure_mode,
                'household_measure' => $authorization->household_measure,
                'portion_justification' => $authorization->justification,
                'portion_reference_code' => $authorization->reference_code,
                'portion_reference_version' => $authorization->reference_version,
                'portion_reference_source' => $authorization->reference_source,
                'portion_reference_technical_status' => $authorization->technical_status,
                ...$this->evidenceSnapshot($authorization, 'evidence_', 'portion_evidence_'),
                'portion_validated_by' => $authorization->validated_by,
                'portion_validated_on' => $authorization->validated_on,
                'regulatory_profile_code' => $profile->code,
                'regulatory_profile_version' => $profile->version,
                'regulatory_profile_title' => $profile->title,
                'regulatory_profile_status' => $profile->technical_status,
                'regulatory_profile_normative_source' => $profile->normative_source,
                'regulatory_profile_normative_version' => $profile->normative_version,
                'regulatory_profile_consulted_on' => $profile->consulted_on,
                ...$this->evidenceSnapshot($profile, 'evidence_', 'regulatory_profile_evidence_'),
                'regulatory_profile_validated_by' => $profile->validated_by,
                'regulatory_profile_validated_on' => $profile->validated_on,
                'energy_numerator' => $energy['ratio']['numerator'] ?? null,
                'energy_denominator' => $energy['ratio']['denominator'] ?? null,
                'energy_unit' => $energy['ratio'] === null ? null : 'kcal',
                'energy_is_complete' => $energy['complete'],
                'energy_availability' => $energy['availability'],
                'rules_version' => ServingDailyValueCalculation::RULES_VERSION,
            ]);

            $snapshotNutrients = [];

            foreach ($nutrientRows as $row) {
                $states = $row['states'];
                unset($row['states']);
                $snapshot = $root->nutrients()->create($row);
                $snapshotNutrients[(int) $snapshot->nutrient_id] = $snapshot;

                foreach ($states as $state) {
                    $snapshot->states()->create(['state' => $state]);
                }
            }

            $regulatorySnapshots = [];

            foreach ($regulatory['rows'] as $row) {
                $nutrientId = $row['nutrient_id'];
                $row['serving_daily_value_nutrient_id'] = $nutrientId === null
                    ? null
                    : ($snapshotNutrients[$nutrientId]->getKey() ?? null);
                $snapshot = $root->regulatoryResults()->create($row);
                $regulatorySnapshots[$snapshot->map_code] = $snapshot;
            }

            foreach ($energy['rows'] as $row) {
                $root->energyComponents()->create($row);
            }

            foreach ($percentages['rows'] as $row) {
                $mapCode = $row['map_code'];
                $row['serving_daily_value_regulatory_result_id'] = $mapCode === null
                    ? null
                    : ($regulatorySnapshots[$mapCode]->getKey() ?? null);
                $root->percentages()->create($row);
            }

            foreach ([...$regulatory['causes'], ...$energy['causes'], ...$percentages['causes']] as $cause) {
                $root->causes()->create($cause);
            }

            return $this->retrieve($root);
        });
    }

    public function retrieve(ServingDailyValueCalculation $calculation): ServingDailyValueCalculation
    {
        return $calculation->load([
            'nutrients' => fn ($query) => $query->orderBy('nutrient_id'),
            'nutrients.states',
            'regulatoryResults' => fn ($query) => $query->orderBy('map_code'),
            'energyComponents' => fn ($query) => $query->orderBy('ene_code'),
            'percentages' => fn ($query) => $query->orderBy('vdr_code'),
            'causes' => fn ($query) => $query->orderBy('scope')->orderBy('scope_key')->orderBy('code'),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function nutrientRows(NutritionCalculation $source, string $portionValue): array
    {
        $rows = [];
        $yield = $source->getRawOriginal('yield_value');

        if (! is_string($yield)) {
            throw new UnexpectedValueException('O rendimento snapshot deve ser uma string decimal.');
        }

        foreach ($source->nutrients as $nutrient) {
            $sourceNumerator = $this->nullableString($nutrient->getRawOriginal('total_numerator'));
            $sourceDenominator = $this->nullableString($nutrient->getRawOriginal('total_denominator'));
            $portion = null;

            if ($sourceNumerator !== null && $sourceDenominator !== null) {
                $portion = $this->arithmetic->ratio(
                    $this->arithmetic->multiplyRatioByDecimal(
                        $sourceNumerator,
                        $sourceDenominator,
                        $portionValue,
                    )->dividedBy($this->arithmetic->fromDecimal($yield)),
                );
            }

            $rows[] = [
                'nutrition_calculation_nutrient_id' => $nutrient->getKey(),
                'nutrient_id' => (int) $nutrient->nutrient_id,
                'nutrient_name' => $nutrient->nutrient_name,
                'nutrient_unit' => $nutrient->nutrient_unit,
                'source_numerator' => $sourceNumerator,
                'source_denominator' => $sourceDenominator,
                'source_is_complete' => (bool) $nutrient->is_complete,
                'source_has_missing_relation' => (bool) $nutrient->has_missing_relation,
                'portion_numerator' => $portion['numerator'] ?? null,
                'portion_denominator' => $portion['denominator'] ?? null,
                'is_complete' => (bool) $nutrient->is_complete,
                'has_missing_relation' => (bool) $nutrient->has_missing_relation,
                'states' => $nutrient->states->pluck('state')->all(),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $nutrientRows
     * @return array{rows: list<array<string, mixed>>, causes: list<array<string, mixed>>, authorized: array<string, bool>}
     */
    private function regulatoryRows(RegulatoryProfile $profile, array $nutrientRows): array
    {
        $nutrients = collect($nutrientRows)->keyBy('nutrient_id');
        $rows = [];
        $causes = [];
        $authorizedMappings = [];
        $profileAuthorized = $this->gates->profileAuthorized($profile);

        foreach ($profile->mappings as $mapping) {
            $nutrient = $mapping->nutrient_id === null ? null : $nutrients->get((int) $mapping->nutrient_id);
            $mappingAuthorized = $profileAuthorized && $this->gates->mappingAuthorized($mapping);
            $authorizedMappings[$mapping->map_code] = $mappingAuthorized;
            $authorized = $mappingAuthorized && is_array($nutrient);
            $ratio = null;

            if ($authorized && $nutrient['portion_numerator'] !== null) {
                $value = $this->arithmetic->fromRatio($nutrient['portion_numerator'], $nutrient['portion_denominator']);

                if ($mapping->conversion_status === 'AUTHORIZED') {
                    $value = $value->multipliedBy($this->arithmetic->fromRatio(
                        $this->rawString($mapping, 'conversion_numerator'),
                        $this->rawString($mapping, 'conversion_denominator'),
                    ));
                }

                $ratio = $this->arithmetic->ratio($value);
            }

            $availability = ! $authorized || $ratio === null
                ? 'UNAVAILABLE'
                : ($nutrient['is_complete'] ? 'AVAILABLE' : 'PARTIAL');

            if (! $authorized) {
                $causes[] = $this->cause('REGULATORY_VALUE', $mapping->map_code, 'REGULATORY_BLOCK', 'MAP_NOT_TECHNICALLY_AUTHORIZED', $mapping->source_reference);
            } elseif ($ratio === null) {
                $causes[] = $this->cause('REGULATORY_VALUE', $mapping->map_code, 'MISSING_SOURCE', 'REGULATORY_NUMERATOR_UNAVAILABLE', $mapping->source_reference);
            } elseif (! $nutrient['is_complete']) {
                $causes[] = $this->cause('REGULATORY_VALUE', $mapping->map_code, 'INCOMPLETE_NUTRITION', 'REGULATORY_NUMERATOR_PARTIAL', $mapping->source_reference);
            }

            $rows[] = [
                'map_code' => $mapping->map_code,
                'component_name' => $mapping->component_name,
                'technical_status' => $mapping->technical_status,
                'nutrient_id' => $mapping->nutrient_id,
                'nutrient_name' => $nutrient['nutrient_name'] ?? null,
                'source_unit' => $mapping->source_unit,
                'regulatory_unit' => $mapping->regulatory_unit,
                'map_source_reference' => $mapping->source_reference,
                ...$this->evidenceSnapshot($mapping, 'evidence_', 'map_evidence_'),
                'map_validated_by' => $mapping->validated_by,
                'map_validated_on' => $mapping->validated_on,
                'conversion_status' => $mapping->conversion_status,
                'conversion_source_unit' => $mapping->conversion_source_unit,
                'conversion_target_unit' => $mapping->conversion_target_unit,
                'conversion_numerator' => $this->nullableString($mapping->getRawOriginal('conversion_numerator')),
                'conversion_denominator' => $this->nullableString($mapping->getRawOriginal('conversion_denominator')),
                ...$this->evidenceSnapshot($mapping, 'conversion_evidence_', 'conversion_evidence_'),
                'conversion_validated_by' => $mapping->conversion_validated_by,
                'conversion_validated_on' => $mapping->conversion_validated_on,
                'value_numerator' => $ratio['numerator'] ?? null,
                'value_denominator' => $ratio['denominator'] ?? null,
                'is_complete' => $authorized && $ratio !== null && (bool) $nutrient['is_complete'],
                'availability' => $availability,
            ];
        }

        return ['rows' => $rows, 'causes' => $causes, 'authorized' => $authorizedMappings];
    }

    /**
     * @param  list<array<string, mixed>>  $regulatoryRows
     * @param  array<string, bool>  $authorizedMappings
     * @param  Collection<int, RegulatoryEnergyApplicability>  $applicabilities
     * @return array{rows: list<array<string, mixed>>, causes: list<array<string, mixed>>, ratio: ?array{numerator: string, denominator: string}, complete: bool, availability: string}
     */
    private function energyRows(
        NutritionCalculation $source,
        RegulatoryProfile $profile,
        array $regulatoryRows,
        array $authorizedMappings,
        $applicabilities,
    ): array {
        $results = collect($regulatoryRows)->keyBy('map_code');
        $rows = [];
        $causes = [];
        $structural = $this->gates->profileAuthorized($profile) && $profile->energyComponents->isNotEmpty();
        $total = null;
        $complete = true;

        foreach ($profile->energyComponents as $component) {
            $applicability = $applicabilities->get($component->getKey());
            $applicable = $applicability instanceof RegulatoryEnergyApplicability
                && $this->gates->applicabilityAuthorized($applicability, $source, $component);
            $nonApplicable = $applicable && $applicability->status === 'NOT_APPLICABLE';
            $result = $component->mapping === null ? null : $results->get($component->mapping->map_code);
            $componentAuthorized = $this->gates->energyComponentAuthorized($component);
            $mappingAuthorized = $component->mapping !== null
                && ($authorizedMappings[$component->mapping->map_code] ?? false);
            $included = $applicable
                && ! $nonApplicable
                && $componentAuthorized
                && $mappingAuthorized
                && is_array($result);
            $input = $included && $result['value_numerator'] !== null
                ? ['numerator' => $result['value_numerator'], 'denominator' => $result['value_denominator']]
                : null;
            $contribution = null;

            if ($included) {
                if ($component->factor_input_unit !== $result['regulatory_unit']) {
                    $structural = false;
                    $complete = false;
                    $causes[] = $this->cause('ENERGY', 'ENERGY', 'UNIT_INCOMPATIBLE', 'ENE_UNIT_INCOMPATIBLE', $component->source_reference);
                } elseif ($input !== null) {
                    $value = $this->arithmetic->multiplyRatios(
                        $input['numerator'],
                        $input['denominator'],
                        $this->rawString($component, 'factor_numerator'),
                        $this->rawString($component, 'factor_denominator'),
                    );
                    $contribution = $this->arithmetic->ratio($value);
                    $total = $total instanceof BigRational ? $total->plus($value) : $value;
                    $complete = $complete && (bool) $result['is_complete'];
                } else {
                    $complete = false;
                    $causes[] = $this->cause('ENERGY', 'ENERGY', 'INCOMPLETE_NUTRITION', 'ENE_COMPONENT_WITHOUT_NUMERIC_VALUE', $component->source_reference);
                }
            } elseif (! $nonApplicable) {
                $structural = false;
                $complete = false;
            }

            if (! $applicable) {
                $causes[] = $this->cause('ENERGY', 'ENERGY', 'UNKNOWN_APPLICABILITY', 'ENE_APPLICABILITY_NOT_AUTHORIZED', $component->source_reference);
            } elseif (! $nonApplicable && (! $componentAuthorized || ! $mappingAuthorized)) {
                $causes[] = $component->overlap_group !== null && $component->overlap_rule_status !== 'AUTHORIZED'
                    ? $this->cause('ENERGY', 'ENERGY', 'DOUBLE_COUNTING_RISK', 'ENE_OVERLAP_NOT_AUTHORIZED', $component->source_reference)
                    : $this->cause(
                        'ENERGY',
                        'ENERGY',
                        'REGULATORY_BLOCK',
                        $mappingAuthorized ? 'ENE_COMPONENT_NOT_AUTHORIZED' : 'ENE_MAPPING_NOT_AUTHORIZED',
                        $component->source_reference,
                    );
            }

            $rows[] = [
                'ene_code' => $component->ene_code,
                'component_name' => $component->component_name,
                'map_code' => $component->mapping?->map_code,
                'applicability_status' => $applicability?->status ?? 'UNKNOWN',
                'participation' => $nonApplicable ? 'NON_APPLICABLE' : ($included ? 'INCLUDED' : 'BLOCKED'),
                'factor_numerator' => $this->nullableString($component->getRawOriginal('factor_numerator')),
                'factor_denominator' => $this->nullableString($component->getRawOriginal('factor_denominator')),
                'input_numerator' => $input['numerator'] ?? null,
                'input_denominator' => $input['denominator'] ?? null,
                'contribution_numerator' => $contribution['numerator'] ?? null,
                'contribution_denominator' => $contribution['denominator'] ?? null,
                'factor_input_unit' => $component->factor_input_unit,
                'factor_output_unit' => $component->factor_output_unit,
                'source_reference' => $component->source_reference,
                'source_version' => $component->evidence_version,
                'technical_status' => $component->technical_status,
                ...$this->evidenceSnapshot($component, 'evidence_', 'ene_evidence_'),
                'ene_validated_by' => $component->validated_by,
                'ene_validated_on' => $component->validated_on,
                'overlap_group' => $component->overlap_group,
                'overlap_rule_code' => $component->overlap_rule_code,
                'overlap_rule_status' => $component->overlap_rule_status,
                ...($applicability === null ? $this->emptyEvidence('applicability_evidence_') : $this->evidenceSnapshot($applicability, 'evidence_', 'applicability_evidence_')),
                'applicability_validated_by' => $applicability?->validated_by,
                'applicability_validated_on' => $applicability?->validated_on,
                'non_applicability_justification' => $nonApplicable ? $applicability->justification : null,
                'non_applicability_source' => $nonApplicable ? $applicability->source_reference : null,
            ];
        }

        if (! $structural) {
            $total = null;
            $complete = false;
            $causes[] = $this->cause('ENERGY', 'ENERGY', 'REGULATORY_BLOCK', 'ENE_MATRIX_NOT_AUTHORIZED');
            foreach ($rows as &$row) {
                $row['contribution_numerator'] = null;
                $row['contribution_denominator'] = null;
            }
            unset($row);
        }

        $ratio = $total instanceof BigRational ? $this->arithmetic->ratio($total) : null;
        $complete = $ratio !== null && $complete;
        $availability = $ratio === null ? 'UNAVAILABLE' : ($complete ? 'AVAILABLE' : 'PARTIAL');

        if ($ratio !== null && ! $complete) {
            $causes[] = $this->cause('ENERGY', 'ENERGY', 'INCOMPLETE_NUTRITION', 'ENERGY_SUBTOTAL_PARTIAL');
        }

        return compact('rows', 'causes', 'ratio', 'complete', 'availability');
    }

    /**
     * @param  list<array<string, mixed>>  $regulatoryRows
     * @param  array<string, bool>  $authorizedMappings
     * @return array{rows: list<array<string, mixed>>, causes: list<array<string, mixed>>}
     */
    private function percentageRows(RegulatoryProfile $profile, array $regulatoryRows, array $authorizedMappings): array
    {
        $results = collect($regulatoryRows)->keyBy('map_code');
        $rows = [];
        $causes = [];
        $profileAuthorized = $this->gates->profileAuthorized($profile);

        foreach ($profile->dailyValues as $dailyValue) {
            $mapCode = $dailyValue->mapping?->map_code;
            $result = $mapCode === null ? null : $results->get($mapCode);
            $ratio = null;
            $availability = 'UNAVAILABLE';
            $complete = false;

            if ($this->gates->noNormativeDailyValueAuthorized($dailyValue)) {
                $availability = 'NO_NORMATIVE_VDR';
                $causes[] = $this->cause('DAILY_VALUE', $dailyValue->vdr_code, 'NO_NORMATIVE_VDR', 'NO_NORMATIVE_VDR', $dailyValue->source_reference);
            } elseif ($profileAuthorized
                && $this->gates->dailyValueAuthorized($dailyValue)
                && $mapCode !== null
                && ($authorizedMappings[$mapCode] ?? false)
                && is_array($result)
                && $result['value_numerator'] !== null
                && $result['regulatory_unit'] === $dailyValue->unit) {
                $ratio = $this->arithmetic->ratio($this->arithmetic->percentage(
                    $result['value_numerator'],
                    $result['value_denominator'],
                    $this->rawString($dailyValue, 'vdr_numerator'),
                    $this->rawString($dailyValue, 'vdr_denominator'),
                ));
                $complete = (bool) $result['is_complete'];
                $availability = $complete ? 'AVAILABLE' : 'PARTIAL';
            } else {
                $causes[] = $this->cause('DAILY_VALUE', $dailyValue->vdr_code, 'MISSING_NUMERATOR', 'DAILY_VALUE_GATE_NOT_AUTHORIZED', $dailyValue->source_reference);
            }

            $rows[] = [
                'vdr_code' => $dailyValue->vdr_code,
                'component_name' => $dailyValue->component_name,
                'map_code' => $mapCode,
                'vdr_numerator' => $this->nullableString($dailyValue->getRawOriginal('vdr_numerator')),
                'vdr_denominator' => $this->nullableString($dailyValue->getRawOriginal('vdr_denominator')),
                'vdr_unit' => $dailyValue->unit,
                'vdr_source_reference' => $dailyValue->source_reference,
                'vdr_source_version' => $dailyValue->evidence_version,
                'technical_status' => $dailyValue->technical_status,
                ...$this->evidenceSnapshot($dailyValue, 'evidence_', 'vdr_evidence_'),
                'vdr_validated_by' => $dailyValue->validated_by,
                'vdr_validated_on' => $dailyValue->validated_on,
                'numerator_identity' => $mapCode,
                'numerator_numerator' => $result['value_numerator'] ?? null,
                'numerator_denominator' => $result['value_denominator'] ?? null,
                'percentage_numerator' => $ratio['numerator'] ?? null,
                'percentage_denominator' => $ratio['denominator'] ?? null,
                'is_complete' => $complete,
                'availability' => $availability,
            ];
        }

        return compact('rows', 'causes');
    }

    /** @return array<string, mixed> */
    private function evidenceSnapshot(object $record, string $sourcePrefix, string $targetPrefix): array
    {
        $result = [];

        foreach (['reference', 'source', 'version', 'scope', 'kind'] as $field) {
            $result[$targetPrefix.$field] = $record->{$sourcePrefix.$field} ?? null;
        }

        return $result;
    }

    /** @return array<string, null> */
    private function emptyEvidence(string $prefix): array
    {
        return collect(['reference', 'source', 'version', 'scope', 'kind'])
            ->mapWithKeys(fn (string $field): array => [$prefix.$field => null])
            ->all();
    }

    /** @return array<string, mixed> */
    private function cause(string $scope, string $key, string $category, string $code, ?string $reference = null): array
    {
        return ['scope' => $scope, 'scope_key' => $key, 'category' => $category, 'code' => $code, 'detail' => null, 'reference' => $reference];
    }

    private function rawString(object $model, string $attribute): string
    {
        $value = $model->getRawOriginal($attribute);

        if (! is_string($value)) {
            throw new UnexpectedValueException("{$attribute} deve ser uma string numérica.");
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function useRepeatableReadIsolation(): void
    {
        if (DB::transactionLevel() === 1) {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }
    }
}
