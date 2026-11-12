<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeclaredNutritionCalculation;
use App\Models\DeclaredNutritionCause;
use App\Models\DeclaredNutritionComponent;
use App\Models\DeclaredNutritionComponentState;
use App\Models\DeclaredNutritionEnergyComponent;
use App\Models\DeclaredNutritionPercentage;
use App\Models\RegulatoryDeclarationProfile;
use App\Models\RegulatoryNonSignificantRule;
use App\Models\RegulatoryRoundingRule;
use App\Models\ServingDailyValueCalculation;
use App\Models\ServingDailyValueNutrient;
use App\Models\ServingDailyValueRegulatoryResult;
use Brick\Math\BigRational;
use Brick\Math\Exception\RoundingNecessaryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class DeclaredNutritionCalculationService
{
    /** @var array<int, string> */
    private const QUALITATIVE_STATE_ORDER = ['TRACE', 'NA', 'ABSENT', 'REEVALUATION'];

    public function __construct(
        private NutritionCalculationArithmetic $arithmetic,
        private DeclaredValuesRegulatoryGateService $gates,
    ) {}

    /**
     * @param  array{regulatory_declaration_profile_id: int}  $input
     */
    public function create(ServingDailyValueCalculation $servingDailyValueCalculation, array $input): DeclaredNutritionCalculation
    {
        return DB::transaction(function () use ($servingDailyValueCalculation, $input): DeclaredNutritionCalculation {
            $this->useRepeatableReadIsolation();

            $source = ServingDailyValueCalculation::query()
                ->with([
                    'nutrients' => fn ($query) => $query->with(['sourceNutrient', 'states']),
                    'regulatoryResults' => fn ($query) => $query->orderBy('map_code'),
                    'energyComponents' => fn ($query) => $query->orderBy('ene_code'),
                    'percentages' => fn ($query) => $query->orderBy('vdr_code'),
                ])
                ->findOrFail($servingDailyValueCalculation->getKey());

            $profile = RegulatoryDeclarationProfile::query()
                ->with([
                    'recalculationPolicy',
                    'nonSignificantRules',
                    'roundingRules.ranges',
                    'requirements',
                ])
                ->findOrFail($input['regulatory_declaration_profile_id']);

            $this->gates->assertProfileMatchesSource($profile, $source);

            $policy = $profile->recalculationPolicy;
            $componentEvaluation = $this->componentRows($source, $profile);
            $energyEvaluation = $this->energyRows($source, $profile, $componentEvaluation['rows']);
            $percentageEvaluation = $this->percentageRows(
                $source,
                $profile,
                $componentEvaluation['rows'],
                $energyEvaluation['portion_declared'],
            );
            $readinessEvaluation = $this->gates->evaluateLabelReadiness(
                $source,
                $profile,
                $componentEvaluation['rows'],
                $energyEvaluation['root_fields'],
                $percentageEvaluation['rows'],
            );

            $calculation = DeclaredNutritionCalculation::query()->create(array_merge(
                [
                    'serving_daily_value_calculation_id' => $source->getKey(),
                    'source_nutrition_calculation_id' => $source->nutrition_calculation_id,
                    'source_recipe_id' => $source->source_recipe_id,
                    'source_003_rules_version' => $source->source_rules_version,
                    'source_004_rules_version' => $source->rules_version,
                    'source_yield_value' => $this->rawString($source, 'source_yield_value'),
                    'source_yield_unit' => $source->source_yield_unit,
                    'portion_value' => $this->rawString($source, 'portion_value'),
                    'portion_unit' => $source->portion_unit,
                    'household_measure_mode' => $source->household_measure_mode,
                    'household_measure' => $source->household_measure,
                    'portion_justification' => $source->portion_justification,
                    'portion_reference_technical_status' => $source->portion_reference_technical_status,
                    'regulatory_profile_code' => $source->regulatory_profile_code,
                    'regulatory_profile_version' => $source->regulatory_profile_version,
                    'regulatory_profile_status' => $source->regulatory_profile_status,
                    'regulatory_profile_normative_source' => $source->regulatory_profile_normative_source,
                    'regulatory_profile_normative_version' => $source->regulatory_profile_normative_version,
                    'declaration_profile_code' => $profile->code,
                    'declaration_profile_version' => $profile->version,
                    'declaration_profile_title' => $profile->title,
                    'declaration_profile_status' => $profile->technical_status,
                    'declaration_profile_normative_source' => $profile->normative_source,
                    'declaration_profile_normative_version' => $profile->normative_version,
                    'declaration_profile_consulted_on' => $profile->consulted_on,
                    'declaration_profile_scope_recipe_id' => $profile->scope_recipe_id,
                    'declaration_profile_scope_nutrition_calculation_id' => $profile->scope_nutrition_calculation_id,
                    'recalculation_policy_code' => $policy?->policy_code,
                    'recalculation_policy_status' => $policy?->technical_status,
                    'recalculation_normative_order_mode' => $policy?->normative_order_mode,
                    'recalculation_requires_complete_energy_inputs' => $policy?->requires_complete_energy_inputs,
                    'recalculation_policy_source_reference' => $policy?->source_reference,
                    'source_portion_energy_numerator' => $this->nullableString($source->energy_numerator),
                    'source_portion_energy_denominator' => $this->nullableString($source->energy_denominator),
                    'source_portion_energy_unit' => $source->energy_unit,
                    'source_portion_energy_is_complete' => $source->energy_is_complete,
                    'source_portion_energy_availability' => $source->energy_availability,
                    'is_label_ready' => $readinessEvaluation['ready'],
                    'rules_version' => DeclaredNutritionCalculation::RULES_VERSION,
                ],
                $this->evidenceSnapshot($source, 'portion_', 'portion_'),
                $this->evidenceSnapshot($profile, '', 'declaration_profile_'),
                $policy !== null ? $this->evidenceSnapshot($policy, '', 'recalculation_policy_') : [],
                $energyEvaluation['root_fields'],
            ));

            foreach ($componentEvaluation['rows'] as $row) {
                $states = $row['states'];
                unset($row['states']);

                $component = DeclaredNutritionComponent::query()->create(array_merge(
                    $row,
                    ['declared_nutrition_calculation_id' => $calculation->getKey()],
                ));

                foreach ($states as $state) {
                    DeclaredNutritionComponentState::query()->create([
                        'declared_nutrition_component_id' => $component->getKey(),
                        'state' => $state,
                    ]);
                }
            }

            foreach ($energyEvaluation['component_rows'] as $eneRow) {
                DeclaredNutritionEnergyComponent::query()->create(array_merge(
                    $eneRow,
                    ['declared_nutrition_calculation_id' => $calculation->getKey()],
                ));
            }

            foreach ($percentageEvaluation['rows'] as $pctRow) {
                DeclaredNutritionPercentage::query()->create(array_merge(
                    $pctRow,
                    ['declared_nutrition_calculation_id' => $calculation->getKey()],
                ));
            }

            $allCauses = collect(array_merge(
                $componentEvaluation['causes'],
                $energyEvaluation['causes'],
                $percentageEvaluation['causes'],
                $readinessEvaluation['causes'],
            ))->unique(static fn (array $cause): string => "{$cause['scope']}|{$cause['scope_key']}|{$cause['code']}");

            foreach ($allCauses as $cause) {
                DeclaredNutritionCause::query()->create([
                    'declared_nutrition_calculation_id' => $calculation->getKey(),
                    'scope' => $cause['scope'],
                    'scope_key' => $cause['scope_key'],
                    'category' => $cause['category'],
                    'code' => $cause['code'],
                    'detail' => $cause['detail'],
                    'source_reference' => $cause['source_reference'],
                ]);
            }

            return $this->retrieve($calculation);
        });
    }

    public function retrieve(DeclaredNutritionCalculation $declaredNutritionCalculation): DeclaredNutritionCalculation
    {
        return DeclaredNutritionCalculation::query()
            ->with([
                'components' => fn ($query) => $query->orderBy('map_code')->with('states'),
                'energyComponents' => fn ($query) => $query->orderBy('basis')->orderBy('ene_code'),
                'percentages' => fn ($query) => $query->orderBy('vdr_code'),
                'causes' => fn ($query) => $query->orderBy('scope')->orderBy('scope_key')->orderBy('code'),
            ])
            ->findOrFail($declaredNutritionCalculation->getKey());
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, causes: array<int, array<string, mixed>>}
     */
    private function componentRows(ServingDailyValueCalculation $source, RegulatoryDeclarationProfile $profile): array
    {
        $svNutrientsById = $source->nutrients->keyBy('id');
        $nsqByKey = $this->indexComponentNsqRules($profile->nonSignificantRules);
        $roundingByKey = $this->indexComponentRoundingRules($profile->roundingRules);
        $profileOk = $this->gates->profileAuthorized($profile);

        $rows = [];
        $causes = [];

        foreach ($source->regulatoryResults as $result) {
            /** @var ServingDailyValueNutrient|null $svNutrient */
            $svNutrient = $result->serving_daily_value_nutrient_id !== null
                ? $svNutrientsById->get((int) $result->serving_daily_value_nutrient_id)
                : null;
            $calcNutrient = $svNutrient?->sourceNutrient;

            $states = $this->orderedQualitativeStates($svNutrient);
            $sourceHasMissingRelation = (bool) ($svNutrient?->has_missing_relation ?? $calcNutrient?->has_missing_relation ?? false);
            $sourceIsComplete = $svNutrient !== null
                && $calcNutrient !== null
                && (bool) $svNutrient->is_complete
                && (bool) $calcNutrient->is_complete
                && ! $sourceHasMissingRelation;

            $mapAuthorized = $this->gates->sourceMappingAuthorized($source, $result);
            $conversionAuthorized = $this->gates->sourceConversionAuthorized($source, $result);

            [$ref100SourceRatio, $ref100RegRatio] = $this->resolveReference100Ratios(
                $calcNutrient?->reference_numerator !== null ? $this->nullableString($calcNutrient->reference_numerator) : null,
                $calcNutrient?->reference_denominator !== null ? $this->nullableString($calcNutrient->reference_denominator) : null,
                $result,
                $mapAuthorized,
                $conversionAuthorized,
            );

            $portionSourceRatio = $this->canonicalRatio(
                $svNutrient?->portion_numerator !== null ? $this->nullableString($svNutrient->portion_numerator) : null,
                $svNutrient?->portion_denominator !== null ? $this->nullableString($svNutrient->portion_denominator) : null,
            );
            $portionRegRatio = ($mapAuthorized && $conversionAuthorized)
                ? $this->canonicalRatio(
                    $result->value_numerator !== null ? $this->nullableString($result->value_numerator) : null,
                    $result->value_denominator !== null ? $this->nullableString($result->value_denominator) : null,
                )
                : null;

            $ref100Eval = $this->evaluateComponentBasis(
                basis: 'REFERENCE_100',
                result: $result,
                profile: $profile,
                profileOk: $profileOk,
                mapAuthorized: $mapAuthorized,
                conversionAuthorized: $conversionAuthorized,
                qualitativeStates: $states,
                sourceHasMissingRelation: $sourceHasMissingRelation,
                sourceIsComplete: $sourceIsComplete,
                svNutrientMissing: $svNutrient === null || $calcNutrient === null,
                regulatoryRatio: $ref100RegRatio,
                nsqRule: $nsqByKey->get("{$result->map_code}:REFERENCE_100"),
                roundingRule: $roundingByKey->get("{$result->map_code}:REFERENCE_100"),
            );

            $portionEval = $this->evaluateComponentBasis(
                basis: 'PORTION',
                result: $result,
                profile: $profile,
                profileOk: $profileOk,
                mapAuthorized: $mapAuthorized,
                conversionAuthorized: $conversionAuthorized,
                qualitativeStates: $states,
                sourceHasMissingRelation: $sourceHasMissingRelation,
                sourceIsComplete: $sourceIsComplete,
                svNutrientMissing: $svNutrient === null || $calcNutrient === null,
                regulatoryRatio: $portionRegRatio,
                nsqRule: $nsqByKey->get("{$result->map_code}:PORTION"),
                roundingRule: $roundingByKey->get("{$result->map_code}:PORTION"),
            );

            array_push($causes, ...$ref100Eval['causes'], ...$portionEval['causes']);

            $rows[] = array_merge(
                [
                    'serving_daily_value_regulatory_result_id' => $result->getKey(),
                    'map_code' => $result->map_code,
                    'component_name' => $result->component_name,
                    'map_technical_status' => $result->technical_status,
                    'nutrient_id' => $result->nutrient_id,
                    'nutrient_name' => $result->nutrient_name,
                    'source_unit' => $result->source_unit,
                    'regulatory_unit' => $result->regulatory_unit,
                    'map_source_reference' => $result->map_source_reference,
                    'conversion_status' => $result->conversion_status,
                    'conversion_source_unit' => $result->conversion_source_unit,
                    'conversion_target_unit' => $result->conversion_target_unit,
                    'conversion_numerator' => $this->nullableString($result->conversion_numerator),
                    'conversion_denominator' => $this->nullableString($result->conversion_denominator),
                    'source_is_complete' => $sourceIsComplete,
                    'source_has_missing_relation' => $sourceHasMissingRelation,
                    'reference_100_source_numerator' => $ref100SourceRatio['numerator'] ?? null,
                    'reference_100_source_denominator' => $ref100SourceRatio['denominator'] ?? null,
                    'reference_100_regulatory_numerator' => $ref100RegRatio['numerator'] ?? null,
                    'reference_100_regulatory_denominator' => $ref100RegRatio['denominator'] ?? null,
                    'portion_source_numerator' => $portionSourceRatio['numerator'] ?? null,
                    'portion_source_denominator' => $portionSourceRatio['denominator'] ?? null,
                    'portion_regulatory_numerator' => $portionRegRatio['numerator'] ?? null,
                    'portion_regulatory_denominator' => $portionRegRatio['denominator'] ?? null,
                    'states' => $states,
                ],
                $this->evidenceSnapshot($result, 'map_', 'map_'),
                $this->evidenceSnapshot($result, 'conversion_', 'conversion_'),
                $ref100Eval['fields'],
                $portionEval['fields'],
            );
        }

        return ['rows' => $rows, 'causes' => $causes];
    }

    /**
     * @param  array<int, string>  $qualitativeStates
     * @param  array{numerator: string, denominator: string}|null  $regulatoryRatio
     * @return array{fields: array<string, mixed>, causes: array<int, array<string, mixed>>}
     */
    private function evaluateComponentBasis(
        string $basis,
        ServingDailyValueRegulatoryResult $result,
        RegulatoryDeclarationProfile $profile,
        bool $profileOk,
        bool $mapAuthorized,
        bool $conversionAuthorized,
        array $qualitativeStates,
        bool $sourceHasMissingRelation,
        bool $sourceIsComplete,
        bool $svNutrientMissing,
        ?array $regulatoryRatio,
        ?RegulatoryNonSignificantRule $nsqRule,
        ?RegulatoryRoundingRule $roundingRule,
    ): array {
        $prefix = $basis === 'REFERENCE_100' ? 'reference_100_' : 'portion_';
        $causeScope = $basis === 'REFERENCE_100' ? 'COMPONENT_REFERENCE_100' : 'COMPONENT_PORTION';
        $causes = [];

        if (! $profileOk) {
            $causes[] = $this->cause($causeScope, $result->map_code, 'REGULATORY_BLOCK', 'DECLARATION_PROFILE_BLOCKED');
        }
        if (! $mapAuthorized) {
            $causes[] = $this->cause($causeScope, $result->map_code, 'REGULATORY_BLOCK', 'MAP_BLOCKED', $result->map_source_reference);
        }
        if (! $conversionAuthorized) {
            $causes[] = $this->cause($causeScope, $result->map_code, 'REGULATORY_BLOCK', 'CONVERSION_BLOCKED');
        }
        if ($qualitativeStates !== []) {
            $causes[] = $this->cause($causeScope, $result->map_code, 'QUALITATIVE_STATE_BLOCK', 'QUALITATIVE_STATE_PRESENT');
        }
        if ($sourceHasMissingRelation) {
            $causes[] = $this->cause($causeScope, $result->map_code, 'MISSING_RELATION_BLOCK', 'MISSING_FOOD_NUTRIENT_RELATION');
        }
        if ($svNutrientMissing) {
            $causes[] = $this->cause($causeScope, $result->map_code, 'MISSING_SOURCE', 'MISSING_SOURCE_NUTRIENT');
        } elseif (! $sourceIsComplete || $regulatoryRatio === null) {
            $causes[] = $this->cause($causeScope, $result->map_code, 'INCOMPLETE_NUTRITION', 'INCOMPLETE_SOURCE_VALUE');
        }

        $fields = array_merge(
            [
                "{$prefix}is_exact_zero" => false,
                "{$prefix}nsq_rule_code" => $nsqRule?->rule_code,
                "{$prefix}nsq_rule_status" => $nsqRule?->technical_status,
                "{$prefix}nsq_applied" => false,
                "{$prefix}rounding_rule_code" => $roundingRule?->rule_code,
                "{$prefix}rounding_rule_status" => $roundingRule?->technical_status,
                "{$prefix}rounding_tie_breaker" => $roundingRule?->tie_breaker,
                "{$prefix}rounding_step_numerator" => null,
                "{$prefix}rounding_step_denominator" => null,
                "{$prefix}rounding_decimal_places" => null,
                "{$prefix}declared_string" => null,
                "{$prefix}declared_numerator" => null,
                "{$prefix}declared_denominator" => null,
                "{$prefix}is_complete" => false,
                "{$prefix}availability" => 'UNAVAILABLE',
            ],
            $nsqRule !== null ? $this->evidenceSnapshot($nsqRule, '', "{$prefix}nsq_") : [],
            $roundingRule !== null ? $this->evidenceSnapshot($roundingRule, '', "{$prefix}rounding_") : [],
        );

        if ($causes !== [] || $regulatoryRatio === null) {
            return ['fields' => $fields, 'causes' => $causes];
        }

        $regValue = $this->arithmetic->fromRatio($regulatoryRatio['numerator'], $regulatoryRatio['denominator']);
        $isExactZero = $this->arithmetic->compare($regValue, $this->arithmetic->fromDecimal('0')) === 0;
        $fields["{$prefix}is_exact_zero"] = $isExactZero;

        $applied = $this->applyDeclarationRules(
            exactValue: $regValue,
            skipNsqWhenExactZero: true,
            unit: $result->regulatory_unit,
            profile: $profile,
            nsqRule: $nsqRule,
            roundingRule: $roundingRule,
            causeScope: $causeScope,
            causeKey: $result->map_code,
        );

        array_push($causes, ...$applied['causes']);
        $fields["{$prefix}nsq_applied"] = $applied['nsq_applied'];
        $fields["{$prefix}rounding_step_numerator"] = $applied['step']['numerator'] ?? null;
        $fields["{$prefix}rounding_step_denominator"] = $applied['step']['denominator'] ?? null;
        $fields["{$prefix}rounding_decimal_places"] = $applied['decimal_places'];
        $fields["{$prefix}declared_string"] = $applied['declared_string'];
        $fields["{$prefix}declared_numerator"] = $applied['declared_ratio']['numerator'] ?? null;
        $fields["{$prefix}declared_denominator"] = $applied['declared_ratio']['denominator'] ?? null;
        $fields["{$prefix}is_complete"] = $applied['complete'];
        $fields["{$prefix}availability"] = $applied['availability'];

        return ['fields' => $fields, 'causes' => $causes];
    }

    /**
     * @param  array<int, array<string, mixed>>  $componentRows
     * @return array{
     *   root_fields: array<string, mixed>,
     *   component_rows: array<int, array<string, mixed>>,
     *   causes: array<int, array<string, mixed>>,
     *   portion_declared: array{complete: bool, string: ?string, numerator: ?string, denominator: ?string, unit: string}
     * }
     */
    private function energyRows(
        ServingDailyValueCalculation $source,
        RegulatoryDeclarationProfile $profile,
        array $componentRows,
    ): array {
        $componentsByMap = collect($componentRows)->keyBy('map_code');
        $policyOk = $this->gates->recalculationPolicyAuthorized($profile, $profile->recalculationPolicy);
        $matrixOk = $this->gates->energyMatrixAuthorized($source);

        $nsqByBasis = $profile->nonSignificantRules
            ->filter(static fn (RegulatoryNonSignificantRule $rule): bool => $rule->target_kind === 'ENERGY')
            ->keyBy('basis');
        $roundingByBasis = $profile->roundingRules
            ->filter(static fn (RegulatoryRoundingRule $rule): bool => $rule->target_kind === 'ENERGY')
            ->keyBy('basis');

        $rootFields = [];
        $allEnergyComponents = [];
        $causes = [];
        $portionDeclared = [
            'complete' => false,
            'string' => null,
            'numerator' => null,
            'denominator' => null,
            'unit' => 'kcal',
        ];

        foreach (['REFERENCE_100', 'PORTION'] as $basis) {
            $basisKey = $basis === 'REFERENCE_100' ? 'reference_100' : 'portion';
            $causeScope = "ENERGY_{$basis}";
            $nsqRule = $nsqByBasis->get($basis);
            $roundingRule = $roundingByBasis->get($basis);
            $basisCauses = [];

            if (! $policyOk) {
                $basisCauses[] = $this->cause(
                    $causeScope,
                    'ENERGY',
                    'POLICY_BLOCK',
                    'RECALCULATION_POLICY_BLOCKED',
                    $profile->recalculationPolicy?->source_reference,
                );
            }

            if (! $matrixOk) {
                array_push($basisCauses, ...$this->energyMatrixCauses($source, $causeScope));
            }

            $sum = $this->arithmetic->fromDecimal('0');
            $allApplicableReady = true;

            foreach ($source->energyComponents as $ene) {
                $eneAuthorized = $this->gates->sourceEnergyComponentAuthorized($source, $ene);
                $inputString = null;
                $inputRatio = null;
                $contributionRatio = null;
                $participation = $ene->participation;

                if (! $eneAuthorized) {
                    $participation = 'BLOCKED';
                    $allApplicableReady = false;
                } elseif ($ene->applicability_status === 'APPLICABLE') {
                    $compRow = $ene->map_code !== null ? $componentsByMap->get($ene->map_code) : null;
                    $compComplete = (bool) ($compRow["{$basisKey}_is_complete"] ?? false);
                    $declStr = $compRow["{$basisKey}_declared_string"] ?? null;
                    $declNum = $compRow["{$basisKey}_declared_numerator"] ?? null;
                    $declDen = $compRow["{$basisKey}_declared_denominator"] ?? null;
                    $unitMatch = $compRow !== null && $compRow['regulatory_unit'] === $ene->factor_input_unit;

                    if ($compComplete && $declStr !== null && $declNum !== null && $declDen !== null && $unitMatch) {
                        $inputString = (string) $declStr;
                        $inputRatio = ['numerator' => (string) $declNum, 'denominator' => (string) $declDen];
                        $factor = $this->arithmetic->fromRatio(
                            (string) $ene->factor_numerator,
                            (string) $ene->factor_denominator,
                        );
                        $contrib = $this->arithmetic->fromRatio($inputRatio['numerator'], $inputRatio['denominator'])
                            ->multipliedBy($factor);
                        $contributionRatio = $this->arithmetic->ratio($contrib);
                        $sum = $this->arithmetic->addRationals($sum, $contrib);
                        $participation = 'INCLUDED';
                    } else {
                        $participation = 'BLOCKED';
                        $allApplicableReady = false;
                        $basisCauses[] = $this->cause(
                            $causeScope,
                            $ene->ene_code,
                            'INCOMPLETE_NUTRITION',
                            'ENERGY_INPUT_UNAVAILABLE',
                            $ene->source_reference,
                        );
                    }
                } else {
                    $participation = 'NON_APPLICABLE';
                }

                $allEnergyComponents[] = array_merge(
                    [
                        'basis' => $basis,
                        'ene_code' => $ene->ene_code,
                        'component_name' => $ene->component_name,
                        'map_code' => $ene->map_code,
                        'applicability_status' => $ene->applicability_status,
                        'participation' => $participation,
                        'factor_numerator' => $this->nullableString($ene->factor_numerator),
                        'factor_denominator' => $this->nullableString($ene->factor_denominator),
                        'factor_input_unit' => $ene->factor_input_unit,
                        'factor_output_unit' => $ene->factor_output_unit,
                        'input_declared_string' => $inputString,
                        'input_declared_numerator' => $inputRatio['numerator'] ?? null,
                        'input_declared_denominator' => $inputRatio['denominator'] ?? null,
                        'contribution_numerator' => $contributionRatio['numerator'] ?? null,
                        'contribution_denominator' => $contributionRatio['denominator'] ?? null,
                        'ene_technical_status' => $ene->technical_status,
                        'ene_source_reference' => $ene->source_reference,
                        'ene_source_version' => $ene->source_version,
                        'non_applicability_justification' => $ene->non_applicability_justification,
                        'overlap_group' => $ene->overlap_group,
                        'overlap_rule_code' => $ene->overlap_rule_code,
                        'overlap_rule_status' => $ene->overlap_rule_status,
                    ],
                    $this->evidenceSnapshot($ene, 'ene_', 'ene_'),
                    $this->evidenceSnapshot($ene, 'applicability_', 'applicability_'),
                );
            }

            $basisFields = array_merge(
                [
                    "{$basisKey}_exact_energy_numerator" => null,
                    "{$basisKey}_exact_energy_denominator" => null,
                    "{$basisKey}_energy_nsq_applied" => false,
                    "{$basisKey}_energy_nsq_rule_code" => $nsqRule?->rule_code,
                    "{$basisKey}_energy_nsq_rule_status" => $nsqRule?->technical_status,
                    "{$basisKey}_energy_rounding_rule_code" => $roundingRule?->rule_code,
                    "{$basisKey}_energy_rounding_rule_status" => $roundingRule?->technical_status,
                    "{$basisKey}_energy_rounding_tie_breaker" => $roundingRule?->tie_breaker,
                    "{$basisKey}_energy_rounding_step_numerator" => null,
                    "{$basisKey}_energy_rounding_step_denominator" => null,
                    "{$basisKey}_energy_rounding_decimal_places" => null,
                    "{$basisKey}_declared_energy_string" => null,
                    "{$basisKey}_declared_energy_numerator" => null,
                    "{$basisKey}_declared_energy_denominator" => null,
                    "{$basisKey}_energy_unit" => 'kcal',
                    "{$basisKey}_energy_is_complete" => false,
                    "{$basisKey}_energy_availability" => 'UNAVAILABLE',
                ],
                $nsqRule !== null ? $this->evidenceSnapshot($nsqRule, '', "{$basisKey}_energy_nsq_") : [],
                $roundingRule !== null ? $this->evidenceSnapshot($roundingRule, '', "{$basisKey}_energy_rounding_") : [],
            );

            if ($policyOk && $matrixOk && $allApplicableReady) {
                $exactRatio = $this->arithmetic->ratio($sum);
                $basisFields["{$basisKey}_exact_energy_numerator"] = $exactRatio['numerator'];
                $basisFields["{$basisKey}_exact_energy_denominator"] = $exactRatio['denominator'];

                $applied = $this->applyDeclarationRules(
                    exactValue: $sum,
                    skipNsqWhenExactZero: true,
                    unit: 'kcal',
                    profile: $profile,
                    nsqRule: $nsqRule,
                    roundingRule: $roundingRule,
                    causeScope: $causeScope,
                    causeKey: 'ENERGY',
                );

                array_push($basisCauses, ...$applied['causes']);
                $basisFields["{$basisKey}_energy_nsq_applied"] = $applied['nsq_applied'];
                $basisFields["{$basisKey}_energy_rounding_step_numerator"] = $applied['step']['numerator'] ?? null;
                $basisFields["{$basisKey}_energy_rounding_step_denominator"] = $applied['step']['denominator'] ?? null;
                $basisFields["{$basisKey}_energy_rounding_decimal_places"] = $applied['decimal_places'];
                $basisFields["{$basisKey}_declared_energy_string"] = $applied['declared_string'];
                $basisFields["{$basisKey}_declared_energy_numerator"] = $applied['declared_ratio']['numerator'] ?? null;
                $basisFields["{$basisKey}_declared_energy_denominator"] = $applied['declared_ratio']['denominator'] ?? null;
                $basisFields["{$basisKey}_energy_is_complete"] = $applied['complete'];
                $basisFields["{$basisKey}_energy_availability"] = $applied['availability'];

                if ($basis === 'PORTION' && $applied['complete']) {
                    $portionDeclared = [
                        'complete' => true,
                        'string' => $applied['declared_string'],
                        'numerator' => $applied['declared_ratio']['numerator'] ?? null,
                        'denominator' => $applied['declared_ratio']['denominator'] ?? null,
                        'unit' => 'kcal',
                    ];
                }
            }

            $rootFields = array_merge($rootFields, $basisFields);
            array_push($causes, ...$basisCauses);
        }

        return [
            'root_fields' => $rootFields,
            'component_rows' => $allEnergyComponents,
            'causes' => $causes,
            'portion_declared' => $portionDeclared,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $componentRows
     * @param  array{complete: bool, string: ?string, numerator: ?string, denominator: ?string, unit: string}  $portionEnergy
     * @return array{rows: array<int, array<string, mixed>>, causes: array<int, array<string, mixed>>}
     */
    private function percentageRows(
        ServingDailyValueCalculation $source,
        RegulatoryDeclarationProfile $profile,
        array $componentRows,
        array $portionEnergy,
    ): array {
        $componentsByMap = collect($componentRows)->keyBy('map_code');
        $roundingByVdr = $profile->roundingRules
            ->filter(static fn (RegulatoryRoundingRule $rule): bool => $rule->target_kind === 'DAILY_VALUE')
            ->keyBy('vdr_code');
        $policyOk = $this->gates->recalculationPolicyAuthorized($profile, $profile->recalculationPolicy);

        $rows = [];
        $causes = [];

        foreach ($source->percentages as $pct) {
            /** @var RegulatoryRoundingRule|null $roundingRule */
            $roundingRule = $roundingByVdr->get($pct->vdr_code);

            $baseRow = array_merge(
                [
                    'serving_daily_value_percentage_id' => $pct->getKey(),
                    'vdr_code' => $pct->vdr_code,
                    'component_name' => $pct->component_name,
                    'map_code' => $pct->map_code,
                    'vdr_technical_status' => $pct->technical_status,
                    'vdr_numerator' => $this->nullableString($pct->vdr_numerator),
                    'vdr_denominator' => $this->nullableString($pct->vdr_denominator),
                    'vdr_unit' => $pct->vdr_unit,
                    'vdr_source_reference' => $pct->vdr_source_reference,
                    'vdr_source_version' => $pct->vdr_source_version,
                    'source_derived_percentage_numerator' => $this->nullableString($pct->percentage_numerator),
                    'source_derived_percentage_denominator' => $this->nullableString($pct->percentage_denominator),
                    'source_derived_availability' => $pct->availability,
                    'numerator_identity' => null,
                    'input_declared_string' => null,
                    'input_declared_numerator' => null,
                    'input_declared_denominator' => null,
                    'unrounded_percentage_numerator' => null,
                    'unrounded_percentage_denominator' => null,
                    'rounding_rule_code' => $roundingRule?->rule_code,
                    'rounding_rule_status' => $roundingRule?->technical_status,
                    'rounding_tie_breaker' => $roundingRule?->tie_breaker,
                    'rounding_step_numerator' => null,
                    'rounding_step_denominator' => null,
                    'rounding_decimal_places' => null,
                    'declared_percentage_string' => null,
                    'declared_percentage_numerator' => null,
                    'declared_percentage_denominator' => null,
                    'is_complete' => false,
                    'availability' => 'UNAVAILABLE',
                ],
                $this->evidenceSnapshot($pct, 'vdr_', 'vdr_'),
                $roundingRule !== null ? $this->evidenceSnapshot($roundingRule, '', 'rounding_') : [],
            );

            if ($pct->technical_status === 'NO_NORMATIVE_VDR') {
                if ($this->gates->sourceNoNormativeVdrAuthorized($source, $pct)) {
                    $baseRow['availability'] = 'NO_NORMATIVE_VDR';
                } else {
                    $causes[] = $this->cause(
                        'DAILY_VALUE',
                        $pct->vdr_code,
                        'REGULATORY_BLOCK',
                        'VDR_EVIDENCE_INVALID',
                        $pct->vdr_source_reference,
                    );
                }

                $rows[] = $baseRow;

                continue;
            }

            $pctCauses = [];

            if (! $policyOk) {
                $pctCauses[] = $this->cause(
                    'DAILY_VALUE',
                    $pct->vdr_code,
                    'POLICY_BLOCK',
                    'RECALCULATION_POLICY_BLOCKED',
                    $profile->recalculationPolicy?->source_reference,
                );
            }

            $vdrAuthorized = $this->gates->sourceDailyValueAuthorized($source, $pct);
            if (! $vdrAuthorized) {
                $pctCauses[] = $this->cause(
                    'DAILY_VALUE',
                    $pct->vdr_code,
                    'REGULATORY_BLOCK',
                    'VDR_BLOCKED',
                    $pct->vdr_source_reference,
                );
            }

            if ($roundingRule === null) {
                $pctCauses[] = $this->cause('DAILY_VALUE', $pct->vdr_code, 'REGULATORY_BLOCK', 'ROUNDING_RULE_MISSING');
            } elseif (! $this->gates->roundingRuleAuthorized($profile, $roundingRule) || $roundingRule->unit !== '%') {
                $pctCauses[] = $this->cause(
                    'DAILY_VALUE',
                    $pct->vdr_code,
                    'REGULATORY_BLOCK',
                    'ROUNDING_RULE_BLOCKED',
                    $roundingRule->source_reference,
                );
            }

            $sourceMode = $roundingRule?->vdr_numerator_source;
            $inputComplete = false;
            $inputString = null;
            $inputNum = null;
            $inputDen = null;
            $inputUnit = null;

            if ($sourceMode === 'DECLARED_COMPONENT') {
                $baseRow['numerator_identity'] = $pct->map_code !== null ? "DECLARED_COMPONENT:{$pct->map_code}" : null;
                $compRow = $pct->map_code !== null ? $componentsByMap->get($pct->map_code) : null;
                $inputComplete = (bool) ($compRow['portion_is_complete'] ?? false);
                $inputString = $compRow['portion_declared_string'] ?? null;
                $inputNum = $compRow['portion_declared_numerator'] ?? null;
                $inputDen = $compRow['portion_declared_denominator'] ?? null;
                $inputUnit = $compRow['regulatory_unit'] ?? null;
            } elseif ($sourceMode === 'DECLARED_PORTION_ENERGY') {
                $baseRow['numerator_identity'] = 'DECLARED_PORTION_ENERGY';
                $inputComplete = $portionEnergy['complete'];
                $inputString = $portionEnergy['string'];
                $inputNum = $portionEnergy['numerator'];
                $inputDen = $portionEnergy['denominator'];
                $inputUnit = $portionEnergy['unit'];
            }

            if (
                $sourceMode !== null
                && (! $inputComplete || $inputString === null || $inputNum === null || $inputDen === null || $inputUnit !== $pct->vdr_unit)
            ) {
                $pctCauses[] = $this->cause(
                    'DAILY_VALUE',
                    $pct->vdr_code,
                    'INCOMPLETE_NUTRITION',
                    'VD_INPUT_UNAVAILABLE',
                    $pct->vdr_source_reference,
                );
            }

            if ($pctCauses !== [] || $roundingRule === null || $sourceMode === null) {
                array_push($causes, ...$pctCauses);
                $rows[] = $baseRow;

                continue;
            }

            $baseRow['input_declared_string'] = $inputString;
            $baseRow['input_declared_numerator'] = $inputNum;
            $baseRow['input_declared_denominator'] = $inputDen;

            $unrounded = $this->arithmetic->percentage(
                (string) $inputNum,
                (string) $inputDen,
                (string) $pct->vdr_numerator,
                (string) $pct->vdr_denominator,
            );
            $unroundedRatio = $this->arithmetic->ratio($unrounded);
            $baseRow['unrounded_percentage_numerator'] = $unroundedRatio['numerator'];
            $baseRow['unrounded_percentage_denominator'] = $unroundedRatio['denominator'];

            $range = $this->gates->resolveRange($roundingRule, $unrounded);
            if ($range === null) {
                $causes[] = $this->cause(
                    'DAILY_VALUE',
                    $pct->vdr_code,
                    'ROUNDING_RANGE_MISMATCH',
                    'ROUNDING_RANGE_MISMATCH',
                    $roundingRule->source_reference,
                );
                $rows[] = $baseRow;

                continue;
            }

            $step = $this->arithmetic->fromRatio((string) $range->step_numerator, (string) $range->step_denominator);
            $stepRatio = $this->arithmetic->ratio($step);

            try {
                $rounded = $this->arithmetic->roundToStepHalfUp($unrounded, $step);
                $display = $this->arithmetic->formatDecimal($rounded, (int) $range->decimal_places);
            } catch (RoundingNecessaryException) {
                $causes[] = $this->cause(
                    'DAILY_VALUE',
                    $pct->vdr_code,
                    'ROUNDING_RANGE_MISMATCH',
                    'ROUNDING_SCALE_MISMATCH',
                    $roundingRule->source_reference,
                );
                $rows[] = $baseRow;

                continue;
            }

            $declaredRatio = $this->arithmetic->ratio($rounded);
            $baseRow['rounding_step_numerator'] = $stepRatio['numerator'];
            $baseRow['rounding_step_denominator'] = $stepRatio['denominator'];
            $baseRow['rounding_decimal_places'] = (int) $range->decimal_places;
            $baseRow['declared_percentage_string'] = $display;
            $baseRow['declared_percentage_numerator'] = $declaredRatio['numerator'];
            $baseRow['declared_percentage_denominator'] = $declaredRatio['denominator'];
            $baseRow['is_complete'] = true;
            $baseRow['availability'] = 'AVAILABLE';

            $rows[] = $baseRow;
        }

        return ['rows' => $rows, 'causes' => $causes];
    }

    /**
     * @return array{
     *   nsq_applied: bool,
     *   step: ?array{numerator: string, denominator: string},
     *   decimal_places: ?int,
     *   declared_string: ?string,
     *   declared_ratio: ?array{numerator: string, denominator: string},
     *   complete: bool,
     *   availability: string,
     *   causes: array<int, array<string, mixed>>
     * }
     */
    private function applyDeclarationRules(
        BigRational $exactValue,
        bool $skipNsqWhenExactZero,
        string $unit,
        RegulatoryDeclarationProfile $profile,
        ?RegulatoryNonSignificantRule $nsqRule,
        ?RegulatoryRoundingRule $roundingRule,
        string $causeScope,
        string $causeKey,
    ): array {
        $isExactZero = $this->arithmetic->compare($exactValue, $this->arithmetic->fromDecimal('0')) === 0;

        if ($nsqRule !== null) {
            $nsqBlocked = match ($nsqRule->technical_status) {
                'AUTHORIZED' => ! $this->gates->nonSignificantRuleAuthorized($profile, $nsqRule),
                'NOT_APPLICABLE' => ! $this->gates->nonSignificantRuleNotApplicableValid($profile, $nsqRule),
                default => true,
            };

            if ($nsqBlocked) {
                return [
                    'nsq_applied' => false,
                    'step' => null,
                    'decimal_places' => null,
                    'declared_string' => null,
                    'declared_ratio' => null,
                    'complete' => false,
                    'availability' => 'UNAVAILABLE',
                    'causes' => [$this->cause($causeScope, $causeKey, 'REGULATORY_BLOCK', 'NSQ_RULE_BLOCKED', $nsqRule->source_reference)],
                ];
            }

            if ($nsqRule->technical_status === 'AUTHORIZED' && $nsqRule->threshold_unit !== $unit) {
                return [
                    'nsq_applied' => false,
                    'step' => null,
                    'decimal_places' => null,
                    'declared_string' => null,
                    'declared_ratio' => null,
                    'complete' => false,
                    'availability' => 'UNAVAILABLE',
                    'causes' => [$this->cause($causeScope, $causeKey, 'REGULATORY_BLOCK', 'NSQ_UNIT_MISMATCH', $nsqRule->source_reference)],
                ];
            }

            if (
                ! ($skipNsqWhenExactZero && $isExactZero)
                && $nsqRule->technical_status === 'AUTHORIZED'
                && $this->valueMatchesNsq($exactValue, $nsqRule)
            ) {
                return [
                    'nsq_applied' => true,
                    'step' => null,
                    'decimal_places' => null,
                    'declared_string' => $nsqRule->declared_string,
                    'declared_ratio' => $this->canonicalRatio(
                        (string) $nsqRule->declared_numerator,
                        (string) $nsqRule->declared_denominator,
                    ),
                    'complete' => true,
                    'availability' => 'AVAILABLE',
                    'causes' => [],
                ];
            }
        }

        if ($roundingRule === null) {
            return [
                'nsq_applied' => false,
                'step' => null,
                'decimal_places' => null,
                'declared_string' => null,
                'declared_ratio' => null,
                'complete' => false,
                'availability' => 'UNAVAILABLE',
                'causes' => [$this->cause($causeScope, $causeKey, 'REGULATORY_BLOCK', 'ROUNDING_RULE_MISSING')],
            ];
        }

        if (! $this->gates->roundingRuleAuthorized($profile, $roundingRule) || $roundingRule->unit !== $unit) {
            return [
                'nsq_applied' => false,
                'step' => null,
                'decimal_places' => null,
                'declared_string' => null,
                'declared_ratio' => null,
                'complete' => false,
                'availability' => 'UNAVAILABLE',
                'causes' => [$this->cause($causeScope, $causeKey, 'REGULATORY_BLOCK', 'ROUNDING_RULE_BLOCKED', $roundingRule->source_reference)],
            ];
        }

        $range = $this->gates->resolveRange($roundingRule, $exactValue);
        if ($range === null) {
            return [
                'nsq_applied' => false,
                'step' => null,
                'decimal_places' => null,
                'declared_string' => null,
                'declared_ratio' => null,
                'complete' => false,
                'availability' => 'UNAVAILABLE',
                'causes' => [$this->cause($causeScope, $causeKey, 'ROUNDING_RANGE_MISMATCH', 'ROUNDING_RANGE_MISMATCH', $roundingRule->source_reference)],
            ];
        }

        $step = $this->arithmetic->fromRatio((string) $range->step_numerator, (string) $range->step_denominator);

        try {
            $rounded = $this->arithmetic->roundToStepHalfUp($exactValue, $step);
            $display = $this->arithmetic->formatDecimal($rounded, (int) $range->decimal_places);
        } catch (RoundingNecessaryException) {
            return [
                'nsq_applied' => false,
                'step' => null,
                'decimal_places' => null,
                'declared_string' => null,
                'declared_ratio' => null,
                'complete' => false,
                'availability' => 'UNAVAILABLE',
                'causes' => [$this->cause($causeScope, $causeKey, 'ROUNDING_RANGE_MISMATCH', 'ROUNDING_SCALE_MISMATCH', $roundingRule->source_reference)],
            ];
        }

        return [
            'nsq_applied' => false,
            'step' => $this->arithmetic->ratio($step),
            'decimal_places' => (int) $range->decimal_places,
            'declared_string' => $display,
            'declared_ratio' => $this->arithmetic->ratio($rounded),
            'complete' => true,
            'availability' => 'AVAILABLE',
            'causes' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function energyMatrixCauses(ServingDailyValueCalculation $source, string $causeScope): array
    {
        if ($source->energyComponents->isEmpty()) {
            return [$this->cause($causeScope, 'ENERGY', 'MISSING_SOURCE', 'ENERGY_MATRIX_MISSING')];
        }

        $causes = [];
        foreach ($source->energyComponents as $ene) {
            if ($ene->applicability_status === 'UNKNOWN') {
                $causes[] = $this->cause($causeScope, $ene->ene_code, 'UNKNOWN_APPLICABILITY', 'ENERGY_APPLICABILITY_UNKNOWN', $ene->source_reference);
            } elseif ($ene->overlap_group !== null && $ene->overlap_rule_status !== 'AUTHORIZED') {
                $causes[] = $this->cause($causeScope, $ene->ene_code, 'DOUBLE_COUNTING_RISK', 'ENERGY_OVERLAP_UNRESOLVED', $ene->source_reference);
            } elseif (! $this->gates->sourceEnergyComponentAuthorized($source, $ene)) {
                $causes[] = $this->cause($causeScope, $ene->ene_code, 'REGULATORY_BLOCK', 'ENERGY_COMPONENT_BLOCKED', $ene->source_reference);
            }
        }

        if ($causes === []) {
            $causes[] = $this->cause($causeScope, 'ENERGY', 'REGULATORY_BLOCK', 'ENERGY_MATRIX_BLOCKED');
        }

        return $causes;
    }

    private function valueMatchesNsq(BigRational $value, RegulatoryNonSignificantRule $rule): bool
    {
        $threshold = $this->arithmetic->fromRatio(
            (string) $rule->threshold_numerator,
            (string) $rule->threshold_denominator,
        );
        $cmp = $this->arithmetic->compare($value, $threshold);

        return match ($rule->comparison_operator) {
            'LTE' => $cmp <= 0,
            'LT' => $cmp < 0,
            default => false,
        };
    }

    /**
     * @return array{0: array{numerator: string, denominator: string}|null, 1: array{numerator: string, denominator: string}|null}
     */
    private function resolveReference100Ratios(
        ?string $num,
        ?string $den,
        ServingDailyValueRegulatoryResult $result,
        bool $mapAuthorized,
        bool $conversionAuthorized,
    ): array {
        $sourceRatio = $this->canonicalRatio($num, $den);
        if ($sourceRatio === null || ! $mapAuthorized || ! $conversionAuthorized) {
            return [$sourceRatio, null];
        }

        $value = $this->arithmetic->fromRatio($sourceRatio['numerator'], $sourceRatio['denominator']);
        if ($result->conversion_status === 'AUTHORIZED') {
            $factor = $this->arithmetic->fromRatio(
                (string) $result->conversion_numerator,
                (string) $result->conversion_denominator,
            );
            $value = $value->multipliedBy($factor);
        }

        return [$sourceRatio, $this->arithmetic->ratio($value)];
    }

    /**
     * @param  Collection<int, RegulatoryNonSignificantRule>  $rules
     * @return Collection<string, RegulatoryNonSignificantRule>
     */
    private function indexComponentNsqRules(Collection $rules): Collection
    {
        return $rules
            ->filter(static fn (RegulatoryNonSignificantRule $rule): bool => $rule->target_kind === 'COMPONENT')
            ->keyBy(static fn (RegulatoryNonSignificantRule $rule): string => "{$rule->map_code}:{$rule->basis}");
    }

    /**
     * @param  Collection<int, RegulatoryRoundingRule>  $rules
     * @return Collection<string, RegulatoryRoundingRule>
     */
    private function indexComponentRoundingRules(Collection $rules): Collection
    {
        return $rules
            ->filter(static fn (RegulatoryRoundingRule $rule): bool => $rule->target_kind === 'COMPONENT')
            ->keyBy(static fn (RegulatoryRoundingRule $rule): string => "{$rule->map_code}:{$rule->basis}");
    }

    /**
     * @return array<int, string>
     */
    private function orderedQualitativeStates(?ServingDailyValueNutrient $svNutrient): array
    {
        if ($svNutrient === null) {
            return [];
        }

        $present = $svNutrient->states->pluck('state')->all();

        return array_values(array_intersect(self::QUALITATIVE_STATE_ORDER, $present));
    }

    /**
     * @return array{numerator: string, denominator: string}|null
     */
    private function canonicalRatio(?string $numerator, ?string $denominator): ?array
    {
        if ($numerator === null || $denominator === null) {
            return null;
        }

        return $this->arithmetic->ratio($this->arithmetic->fromRatio($numerator, $denominator));
    }

    /**
     * @return array<string, mixed>
     */
    private function evidenceSnapshot(object $record, string $sourceStem, string $targetStem): array
    {
        $fields = [];
        foreach (['reference', 'source', 'version', 'scope', 'kind'] as $suffix) {
            $fields["{$targetStem}evidence_{$suffix}"] = $record->{"{$sourceStem}evidence_{$suffix}"} ?? null;
        }
        $fields["{$targetStem}validated_by"] = $record->{"{$sourceStem}validated_by"} ?? null;
        $fields["{$targetStem}validated_on"] = $record->{"{$sourceStem}validated_on"} ?? null;

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function cause(
        string $scope,
        string $scopeKey,
        string $category,
        string $code,
        ?string $sourceReference = null,
        ?string $detail = null,
    ): array {
        return [
            'scope' => $scope,
            'scope_key' => $scopeKey,
            'category' => $category,
            'code' => $code,
            'detail' => $detail,
            'source_reference' => $sourceReference,
        ];
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
