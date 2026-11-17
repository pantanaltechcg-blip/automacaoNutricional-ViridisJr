<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DeclaredNutritionCalculation;
use App\Models\NutritionCalculation;
use App\Models\PortionAuthorization;
use App\Models\Recipe;
use App\Models\RegulatoryDeclarationProfile;
use App\Models\RegulatoryNonSignificantRule;
use App\Models\RegulatoryProfile;
use App\Models\RegulatoryRoundingRule;
use App\Models\RegulatoryRoundingRuleRange;
use App\Models\ServingDailyValueCalculation;
use App\Services\DeclaredValuesRegulatoryGateService;
use App\Services\NutritionCalculationArithmetic;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DeclaredNutritionRegulatoryGateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_post_rejects_with_422_and_persists_nothing_when_declaration_profile_diverges_from_source(): void
    {
        $source004 = $this->source004Calculation();
        $baseCount = DeclaredNutritionCalculation::query()->count();

        $divergences = [
            ['regulatory_profile_code' => 'OTHER-CODE'],
            ['regulatory_profile_version' => 'other-version'],
            ['normative_source' => 'OTHER-NORMATIVE'],
            ['normative_version' => 'other-normative-version'],
            ['scope_recipe_id' => $source004->source_recipe_id + 999],
            ['scope_nutrition_calculation_id' => $source004->nutrition_calculation_id + 999],
        ];

        foreach ($divergences as $override) {
            $profile = $this->createDeclarationProfile($source004, $override);

            $this->postJson("/api/serving-daily-value-calculations/{$source004->id}/declared-nutrition-calculations", [
                'regulatory_declaration_profile_id' => $profile->id,
            ])->assertStatus(422)
                ->assertJsonValidationErrors(['regulatory_declaration_profile_id']);
        }

        $this->assertSame($baseCount, DeclaredNutritionCalculation::query()->count());
    }

    public function test_test_fixture_evidence_never_authorizes_outside_testing(): void
    {
        $source004 = $this->source004Calculation();
        $profile = $this->createDeclarationProfile($source004);
        $nsq = $this->createNsqRule($profile);
        $rounding = $this->createRoundingRuleWithRanges($profile);
        $gate = app(DeclaredValuesRegulatoryGateService::class);

        $this->assertTrue($gate->profileAuthorized($profile));
        $this->assertTrue($gate->nonSignificantRuleAuthorized($profile, $nsq));
        $this->assertTrue($gate->roundingRuleAuthorized($profile, $rounding));

        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->assertFalse($gate->profileAuthorized($profile));
            $this->assertFalse($gate->nonSignificantRuleAuthorized($profile, $nsq));
            $this->assertFalse($gate->roundingRuleAuthorized($profile, $rounding));
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
        }
    }

    public function test_evidence_scope_and_version_must_match_exactly_for_profile_nsq_and_rounding(): void
    {
        $source004 = $this->source004Calculation();
        $profile = $this->createDeclarationProfile($source004);
        $nsq = $this->createNsqRule($profile);
        $rounding = $this->createRoundingRuleWithRanges($profile);
        $gate = app(DeclaredValuesRegulatoryGateService::class);

        $profileBadScope = clone $profile;
        $profileBadScope->evidence_scope = "prefix/{$profile->evidence_scope}/suffix";
        $this->assertFalse($gate->profileAuthorized($profileBadScope));

        $profileBadVersion = clone $profile;
        $profileBadVersion->evidence_version = 'wrong-version';
        $this->assertFalse($gate->profileAuthorized($profileBadVersion));

        $nsqBadScope = clone $nsq;
        $nsqBadScope->evidence_scope = "{$nsq->evidence_scope}/extra";
        $this->assertFalse($gate->nonSignificantRuleAuthorized($profile, $nsqBadScope));

        $roundingBadScope = clone $rounding;
        $roundingBadScope->evidence_scope = "other/{$rounding->evidence_scope}";
        $this->assertFalse($gate->roundingRuleAuthorized($profile, $roundingBadScope));
    }

    public function test_rounding_rule_without_ranges_with_overlapping_ranges_or_missing_matching_range_is_blocked(): void
    {
        $source004 = $this->source004Calculation();
        $profile = $this->createDeclarationProfile($source004);
        $gate = app(DeclaredValuesRegulatoryGateService::class);
        $arithmetic = app(NutritionCalculationArithmetic::class);

        $noRanges = $this->createRoundingRuleWithoutRanges($profile, 'RND-EMPTY');
        $this->assertFalse($gate->roundingRuleAuthorized($profile, $noRanges));

        $overlapping = $this->createRoundingRuleWithoutRanges($profile, 'RND-OVERLAP', 'PORTION');
        RegulatoryRoundingRuleRange::query()->create([
            'rounding_rule_id' => $overlapping->id,
            'range_order' => 1,
            'min_numerator' => '0',
            'min_denominator' => '1',
            'min_inclusive' => true,
            'max_numerator' => '10',
            'max_denominator' => '1',
            'max_inclusive' => true,
            'step_numerator' => '1',
            'step_denominator' => '10',
            'decimal_places' => 1,
        ]);
        RegulatoryRoundingRuleRange::query()->create([
            'rounding_rule_id' => $overlapping->id,
            'range_order' => 2,
            'min_numerator' => '10',
            'min_denominator' => '1',
            'min_inclusive' => true,
            'max_numerator' => null,
            'max_denominator' => null,
            'max_inclusive' => false,
            'step_numerator' => '1',
            'step_denominator' => '1',
            'decimal_places' => 0,
        ]);
        $overlapping->load('ranges');

        $this->assertFalse($gate->roundingRuleAuthorized($profile, $overlapping));
        $this->assertNull($gate->resolveRange($overlapping, $arithmetic->fromDecimal('10')));

        $gappedProfile = $this->createDeclarationProfile($source004);
        $gapped = $this->createRoundingRuleWithoutRanges($gappedProfile, 'RND-GAP');
        RegulatoryRoundingRuleRange::query()->create([
            'rounding_rule_id' => $gapped->id,
            'range_order' => 1,
            'min_numerator' => '0',
            'min_denominator' => '1',
            'min_inclusive' => true,
            'max_numerator' => '5',
            'max_denominator' => '1',
            'max_inclusive' => false,
            'step_numerator' => '1',
            'step_denominator' => '10',
            'decimal_places' => 1,
        ]);
        $gapped->load('ranges');

        $this->assertTrue($gate->roundingRuleAuthorized($gappedProfile, $gapped));
        $this->assertNull($gate->resolveRange($gapped, $arithmetic->fromDecimal('5')));

        $response = $this->postJson("/api/serving-daily-value-calculations/{$source004->id}/declared-nutrition-calculations", [
            'regulatory_declaration_profile_id' => $gappedProfile->id,
        ])->assertCreated();

        $component = $response->json('data.components.0');
        $this->assertNull($component['reference_100']['declared']);
        $this->assertSame('UNAVAILABLE', $component['reference_100']['availability']);
        $this->assertContains('ROUNDING_RANGE_MISMATCH', array_column($component['reference_100']['causes'], 'category'));
    }

    private function source004Calculation(): ServingDailyValueCalculation
    {
        $recipe = Recipe::factory()->create(['yield_value' => '100', 'yield_unit' => 'g']);
        $calc = NutritionCalculation::query()->create([
            'recipe_id' => $recipe->id,
            'yield_value' => '100',
            'yield_unit' => 'g',
            'rules_version' => NutritionCalculation::RULES_VERSION,
        ]);
        $calcNutrient = $calc->nutrients()->create([
            'nutrient_id' => 101,
            'nutrient_name' => 'Carboidratos',
            'nutrient_unit' => 'g',
            'total_numerator' => '10',
            'total_denominator' => '1',
            'is_complete' => true,
            'has_missing_relation' => false,
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => '10',
            'reference_denominator' => '1',
        ]);

        $profile004 = RegulatoryProfile::query()->create([
            'code' => '004-GATE',
            'version' => 'v1',
            'title' => 'Perfil 004 Gate',
            'normative_source' => 'Norma teste',
            'normative_version' => '2026',
            'consulted_on' => '2026-11-12',
            'technical_status' => 'BLOCKED',
        ]);
        $portionAuth = PortionAuthorization::query()->create([
            'nutrition_calculation_id' => $calc->id,
            'reference_code' => 'PORTION-GATE',
            'reference_version' => 'v1',
            'reference_source' => 'Fixture controlada',
            'technical_status' => 'AUTHORIZED',
            'portion_value' => '20',
            'portion_unit' => 'g',
            'household_measure_mode' => 'PROVIDED',
            'household_measure' => '1 colher',
            'evidence_reference' => 'EV-PORT',
            'evidence_source' => 'Automated test',
            'evidence_version' => 'v1',
            'evidence_scope' => "nutrition-calculation:{$calc->id}/portion:PORTION-GATE@v1",
            'evidence_kind' => 'TEST_FIXTURE',
            'validated_by' => 'Automated test',
            'validated_on' => '2026-11-12',
        ]);

        $sv = ServingDailyValueCalculation::query()->create([
            'nutrition_calculation_id' => $calc->id,
            'source_recipe_id' => $recipe->id,
            'source_rules_version' => NutritionCalculation::RULES_VERSION,
            'source_yield_value' => '100',
            'source_yield_unit' => 'g',
            'portion_value' => '20',
            'portion_unit' => 'g',
            'household_measure_mode' => 'PROVIDED',
            'household_measure' => '1 colher',
            'portion_reference_code' => $portionAuth->reference_code,
            'portion_reference_version' => $portionAuth->reference_version,
            'portion_reference_source' => $portionAuth->reference_source,
            'portion_reference_technical_status' => 'AUTHORIZED',
            'portion_evidence_reference' => $portionAuth->evidence_reference,
            'portion_evidence_source' => $portionAuth->evidence_source,
            'portion_evidence_version' => $portionAuth->evidence_version,
            'portion_evidence_scope' => $portionAuth->evidence_scope,
            'portion_evidence_kind' => $portionAuth->evidence_kind,
            'portion_validated_by' => $portionAuth->validated_by,
            'portion_validated_on' => $portionAuth->validated_on,
            'regulatory_profile_code' => $profile004->code,
            'regulatory_profile_version' => $profile004->version,
            'regulatory_profile_title' => $profile004->title,
            'regulatory_profile_status' => 'AUTHORIZED',
            'regulatory_profile_normative_source' => $profile004->normative_source,
            'regulatory_profile_normative_version' => $profile004->normative_version,
            'regulatory_profile_consulted_on' => '2026-11-12',
            'energy_is_complete' => false,
            'energy_availability' => 'UNAVAILABLE',
            'rules_version' => ServingDailyValueCalculation::RULES_VERSION,
        ]);

        $svNutrient = $sv->nutrients()->create([
            'nutrition_calculation_nutrient_id' => $calcNutrient->id,
            'nutrient_id' => 101,
            'nutrient_name' => 'Carboidratos',
            'nutrient_unit' => 'g',
            'source_is_complete' => true,
            'source_has_missing_relation' => false,
            'portion_numerator' => '2',
            'portion_denominator' => '1',
            'is_complete' => true,
            'has_missing_relation' => false,
        ]);

        $sv->regulatoryResults()->create([
            'serving_daily_value_nutrient_id' => $svNutrient->id,
            'map_code' => 'MAP-CARB',
            'component_name' => 'Carboidratos',
            'technical_status' => 'AUTHORIZED',
            'nutrient_id' => 101,
            'nutrient_name' => 'Carboidratos',
            'source_unit' => 'g',
            'regulatory_unit' => 'g',
            'map_source_reference' => 'Norma teste MAP',
            'map_evidence_reference' => 'EV-MAP',
            'map_evidence_source' => 'Automated test',
            'map_evidence_version' => 'v1',
            'map_evidence_scope' => 'profile:004-GATE@v1/map:MAP-CARB/nutrient:101',
            'map_evidence_kind' => 'TEST_FIXTURE',
            'map_validated_by' => 'Automated test',
            'map_validated_on' => '2026-11-12',
            'conversion_status' => 'NOT_REQUIRED',
            'value_numerator' => '2',
            'value_denominator' => '1',
            'is_complete' => true,
            'availability' => 'AVAILABLE',
        ]);

        return $sv;
    }

    /** @param array<string, mixed> $overrides */
    private function createDeclarationProfile(ServingDailyValueCalculation $source, array $overrides = []): RegulatoryDeclarationProfile
    {
        $version = str()->uuid()->toString();
        $code = $overrides['code'] ?? 'DECL-GATE';
        $scope = "declaration-profile:{$code}@{$version}";

        return RegulatoryDeclarationProfile::query()->create(array_merge([
            'code' => $code,
            'version' => $version,
            'title' => 'Pacote de Declaração Gate',
            'regulatory_profile_code' => $source->regulatory_profile_code,
            'regulatory_profile_version' => $source->regulatory_profile_version,
            'normative_source' => $source->regulatory_profile_normative_source,
            'normative_version' => $source->regulatory_profile_normative_version,
            'consulted_on' => '2026-11-12',
            'scope_recipe_id' => null,
            'scope_nutrition_calculation_id' => null,
            'technical_status' => 'AUTHORIZED',
            'evidence_reference' => "EV-{$scope}",
            'evidence_source' => 'Automated test',
            'evidence_version' => $version,
            'evidence_scope' => $scope,
            'evidence_kind' => 'TEST_FIXTURE',
            'validated_by' => 'Automated test',
            'validated_on' => '2026-11-12',
        ], $overrides));
    }

    private function createNsqRule(RegulatoryDeclarationProfile $profile): RegulatoryNonSignificantRule
    {
        $ruleCode = 'NSQ-CARB-PORTION';

        return RegulatoryNonSignificantRule::query()->create([
            'declaration_profile_id' => $profile->id,
            'rule_code' => $ruleCode,
            'target_kind' => 'COMPONENT',
            'map_code' => 'MAP-CARB',
            'basis' => 'PORTION',
            'technical_status' => 'AUTHORIZED',
            'comparison_operator' => 'LTE',
            'threshold_numerator' => '1',
            'threshold_denominator' => '2',
            'threshold_unit' => 'g',
            'declared_string' => '0',
            'declared_numerator' => '0',
            'declared_denominator' => '1',
            'source_reference' => 'Norma teste NSQ',
            'evidence_reference' => 'EV-NSQ',
            'evidence_source' => 'Automated test',
            'evidence_version' => $profile->version,
            'evidence_scope' => "declaration-profile:{$profile->code}@{$profile->version}/nsq:{$ruleCode}",
            'evidence_kind' => 'TEST_FIXTURE',
            'validated_by' => 'Automated test',
            'validated_on' => '2026-11-12',
        ]);
    }

    private function createRoundingRuleWithRanges(RegulatoryDeclarationProfile $profile): RegulatoryRoundingRule
    {
        $rule = $this->createRoundingRuleWithoutRanges($profile, 'RND-CARB-REF100');
        RegulatoryRoundingRuleRange::query()->create([
            'rounding_rule_id' => $rule->id,
            'range_order' => 1,
            'min_numerator' => '0',
            'min_denominator' => '1',
            'min_inclusive' => true,
            'max_numerator' => null,
            'max_denominator' => null,
            'max_inclusive' => false,
            'step_numerator' => '1',
            'step_denominator' => '1',
            'decimal_places' => 0,
        ]);

        return $rule->load('ranges');
    }

    private function createRoundingRuleWithoutRanges(
        RegulatoryDeclarationProfile $profile,
        string $ruleCode,
        string $basis = 'REFERENCE_100',
    ): RegulatoryRoundingRule {
        return RegulatoryRoundingRule::query()->create([
            'declaration_profile_id' => $profile->id,
            'rule_code' => $ruleCode,
            'target_kind' => 'COMPONENT',
            'map_code' => 'MAP-CARB',
            'vdr_code' => null,
            'vdr_numerator_source' => null,
            'basis' => $basis,
            'unit' => 'g',
            'tie_breaker' => 'HALF_UP',
            'technical_status' => 'AUTHORIZED',
            'source_reference' => 'Norma teste Rounding',
            'evidence_reference' => "EV-{$ruleCode}",
            'evidence_source' => 'Automated test',
            'evidence_version' => $profile->version,
            'evidence_scope' => "declaration-profile:{$profile->code}@{$profile->version}/rounding:{$ruleCode}",
            'evidence_kind' => 'TEST_FIXTURE',
            'validated_by' => 'Automated test',
            'validated_on' => '2026-11-12',
        ]);
    }
}
