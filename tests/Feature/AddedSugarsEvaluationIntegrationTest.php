<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Food;
use App\Models\NutritionCalculation;
use App\Models\NutritionCalculationIngredient;
use App\Models\PortionAuthorization;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RegulatoryDailyValue;
use App\Models\RegulatoryDeclarationProfile;
use App\Models\RegulatoryDeclarationRequirement;
use App\Models\RegulatoryNonSignificantRule;
use App\Models\RegulatoryNutrientMapping;
use App\Models\RegulatoryProfile;
use App\Models\RegulatoryRecalculationPolicy;
use App\Models\RegulatoryRoundingRule;
use App\Models\RegulatoryRoundingRuleRange;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AddedSugarsEvaluationIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_feature_004_rejects_mismatched_nutrition_calculation_and_consumes_eligible_and_blocked_evaluations_through_feature_005(): void
    {
        [$calc1, $food1, $recipe1] = $this->seedSingleIngredientCalc('500', 'g', '200');
        [$calc2] = $this->seedSingleIngredientCalc('500', 'g', '200');

        $formResp = $this->postJson("/api/recipes/{$recipe1->getKey()}/added-sugars-formulations", [
            'normative_source' => 'RDC nº 429/2020',
            'normative_version' => '2020-ret-2022',
            'registered_by' => 'Nutricionista',
            'registered_on' => '2026-11-23',
            'ingredients' => [[
                'position' => 1,
                'food_id' => $food1->getKey(),
                'ingredient_type' => 'COMPOUND_INDUSTRIALIZED',
                'classification' => 'INCLUDED',
                'amount_basis' => 'PER_100G',
                'amount_value' => '50',
                'amount_unit' => 'g',
                'evidences' => [[
                    'source_type' => 'MANUFACTURER_SPEC',
                    'source_name' => 'Fabricante X',
                    'document_reference' => 'FT-50',
                    'reported_amount_basis' => 'PER_100G',
                    'reported_amount_value' => '50',
                    'reported_amount_unit' => 'g',
                    'only_total_sugars_reported' => false,
                    'recorded_by' => 'Nutricionista',
                    'recorded_on' => '2026-11-23',
                ]],
            ]],
        ])->assertCreated();

        $versionId = $formResp->json('data.current_version.id');

        // Pending evaluation (not yet authorized)
        $pendingEvalId = $this->postJson("/api/nutrition-calculations/{$calc1->getKey()}/added-sugars-evaluations", [
            'formulation_version_id' => $versionId,
        ])->assertCreated()->json('data.id');

        // Authorize v1
        $this->postJson("/api/added-sugars-formulation-versions/{$versionId}/validations", [
            'decision_status' => 'AUTHORIZED',
            'normative_source' => 'RDC nº 429/2020',
            'normative_version' => '2020-ret-2022',
            'evidence_type' => 'TEST_FIXTURE',
            'evidence_reference' => 'FIX-AUTH',
            'evidence_source' => 'Suite',
            'evidence_version' => '1.0',
            'evidence_scope' => "recipe:{$recipe1->getKey()}/added-sugars-formulation:v1",
            'validated_by' => 'Tester',
            'validated_on' => '2026-11-23',
        ])->assertCreated();

        // Eligible evaluation: total = 200 * 50 / 100 = 100 g; ref100 = 100 * 100 / 500 = 20 g
        $eligibleEvalId = $this->postJson("/api/nutrition-calculations/{$calc1->getKey()}/added-sugars-evaluations", [
            'formulation_version_id' => $versionId,
        ])->assertCreated()->json('data.id');

        [$portionAuth1, $profile] = $this->seedAuthorizedProfileAndPortion($calc1, '20', 'g');
        [$portionAuth2] = $this->seedAuthorizedProfileAndPortion($calc2, '20', 'g', $profile);

        // 1. Mismatched NutritionCalculation rejected with 422
        $this->postJson("/api/nutrition-calculations/{$calc2->getKey()}/serving-daily-value-calculations", [
            'portion' => ['value' => '20', 'unit' => 'g', 'household_measure' => '1 colher de sopa'],
            'portion_authorization_id' => $portionAuth2->getKey(),
            'regulatory_profile_id' => $profile->getKey(),
            'added_sugars_evaluation_id' => $eligibleEvalId,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['added_sugars_evaluation_id']);

        // 2. Pending evaluation -> MAP-004 & VDR-004 remain UNAVAILABLE (null) with inherited causes
        $svBlocked = $this->postJson("/api/nutrition-calculations/{$calc1->getKey()}/serving-daily-value-calculations", [
            'portion' => ['value' => '20', 'unit' => 'g', 'household_measure' => '1 colher de sopa'],
            'portion_authorization_id' => $portionAuth1->getKey(),
            'regulatory_profile_id' => $profile->getKey(),
            'added_sugars_evaluation_id' => $pendingEvalId,
        ])->assertCreated();

        $svBlocked->assertJsonPath('data.added_sugars_evaluation.is_regulatory_eligible', false)
            ->assertJsonPath('data.regulatory_results.0.map_code', 'MAP-004')
            ->assertJsonPath('data.regulatory_results.0.availability', 'UNAVAILABLE')
            ->assertJsonPath('data.regulatory_results.0.value', null)
            ->assertJsonPath('data.regulatory_results.0.causes.0.code', 'FORMULATION_VALIDATION_PENDING')
            ->assertJsonPath('data.daily_values.0.vdr_code', 'VDR-004')
            ->assertJsonPath('data.daily_values.0.availability', 'UNAVAILABLE')
            ->assertJsonPath('data.daily_values.0.value', null);

        // 3. Eligible evaluation -> MAP-004 portion = 100 * 20 / 500 = 4 g; %VD = 4 * 100 / 50 = 8%
        $svEligible = $this->postJson("/api/nutrition-calculations/{$calc1->getKey()}/serving-daily-value-calculations", [
            'portion' => ['value' => '20', 'unit' => 'g', 'household_measure' => '1 colher de sopa'],
            'portion_authorization_id' => $portionAuth1->getKey(),
            'regulatory_profile_id' => $profile->getKey(),
            'added_sugars_evaluation_id' => $eligibleEvalId,
        ])->assertCreated();

        $svEligible->assertJsonPath('data.added_sugars_evaluation.id', $eligibleEvalId)
            ->assertJsonPath('data.added_sugars_evaluation.is_regulatory_eligible', true)
            ->assertJsonPath('data.regulatory_results.0.map_code', 'MAP-004')
            ->assertJsonPath('data.regulatory_results.0.value_source_type', 'ADDED_SUGARS_FORMULATION')
            ->assertJsonPath('data.regulatory_results.0.availability', 'AVAILABLE')
            ->assertJsonPath('data.regulatory_results.0.complete', true)
            ->assertJsonPath('data.regulatory_results.0.value.numerator', '4')
            ->assertJsonPath('data.regulatory_results.0.value.denominator', '1')
            ->assertJsonPath('data.regulatory_results.0.source_reference_100.numerator', '20')
            ->assertJsonPath('data.regulatory_results.0.source_reference_100.denominator', '1')
            ->assertJsonPath('data.daily_values.0.vdr_code', 'VDR-004')
            ->assertJsonPath('data.daily_values.0.availability', 'AVAILABLE')
            ->assertJsonPath('data.daily_values.0.value.numerator', '8')
            ->assertJsonPath('data.daily_values.0.value.denominator', '1');

        // 4. Propagates seamlessly into Feature 005 (DeclaredNutritionCalculation)
        $declProfile = $this->seedAuthorizedDeclarationProfile($profile, $calc1);
        $svId = $svEligible->json('data.id');

        $declared = $this->postJson("/api/serving-daily-value-calculations/{$svId}/declared-nutrition-calculations", [
            'regulatory_declaration_profile_id' => $declProfile->getKey(),
        ])->assertCreated();

        $declared->assertJsonPath('data.components.0.map_code', 'MAP-004')
            ->assertJsonPath('data.components.0.reference_100.declared.display', '20')
            ->assertJsonPath('data.components.0.portion.declared.display', '4')
            ->assertJsonPath('data.daily_values.0.vdr_code', 'VDR-004')
            ->assertJsonPath('data.daily_values.0.declared_percentage.display', '8');
    }

    /** @return array{0: NutritionCalculation, 1: Food, 2: Recipe} */
    private function seedSingleIngredientCalc(string $yieldValue, string $yieldUnit, string $qty): array
    {
        $food = Food::query()->create([
            'name' => 'Xarope integração '.uniqid(),
            'category' => 'Açúcares',
            'source' => 'MANUAL',
            'source_code' => 'INT-'.uniqid(),
        ]);

        $recipe = Recipe::query()->create([
            'name' => 'Produto integração '.uniqid(),
            'yield_value' => $yieldValue,
            'yield_unit' => $yieldUnit,
        ]);

        $ri = RecipeIngredient::query()->create([
            'recipe_id' => $recipe->getKey(),
            'food_id' => $food->getKey(),
            'quantity' => $qty,
            'unit' => 'g',
            'position' => 1,
        ]);

        $calc = NutritionCalculation::query()->create([
            'recipe_id' => $recipe->getKey(),
            'rules_version' => 'nutrition-calculation-v1',
            'yield_value' => $yieldValue,
            'yield_unit' => $yieldUnit,
        ]);

        NutritionCalculationIngredient::query()->create([
            'nutrition_calculation_id' => $calc->getKey(),
            'recipe_ingredient_id' => $ri->getKey(),
            'food_id' => $food->getKey(),
            'food_source' => $food->source,
            'food_source_code' => $food->source_code,
            'quantity' => $qty,
            'unit' => 'g',
            'position' => 1,
        ]);

        return [$calc, $food, $recipe];
    }

    /** @return array{0: PortionAuthorization, 1: RegulatoryProfile} */
    private function seedAuthorizedProfileAndPortion(
        NutritionCalculation $calc,
        string $portionValue,
        string $portionUnit,
        ?RegulatoryProfile $existingProfile = null,
    ): array {
        $portionAuth = PortionAuthorization::query()->create([
            'nutrition_calculation_id' => $calc->getKey(),
            'portion_value' => $portionValue,
            'portion_unit' => $portionUnit,
            'household_measure_mode' => 'PROVIDED',
            'household_measure' => '1 colher de sopa',
            'reference_code' => 'PORT-007',
            'reference_version' => '1.0',
            'reference_source' => 'IN 75/2020 Anexo V',
            'technical_status' => 'AUTHORIZED',
            'evidence_reference' => 'EV-PORT-1',
            'evidence_source' => 'Suite',
            'evidence_version' => '1.0',
            'evidence_scope' => "nutrition-calculation:{$calc->getKey()}/portion:PORT-007@1.0",
            'evidence_kind' => 'TEST_FIXTURE',
            'validated_by' => 'Tester',
            'validated_on' => '2026-11-23',
        ]);

        if ($existingProfile instanceof RegulatoryProfile) {
            return [$portionAuth, $existingProfile];
        }

        $code = 'PROF-007-'.uniqid();
        $profile = RegulatoryProfile::query()->create([
            'code' => $code,
            'version' => '1.0',
            'title' => 'Perfil Teste 007',
            'technical_status' => 'AUTHORIZED',
            'normative_source' => 'RDC nº 429/2020 e IN nº 75/2020',
            'normative_version' => '2020-ret-2022',
            'consulted_on' => '2026-11-23',
            'evidence_reference' => 'EV-PROF-1',
            'evidence_source' => 'Suite',
            'evidence_version' => '1.0',
            'evidence_scope' => "profile:{$code}@1.0",
            'evidence_kind' => 'TEST_FIXTURE',
            'validated_by' => 'Tester',
            'validated_on' => '2026-11-23',
        ]);

        $map004 = RegulatoryNutrientMapping::query()->create([
            'profile_id' => $profile->getKey(),
            'map_code' => 'MAP-004',
            'component_name' => 'Açúcares adicionados',
            'nutrient_id' => null,
            'source_unit' => null,
            'regulatory_unit' => 'g',
            'technical_status' => 'UNAVAILABLE',
            'source_reference' => 'RDC nº 429/2020, art. 5º, IV',
            'conversion_status' => 'BLOCKED',
        ]);

        RegulatoryDailyValue::query()->create([
            'profile_id' => $profile->getKey(),
            'mapping_id' => $map004->getKey(),
            'vdr_code' => 'VDR-004',
            'component_name' => 'Açúcares adicionados',
            'vdr_numerator' => '50',
            'vdr_denominator' => '1',
            'unit' => 'g',
            'technical_status' => 'AUTHORIZED',
            'source_reference' => 'IN nº 75/2020, Anexo II',
            'evidence_reference' => 'EV-VDR-004',
            'evidence_source' => 'Suite',
            'evidence_version' => '1.0',
            'evidence_scope' => "profile:{$code}@1.0/vdr:VDR-004/map:MAP-004",
            'evidence_kind' => 'TEST_FIXTURE',
            'validated_by' => 'Tester',
            'validated_on' => '2026-11-23',
        ]);

        return [$portionAuth, $profile];
    }

    private function seedAuthorizedDeclarationProfile(
        RegulatoryProfile $profile,
        NutritionCalculation $calc,
    ): RegulatoryDeclarationProfile {
        $declCode = 'DECL-007-'.uniqid();
        $scope = "declaration-profile:{$declCode}@1.0";

        $declProfile = RegulatoryDeclarationProfile::query()->create([
            'code' => $declCode,
            'version' => '1.0',
            'title' => 'Declaração 007',
            'regulatory_profile_code' => $profile->code,
            'regulatory_profile_version' => $profile->version,
            'normative_source' => $profile->normative_source,
            'normative_version' => $profile->normative_version,
            'consulted_on' => '2026-11-23',
            'scope_recipe_id' => $calc->recipe_id,
            'scope_nutrition_calculation_id' => $calc->getKey(),
            'technical_status' => 'AUTHORIZED',
            'evidence_reference' => 'EV-DECL',
            'evidence_source' => 'Suite',
            'evidence_version' => '1.0',
            'evidence_scope' => $scope,
            'evidence_kind' => 'TEST_FIXTURE',
            'validated_by' => 'Tester',
            'validated_on' => '2026-11-23',
        ]);

        RegulatoryRecalculationPolicy::query()->create([
            'declaration_profile_id' => $declProfile->getKey(),
            'policy_code' => 'POL-007',
            'normative_order_mode' => 'DECLARE_COMPONENTS_THEN_ENERGY_AND_VD',
            'energy_reference_100_input_source' => 'DECLARED_REFERENCE_100',
            'energy_portion_input_source' => 'DECLARED_PORTION',
            'daily_value_input_source' => 'DECLARED_PORTION_AND_DECLARED_ENERGY',
            'requires_complete_energy_inputs' => true,
            'technical_status' => 'AUTHORIZED',
            'source_reference' => 'RDC 429/2020',
            'evidence_reference' => 'EV-POL',
            'evidence_source' => 'Suite',
            'evidence_version' => '1.0',
            'evidence_scope' => "{$scope}/policy:POL-007",
            'evidence_kind' => 'TEST_FIXTURE',
            'validated_by' => 'Tester',
            'validated_on' => '2026-11-23',
        ]);

        foreach (['REFERENCE_100', 'PORTION'] as $basis) {
            $nsqCode = "NSQ-MAP004-{$basis}";
            RegulatoryNonSignificantRule::query()->create([
                'declaration_profile_id' => $declProfile->getKey(),
                'rule_code' => $nsqCode,
                'target_kind' => 'COMPONENT',
                'map_code' => 'MAP-004',
                'basis' => $basis,
                'comparison_operator' => 'LTE',
                'threshold_numerator' => '1',
                'threshold_denominator' => '2',
                'threshold_unit' => 'g',
                'declared_string' => '0',
                'declared_numerator' => '0',
                'declared_denominator' => '1',
                'technical_status' => 'AUTHORIZED',
                'source_reference' => 'IN 75/2020 Anexo IV',
                'evidence_reference' => 'EV-NSQ',
                'evidence_source' => 'Suite',
                'evidence_version' => '1.0',
                'evidence_scope' => "{$scope}/nsq:{$nsqCode}",
                'evidence_kind' => 'TEST_FIXTURE',
                'validated_by' => 'Tester',
                'validated_on' => '2026-11-23',
            ]);

            $rndCode = "RND-MAP004-{$basis}";
            $rule = RegulatoryRoundingRule::query()->create([
                'declaration_profile_id' => $declProfile->getKey(),
                'rule_code' => $rndCode,
                'target_kind' => 'COMPONENT',
                'map_code' => 'MAP-004',
                'basis' => $basis,
                'unit' => 'g',
                'tie_breaker' => 'HALF_UP',
                'technical_status' => 'AUTHORIZED',
                'source_reference' => 'IN 75/2020 Anexo III',
                'evidence_reference' => 'EV-RND',
                'evidence_source' => 'Suite',
                'evidence_version' => '1.0',
                'evidence_scope' => "{$scope}/rounding:{$rndCode}",
                'evidence_kind' => 'TEST_FIXTURE',
                'validated_by' => 'Tester',
                'validated_on' => '2026-11-23',
            ]);

            RegulatoryRoundingRuleRange::query()->create([
                'rounding_rule_id' => $rule->getKey(),
                'range_order' => 1,
                'min_numerator' => '0',
                'min_denominator' => '1',
                'min_inclusive' => true,
                'max_numerator' => null,
                'max_denominator' => null,
                'max_inclusive' => false,
                'decimal_places' => 0,
                'step_numerator' => '1',
                'step_denominator' => '1',
            ]);
        }

        $vdRule = RegulatoryRoundingRule::query()->create([
            'declaration_profile_id' => $declProfile->getKey(),
            'rule_code' => 'RND-VDR-004',
            'target_kind' => 'DAILY_VALUE',
            'vdr_code' => 'VDR-004',
            'vdr_numerator_source' => 'DECLARED_COMPONENT',
            'basis' => 'PERCENT_VD',
            'unit' => '%',
            'tie_breaker' => 'HALF_UP',
            'technical_status' => 'AUTHORIZED',
            'source_reference' => 'IN 75/2020 Anexo III',
            'evidence_reference' => 'EV-RND-VD',
            'evidence_source' => 'Suite',
            'evidence_version' => '1.0',
            'evidence_scope' => "{$scope}/rounding:RND-VDR-004",
            'evidence_kind' => 'TEST_FIXTURE',
            'validated_by' => 'Tester',
            'validated_on' => '2026-11-23',
        ]);

        RegulatoryRoundingRuleRange::query()->create([
            'rounding_rule_id' => $vdRule->getKey(),
            'range_order' => 1,
            'min_numerator' => '0',
            'min_denominator' => '1',
            'min_inclusive' => true,
            'max_numerator' => null,
            'max_denominator' => null,
            'max_inclusive' => false,
            'decimal_places' => 0,
            'step_numerator' => '1',
            'step_denominator' => '1',
        ]);

        RegulatoryDeclarationRequirement::query()->create([
            'declaration_profile_id' => $declProfile->getKey(),
            'requirement_code' => 'REQ-MAP-004',
            'target_kind' => 'COMPONENT',
            'map_code' => 'MAP-004',
            'requirement_level' => 'MANDATORY',
            'applicability_status' => 'APPLICABLE',
            'technical_status' => 'AUTHORIZED',
            'source_reference' => 'RDC 429/2020 Art. 5º',
            'evidence_reference' => 'EV-REQ',
            'evidence_source' => 'Suite',
            'evidence_version' => '1.0',
            'evidence_scope' => "{$scope}/requirement:REQ-MAP-004",
            'evidence_kind' => 'TEST_FIXTURE',
            'validated_by' => 'Tester',
            'validated_on' => '2026-11-23',
        ]);

        return $declProfile;
    }
}
