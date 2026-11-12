<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NutritionCalculation;
use App\Models\PortionAuthorization;
use App\Models\Recipe;
use App\Models\RegulatoryDeclarationProfile;
use App\Models\RegulatoryDeclarationRequirement;
use App\Models\RegulatoryNonSignificantRule;
use App\Models\RegulatoryProfile;
use App\Models\RegulatoryRecalculationPolicy;
use App\Models\RegulatoryRoundingRule;
use App\Models\RegulatoryRoundingRuleRange;
use App\Models\ServingDailyValueCalculation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeclaredNutritionCalculationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_us1_rounds_half_up_across_bases_applies_nsq_only_per_basis_distinguishes_exact_zero_and_blocks_states(): void
    {
        [$source004, $calc] = $this->source004FixtureForUs1();
        $profile = $this->createDeclarationProfileWithUs1Rules($source004);

        $beforeCounts = [
            'recipes' => DB::table('recipes')->count(),
            'nutrition_calculations' => DB::table('nutrition_calculations')->count(),
            'nutrition_calculation_nutrients' => DB::table('nutrition_calculation_nutrients')->count(),
            'serving_daily_value_calculations' => DB::table('serving_daily_value_calculations')->count(),
            'serving_daily_value_regulatory_results' => DB::table('serving_daily_value_regulatory_results')->count(),
        ];

        $response = $this->postJson("/api/serving-daily-value-calculations/{$source004->id}/declared-nutrition-calculations", [
            'regulatory_declaration_profile_id' => $profile->id,
        ])->assertCreated();

        $data = $response->json('data');
        $this->assertSame($source004->id, $data['serving_daily_value_calculation_id']);
        $this->assertSame($calc->id, $data['nutrition_calculation_id']);
        $this->assertSame('declared-values-rounding-v1', $data['rules_version']);

        $components = collect($data['components'])->keyBy('map_code');

        // 1. MAP-001 (HALF_UP tie 5/2 in 100g -> "3" (3/1); conversion mg->g 1250 mg = 5/4 = 1.25 g in portion -> "1.3" (13/10))
        $map1 = $components->get('MAP-001');
        $this->assertSame(['numerator' => '2500', 'denominator' => '1'], $map1['reference_100']['source_exact']);
        $this->assertSame(['numerator' => '5', 'denominator' => '2'], $map1['reference_100']['regulatory_exact']);
        $this->assertFalse($map1['reference_100']['is_exact_zero']);
        $this->assertFalse($map1['reference_100']['nsq_applied']);
        $this->assertSame(['display' => '3', 'ratio' => ['numerator' => '3', 'denominator' => '1']], $map1['reference_100']['declared']);
        $this->assertSame('AVAILABLE', $map1['reference_100']['availability']);

        $this->assertSame(['numerator' => '1250', 'denominator' => '1'], $map1['portion']['source_exact']);
        $this->assertSame(['numerator' => '5', 'denominator' => '4'], $map1['portion']['regulatory_exact']);
        $this->assertSame(['display' => '1.3', 'ratio' => ['numerator' => '13', 'denominator' => '10']], $map1['portion']['declared']);
        $this->assertSame('AVAILABLE', $map1['portion']['availability']);

        // 2. MAP-002 (100g = 2.5 g > 0.5 threshold -> "3"; portion = 0.5 g <= 0.5 -> NSQ applied -> "0", is_exact_zero = false)
        $map2 = $components->get('MAP-002');
        $this->assertFalse($map2['reference_100']['nsq_applied']);
        $this->assertSame(['display' => '3', 'ratio' => ['numerator' => '3', 'denominator' => '1']], $map2['reference_100']['declared']);
        $this->assertFalse($map2['portion']['is_exact_zero']);
        $this->assertTrue($map2['portion']['nsq_applied']);
        $this->assertSame(['display' => '0', 'ratio' => ['numerator' => '0', 'denominator' => '1']], $map2['portion']['declared']);

        // 3. MAP-003 (exact zero 0/1 in both bases -> is_exact_zero = true, nsq_applied = false even with authorized NSQ rule)
        $map3 = $components->get('MAP-003');
        $this->assertTrue($map3['reference_100']['is_exact_zero']);
        $this->assertFalse($map3['reference_100']['nsq_applied']);
        $this->assertSame(['display' => '0.0', 'ratio' => ['numerator' => '0', 'denominator' => '1']], $map3['reference_100']['declared']);
        $this->assertTrue($map3['portion']['is_exact_zero']);
        $this->assertFalse($map3['portion']['nsq_applied']);
        $this->assertSame(['display' => '0.0', 'ratio' => ['numerator' => '0', 'denominator' => '1']], $map3['portion']['declared']);

        // 4. MAP-004 (qualitative TRACE + REEVALUATION + missing relation + incomplete -> null, nsq_applied = false, causes present)
        $map4 = $components->get('MAP-004');
        $this->assertSame(['TRACE', 'REEVALUATION'], $map4['qualitative_states']);
        $this->assertTrue($map4['has_missing_relation']);
        $this->assertFalse($map4['source_complete']);
        $this->assertNull($map4['reference_100']['declared']);
        $this->assertFalse($map4['reference_100']['nsq_applied']);
        $this->assertSame('UNAVAILABLE', $map4['reference_100']['availability']);
        $this->assertContains('QUALITATIVE_STATE_BLOCK', array_column($map4['reference_100']['causes'], 'category'));
        $this->assertNull($map4['portion']['declared']);
        $this->assertFalse($map4['portion']['nsq_applied']);
        $this->assertSame('UNAVAILABLE', $map4['portion']['availability']);

        // 5. MAP-005 (blocked MAP in 004 -> declared null in both bases, regulatory block cause)
        $map5 = $components->get('MAP-005');
        $this->assertNull($map5['reference_100']['declared']);
        $this->assertNull($map5['portion']['declared']);
        $this->assertContains('REGULATORY_BLOCK', array_column($map5['reference_100']['causes'], 'category'));
        $this->assertContains('REGULATORY_BLOCK', array_column($map5['portion']['causes'], 'category'));

        // GET reproduces exact same JSON structure
        $getResponse = $this->getJson("/api/declared-nutrition-calculations/{$data['id']}")
            ->assertOk();
        $this->assertSame($data, $getResponse->json('data'));

        // Features 001-004 tables remain untouched
        foreach ($beforeCounts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "Table {$table} changed after POST");
        }
    }

    /** @return array{ServingDailyValueCalculation, NutritionCalculation} */
    private function source004FixtureForUs1(): array
    {
        $recipe = Recipe::factory()->create(['yield_value' => '100', 'yield_unit' => 'g']);
        $calc = NutritionCalculation::query()->create([
            'recipe_id' => $recipe->id,
            'yield_value' => '100',
            'yield_unit' => 'g',
            'rules_version' => NutritionCalculation::RULES_VERSION,
        ]);

        $n1 = $calc->nutrients()->create([
            'nutrient_id' => 201,
            'nutrient_name' => 'Nutriente Conversao',
            'nutrient_unit' => 'mg',
            'total_numerator' => '2500',
            'total_denominator' => '1',
            'is_complete' => true,
            'has_missing_relation' => false,
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => '2500',
            'reference_denominator' => '1',
        ]);
        $n2 = $calc->nutrients()->create([
            'nutrient_id' => 202,
            'nutrient_name' => 'Nutriente NSQ',
            'nutrient_unit' => 'g',
            'total_numerator' => '5',
            'total_denominator' => '2',
            'is_complete' => true,
            'has_missing_relation' => false,
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => '5',
            'reference_denominator' => '2',
        ]);
        $n3 = $calc->nutrients()->create([
            'nutrient_id' => 203,
            'nutrient_name' => 'Nutriente Zero Exato',
            'nutrient_unit' => 'g',
            'total_numerator' => '0',
            'total_denominator' => '1',
            'is_complete' => true,
            'has_missing_relation' => false,
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => '0',
            'reference_denominator' => '1',
        ]);
        $n4 = $calc->nutrients()->create([
            'nutrient_id' => 204,
            'nutrient_name' => 'Nutriente Qualitativo',
            'nutrient_unit' => 'g',
            'total_numerator' => null,
            'total_denominator' => null,
            'is_complete' => false,
            'has_missing_relation' => true,
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => null,
            'reference_denominator' => null,
        ]);
        $n4->states()->create(['state' => 'REEVALUATION']);
        $n4->states()->create(['state' => 'TRACE']);
        $n5 = $calc->nutrients()->create([
            'nutrient_id' => 205,
            'nutrient_name' => 'Nutriente Bloqueado',
            'nutrient_unit' => 'g',
            'total_numerator' => '4',
            'total_denominator' => '1',
            'is_complete' => true,
            'has_missing_relation' => false,
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => '4',
            'reference_denominator' => '1',
        ]);

        $profile004 = RegulatoryProfile::query()->create([
            'code' => '004-US1',
            'version' => 'v1',
            'title' => 'Perfil 004 US1',
            'normative_source' => 'Norma US1',
            'normative_version' => '2026.1',
            'consulted_on' => '2026-11-12',
            'technical_status' => 'BLOCKED',
        ]);
        $portionAuth = PortionAuthorization::query()->create([
            'nutrition_calculation_id' => $calc->id,
            'reference_code' => 'PORT-US1',
            'reference_version' => 'v1',
            'reference_source' => 'Fixture controlada',
            'technical_status' => 'AUTHORIZED',
            'portion_value' => '20',
            'portion_unit' => 'g',
            'household_measure_mode' => 'PROVIDED',
            'household_measure' => '1 unidade',
            'evidence_reference' => 'EV-PORT-US1',
            'evidence_source' => 'Automated test',
            'evidence_version' => 'v1',
            'evidence_scope' => "nutrition-calculation:{$calc->id}/portion:PORT-US1@v1",
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
            'household_measure' => '1 unidade',
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

        $sv1 = $sv->nutrients()->create([
            'nutrition_calculation_nutrient_id' => $n1->id,
            'nutrient_id' => 201,
            'nutrient_name' => $n1->nutrient_name,
            'nutrient_unit' => 'mg',
            'source_is_complete' => true,
            'source_has_missing_relation' => false,
            'portion_numerator' => '1250',
            'portion_denominator' => '1',
            'is_complete' => true,
            'has_missing_relation' => false,
        ]);
        $sv2 = $sv->nutrients()->create([
            'nutrition_calculation_nutrient_id' => $n2->id,
            'nutrient_id' => 202,
            'nutrient_name' => $n2->nutrient_name,
            'nutrient_unit' => 'g',
            'source_is_complete' => true,
            'source_has_missing_relation' => false,
            'portion_numerator' => '1',
            'portion_denominator' => '2',
            'is_complete' => true,
            'has_missing_relation' => false,
        ]);
        $sv3 = $sv->nutrients()->create([
            'nutrition_calculation_nutrient_id' => $n3->id,
            'nutrient_id' => 203,
            'nutrient_name' => $n3->nutrient_name,
            'nutrient_unit' => 'g',
            'source_is_complete' => true,
            'source_has_missing_relation' => false,
            'portion_numerator' => '0',
            'portion_denominator' => '1',
            'is_complete' => true,
            'has_missing_relation' => false,
        ]);
        $sv4 = $sv->nutrients()->create([
            'nutrition_calculation_nutrient_id' => $n4->id,
            'nutrient_id' => 204,
            'nutrient_name' => $n4->nutrient_name,
            'nutrient_unit' => 'g',
            'source_is_complete' => false,
            'source_has_missing_relation' => true,
            'portion_numerator' => null,
            'portion_denominator' => null,
            'is_complete' => false,
            'has_missing_relation' => true,
        ]);
        $sv4->states()->create(['state' => 'REEVALUATION']);
        $sv4->states()->create(['state' => 'TRACE']);
        $sv5 = $sv->nutrients()->create([
            'nutrition_calculation_nutrient_id' => $n5->id,
            'nutrient_id' => 205,
            'nutrient_name' => $n5->nutrient_name,
            'nutrient_unit' => 'g',
            'source_is_complete' => true,
            'source_has_missing_relation' => false,
            'portion_numerator' => '4',
            'portion_denominator' => '5',
            'is_complete' => true,
            'has_missing_relation' => false,
        ]);

        $sv->regulatoryResults()->create([
            'serving_daily_value_nutrient_id' => $sv1->id,
            'map_code' => 'MAP-001',
            'component_name' => 'Componente Conversao',
            'technical_status' => 'AUTHORIZED',
            'nutrient_id' => 201,
            'nutrient_name' => $n1->nutrient_name,
            'source_unit' => 'mg',
            'regulatory_unit' => 'g',
            'map_source_reference' => 'Norma MAP-001',
            'map_evidence_reference' => 'EV-MAP-001',
            'map_evidence_source' => 'Automated test',
            'map_evidence_version' => 'v1',
            'map_evidence_scope' => 'profile:004-US1@v1/map:MAP-001/nutrient:201',
            'map_evidence_kind' => 'TEST_FIXTURE',
            'map_validated_by' => 'Automated test',
            'map_validated_on' => '2026-11-12',
            'conversion_status' => 'AUTHORIZED',
            'conversion_source_unit' => 'mg',
            'conversion_target_unit' => 'g',
            'conversion_numerator' => '1',
            'conversion_denominator' => '1000',
            'conversion_evidence_reference' => 'EV-CONV-001',
            'conversion_evidence_source' => 'Automated test',
            'conversion_evidence_version' => 'v1',
            'conversion_evidence_scope' => 'profile:004-US1@v1/map:MAP-001/nutrient:201/conversion:mg->g',
            'conversion_evidence_kind' => 'TEST_FIXTURE',
            'conversion_validated_by' => 'Automated test',
            'conversion_validated_on' => '2026-11-12',
            'value_numerator' => '5',
            'value_denominator' => '4',
            'is_complete' => true,
            'availability' => 'AVAILABLE',
        ]);

        foreach ([
            [$sv2, 'MAP-002', 'Componente NSQ', 202, $n2->nutrient_name, '1', '2', 'AUTHORIZED', true, 'AVAILABLE'],
            [$sv3, 'MAP-003', 'Componente Zero', 203, $n3->nutrient_name, '0', '1', 'AUTHORIZED', true, 'AVAILABLE'],
            [$sv4, 'MAP-004', 'Componente Qualitativo', 204, $n4->nutrient_name, null, null, 'AUTHORIZED', false, 'UNAVAILABLE'],
            [$sv5, 'MAP-005', 'Componente Bloqueado', 205, $n5->nutrient_name, null, null, 'BLOCKED', false, 'UNAVAILABLE'],
        ] as [$svNut, $mapCode, $compName, $nutId, $nutName, $valNum, $valDen, $status, $complete, $avail]) {
            $sv->regulatoryResults()->create([
                'serving_daily_value_nutrient_id' => $svNut->id,
                'map_code' => $mapCode,
                'component_name' => $compName,
                'technical_status' => $status,
                'nutrient_id' => $nutId,
                'nutrient_name' => $nutName,
                'source_unit' => 'g',
                'regulatory_unit' => 'g',
                'map_source_reference' => "Norma {$mapCode}",
                'map_evidence_reference' => "EV-{$mapCode}",
                'map_evidence_source' => 'Automated test',
                'map_evidence_version' => 'v1',
                'map_evidence_scope' => "profile:004-US1@v1/map:{$mapCode}/nutrient:{$nutId}",
                'map_evidence_kind' => 'TEST_FIXTURE',
                'map_validated_by' => 'Automated test',
                'map_validated_on' => '2026-11-12',
                'conversion_status' => 'NOT_REQUIRED',
                'value_numerator' => $valNum,
                'value_denominator' => $valDen,
                'is_complete' => $complete,
                'availability' => $avail,
            ]);
        }

        return [$sv, $calc];
    }

    private function createDeclarationProfileWithUs1Rules(ServingDailyValueCalculation $source): RegulatoryDeclarationProfile
    {
        $code = 'DECL-US1';
        $version = str()->uuid()->toString();
        $profile = RegulatoryDeclarationProfile::query()->create([
            'code' => $code,
            'version' => $version,
            'title' => 'Pacote US1',
            'regulatory_profile_code' => $source->regulatory_profile_code,
            'regulatory_profile_version' => $source->regulatory_profile_version,
            'normative_source' => $source->regulatory_profile_normative_source,
            'normative_version' => $source->regulatory_profile_normative_version,
            'consulted_on' => '2026-11-12',
            'technical_status' => 'AUTHORIZED',
            'evidence_reference' => "EV-{$code}",
            'evidence_source' => 'Automated test',
            'evidence_version' => $version,
            'evidence_scope' => "declaration-profile:{$code}@{$version}",
            'evidence_kind' => 'TEST_FIXTURE',
            'validated_by' => 'Automated test',
            'validated_on' => '2026-11-12',
        ]);

        // NSQ rules for MAP-002 (both bases <= 0.5) and MAP-003 (both bases <= 0.5)
        foreach (['MAP-002', 'MAP-003'] as $mapCode) {
            foreach (['REFERENCE_100', 'PORTION'] as $basis) {
                $ruleCode = "NSQ-{$mapCode}-{$basis}";
                RegulatoryNonSignificantRule::query()->create([
                    'declaration_profile_id' => $profile->id,
                    'rule_code' => $ruleCode,
                    'target_kind' => 'COMPONENT',
                    'map_code' => $mapCode,
                    'basis' => $basis,
                    'technical_status' => 'AUTHORIZED',
                    'comparison_operator' => 'LTE',
                    'threshold_numerator' => '1',
                    'threshold_denominator' => '2',
                    'threshold_unit' => 'g',
                    'declared_string' => '0',
                    'declared_numerator' => '0',
                    'declared_denominator' => '1',
                    'source_reference' => 'Norma teste NSQ',
                    'evidence_reference' => "EV-{$ruleCode}",
                    'evidence_source' => 'Automated test',
                    'evidence_version' => $version,
                    'evidence_scope' => "declaration-profile:{$code}@{$version}/nsq:{$ruleCode}",
                    'evidence_kind' => 'TEST_FIXTURE',
                    'validated_by' => 'Automated test',
                    'validated_on' => '2026-11-12',
                ]);
            }
        }

        // Rounding rules:
        // MAP-001 REF100 -> step 1 (dec 0); MAP-001 PORTION -> step 0.1 (dec 1)
        // MAP-002 both -> step 1 (dec 0)
        // MAP-003 both -> step 0.1 (dec 1) so 0/1 formats as "0.0" while NSQ would give "0"
        $roundingSpecs = [
            ['MAP-001', 'REFERENCE_100', '1', '1', 0],
            ['MAP-001', 'PORTION', '1', '10', 1],
            ['MAP-002', 'REFERENCE_100', '1', '1', 0],
            ['MAP-002', 'PORTION', '1', '1', 0],
            ['MAP-003', 'REFERENCE_100', '1', '10', 1],
            ['MAP-003', 'PORTION', '1', '10', 1],
            ['MAP-004', 'REFERENCE_100', '1', '1', 0],
            ['MAP-004', 'PORTION', '1', '1', 0],
        ];

        foreach ($roundingSpecs as [$mapCode, $basis, $stepNum, $stepDen, $decPlaces]) {
            $ruleCode = "RND-{$mapCode}-{$basis}";
            $rule = RegulatoryRoundingRule::query()->create([
                'declaration_profile_id' => $profile->id,
                'rule_code' => $ruleCode,
                'target_kind' => 'COMPONENT',
                'map_code' => $mapCode,
                'vdr_code' => null,
                'vdr_numerator_source' => null,
                'basis' => $basis,
                'unit' => 'g',
                'tie_breaker' => 'HALF_UP',
                'technical_status' => 'AUTHORIZED',
                'source_reference' => 'Norma teste Rounding',
                'evidence_reference' => "EV-{$ruleCode}",
                'evidence_source' => 'Automated test',
                'evidence_version' => $version,
                'evidence_scope' => "declaration-profile:{$code}@{$version}/rounding:{$ruleCode}",
                'evidence_kind' => 'TEST_FIXTURE',
                'validated_by' => 'Automated test',
                'validated_on' => '2026-11-12',
            ]);

            RegulatoryRoundingRuleRange::query()->create([
                'rounding_rule_id' => $rule->id,
                'range_order' => 1,
                'min_numerator' => '0',
                'min_denominator' => '1',
                'min_inclusive' => true,
                'max_numerator' => null,
                'max_denominator' => null,
                'max_inclusive' => false,
                'step_numerator' => $stepNum,
                'step_denominator' => $stepDen,
                'decimal_places' => $decPlaces,
            ]);
        }

        return $profile;
    }

    public function test_us2_recalculates_energy_and_daily_values_from_declared_components_in_normative_order(): void
    {
        $source004 = $this->source004FixtureForUs2();
        $profile = $this->createDeclarationProfileForUs2($source004);

        $response = $this->postJson("/api/serving-daily-value-calculations/{$source004->id}/declared-nutrition-calculations", [
            'regulatory_declaration_profile_id' => $profile->id,
        ])->assertCreated();

        $data = $response->json('data');

        // 004 derived portion energy: (26/10)*4 + (26/10)*4 + (1/10)*9 = 217/10 = 21.7 kcal (rounding exact would be 22, NOT 24)
        $this->assertSame(['numerator' => '217', 'denominator' => '10'], $data['energy']['source_portion_derived_exact']['value']);
        $this->assertSame('AVAILABLE', $data['energy']['source_portion_derived_exact']['availability']);

        // Portion components: CARB 2.6 -> "3", PROT 2.6 -> "3", FAT 0.1 -> NSQ -> "0"
        // Recalculated portion energy = 3*4 + 3*4 + 0*9 = 24 kcal (different from 22!)
        $portionEnergy = $data['energy']['portion'];
        $this->assertSame(['numerator' => '24', 'denominator' => '1'], $portionEnergy['recalculated_exact']);
        $this->assertFalse($portionEnergy['nsq_applied']);
        $this->assertSame(['display' => '24', 'ratio' => ['numerator' => '24', 'denominator' => '1']], $portionEnergy['declared']);
        $this->assertSame('kcal', $portionEnergy['unit']);
        $this->assertTrue($portionEnergy['complete']);
        $this->assertSame('AVAILABLE', $portionEnergy['availability']);
        $this->assertCount(3, $portionEnergy['components']);
        $this->assertSame(['ENE-001', 'ENE-002', 'ENE-003'], array_column($portionEnergy['components'], 'ene_code'));
        // FAT (ENE-003) was zeroed by NSQ, so its input_declared is 0 and contribution is 0/1
        $this->assertSame(['numerator' => '0', 'denominator' => '1'], $portionEnergy['components'][2]['contribution']);

        // Reference 100g energy: CARB 13 -> "13", PROT 13 -> "13", FAT 0.5 -> NSQ "0" => 13*4 + 13*4 + 0 = 104 kcal (or with NSQ energy rule <= 5 -> check non-NSQ vs NSQ)
        $ref100Energy = $data['energy']['reference_100'];
        $this->assertSame(['numerator' => '104', 'denominator' => '1'], $ref100Energy['recalculated_exact']);
        $this->assertSame(['display' => '104', 'ratio' => ['numerator' => '104', 'denominator' => '1']], $ref100Energy['declared']);
        $this->assertSame('AVAILABLE', $ref100Energy['availability']);

        // Daily values recalculated in normative order:
        $dailyValues = collect($data['daily_values'])->keyBy('vdr_code');

        // VDR-001 (CARB): 004 exact was 26/10 / 300 * 100 = 13/15, declared component in portion is 3 => 3/300 * 100 = 1%
        $vdrCarb = $dailyValues->get('VDR-001');
        $this->assertSame(['numerator' => '13', 'denominator' => '15'], $vdrCarb['source_derived_exact']['value']);
        $this->assertSame('DECLARED_COMPONENT:MAP-CARB', $vdrCarb['numerator_identity']);
        $this->assertSame(['display' => '3', 'ratio' => ['numerator' => '3', 'denominator' => '1']], $vdrCarb['input_declared']);
        $this->assertSame(['numerator' => '1', 'denominator' => '1'], $vdrCarb['unrounded_percentage']);
        $this->assertSame(['display' => '1', 'ratio' => ['numerator' => '1', 'denominator' => '1']], $vdrCarb['declared_percentage']);
        $this->assertSame('AVAILABLE', $vdrCarb['availability']);

        // VDR-ENE (ENERGY): uses declared portion energy 24 / 2000 * 100 = 6/5 (1.2%) -> half_up step 1 = "1"
        $vdrEnergy = $dailyValues->get('VDR-ENE');
        $this->assertSame('DECLARED_PORTION_ENERGY', $vdrEnergy['numerator_identity']);
        $this->assertSame(['display' => '24', 'ratio' => ['numerator' => '24', 'denominator' => '1']], $vdrEnergy['input_declared']);
        $this->assertSame(['numerator' => '6', 'denominator' => '5'], $vdrEnergy['unrounded_percentage']);
        $this->assertSame(['display' => '1', 'ratio' => ['numerator' => '1', 'denominator' => '1']], $vdrEnergy['declared_percentage']);

        // VDR-NA (NO_NORMATIVE_VDR): stays NO_NORMATIVE_VDR with null declared_percentage
        $vdrNa = $dailyValues->get('VDR-NA');
        $this->assertNull($vdrNa['declared_percentage']);
        $this->assertSame('NO_NORMATIVE_VDR', $vdrNa['availability']);

        // VDR-BLK (BLOCKED VDR in 004): stays UNAVAILABLE with null declared_percentage (never uses 004 derived exact)
        $vdrBlk = $dailyValues->get('VDR-BLK');
        $this->assertNull($vdrBlk['declared_percentage']);
        $this->assertSame('UNAVAILABLE', $vdrBlk['availability']);
        $this->assertNotEmpty($vdrBlk['causes']);
    }

    public function test_us2_blocks_energy_and_vd_when_policy_blocked_or_energy_inputs_incomplete_or_matrix_unsafe(): void
    {
        // 1. Blocked recalculation policy blocks BOTH energy (both bases) and all non-NO_NORMATIVE_VDR daily values
        $source004 = $this->source004FixtureForUs2();
        $profileBlockedPolicy = $this->createDeclarationProfileForUs2($source004, policyStatus: 'BLOCKED');

        $respPolicy = $this->postJson("/api/serving-daily-value-calculations/{$source004->id}/declared-nutrition-calculations", [
            'regulatory_declaration_profile_id' => $profileBlockedPolicy->id,
        ])->assertCreated()->json('data');

        $this->assertNull($respPolicy['energy']['reference_100']['declared']);
        $this->assertSame('UNAVAILABLE', $respPolicy['energy']['reference_100']['availability']);
        $this->assertContains('POLICY_BLOCK', array_column($respPolicy['energy']['reference_100']['causes'], 'category'));
        $this->assertNull($respPolicy['energy']['portion']['declared']);
        $this->assertSame('UNAVAILABLE', $respPolicy['energy']['portion']['availability']);
        $this->assertContains('POLICY_BLOCK', array_column($respPolicy['energy']['portion']['causes'], 'category'));

        $vdCarb = collect($respPolicy['daily_values'])->firstWhere('vdr_code', 'VDR-001');
        $this->assertNull($vdCarb['declared_percentage']);
        $this->assertSame('UNAVAILABLE', $vdCarb['availability']);
        $this->assertContains('POLICY_BLOCK', array_column($vdCarb['causes'], 'category'));

        // 2. Energy with an applicable component whose declared value is unavailable (e.g. qualitative/missing rounding) OR UNKNOWN applicability -> null without partial subtotal
        $sourceUnsafeMatrix = $this->source004FixtureForUs2(eneFatApplicability: 'UNKNOWN', eneFatOverlapStatus: 'BLOCKED', eneFatOverlapGroup: 'CARB_OVERLAP');
        $profileOk = $this->createDeclarationProfileForUs2($sourceUnsafeMatrix);

        $respUnsafe = $this->postJson("/api/serving-daily-value-calculations/{$sourceUnsafeMatrix->id}/declared-nutrition-calculations", [
            'regulatory_declaration_profile_id' => $profileOk->id,
        ])->assertCreated()->json('data');

        $this->assertNull($respUnsafe['energy']['portion']['recalculated_exact']);
        $this->assertNull($respUnsafe['energy']['portion']['declared']);
        $this->assertSame('UNAVAILABLE', $respUnsafe['energy']['portion']['availability']);
        $this->assertNotEmpty($respUnsafe['energy']['portion']['causes']);

        // VDR-ENE depends on declared portion energy, so it also becomes UNAVAILABLE without partial calculation
        $vdEne = collect($respUnsafe['daily_values'])->firstWhere('vdr_code', 'VDR-ENE');
        $this->assertNull($vdEne['declared_percentage']);
        $this->assertSame('UNAVAILABLE', $vdEne['availability']);
    }

    private function source004FixtureForUs2(
        string $eneFatApplicability = 'APPLICABLE',
        string $eneFatOverlapStatus = 'NOT_REQUIRED',
        ?string $eneFatOverlapGroup = null,
    ): ServingDailyValueCalculation {
        $recipe = Recipe::factory()->create(['yield_value' => '100', 'yield_unit' => 'g']);
        $calc = NutritionCalculation::query()->create([
            'recipe_id' => $recipe->id,
            'yield_value' => '100',
            'yield_unit' => 'g',
            'rules_version' => NutritionCalculation::RULES_VERSION,
        ]);

        // Portion = 20g of 100g:
        // CARB: 100g = 13g -> portion = 2.6g (26/10 -> declared "3")
        // PROT: 100g = 13g -> portion = 2.6g (26/10 -> declared "3")
        // FAT:  100g = 0.5g (NSQ <= 0.5 -> "0") -> portion = 0.1g (NSQ <= 0.5 -> "0")
        $nCarb = $calc->nutrients()->create([
            'nutrient_id' => 301, 'nutrient_name' => 'Carboidratos', 'nutrient_unit' => 'g',
            'total_numerator' => '13', 'total_denominator' => '1', 'is_complete' => true, 'has_missing_relation' => false,
            'reference_basis' => '100', 'reference_unit' => 'g', 'reference_numerator' => '13', 'reference_denominator' => '1',
        ]);
        $nProt = $calc->nutrients()->create([
            'nutrient_id' => 302, 'nutrient_name' => 'Proteinas', 'nutrient_unit' => 'g',
            'total_numerator' => '13', 'total_denominator' => '1', 'is_complete' => true, 'has_missing_relation' => false,
            'reference_basis' => '100', 'reference_unit' => 'g', 'reference_numerator' => '13', 'reference_denominator' => '1',
        ]);
        $nFat = $calc->nutrients()->create([
            'nutrient_id' => 303, 'nutrient_name' => 'Gorduras Totais', 'nutrient_unit' => 'g',
            'total_numerator' => '1', 'total_denominator' => '2', 'is_complete' => true, 'has_missing_relation' => false,
            'reference_basis' => '100', 'reference_unit' => 'g', 'reference_numerator' => '1', 'reference_denominator' => '2',
        ]);

        $portionAuth = PortionAuthorization::query()->create([
            'nutrition_calculation_id' => $calc->id,
            'reference_code' => 'PORT-US2', 'reference_version' => 'v1', 'reference_source' => 'Fixture controlada',
            'technical_status' => 'AUTHORIZED', 'portion_value' => '20', 'portion_unit' => 'g',
            'household_measure_mode' => 'PROVIDED', 'household_measure' => '1 colher',
            'evidence_reference' => 'EV-PORT-US2', 'evidence_source' => 'Automated test', 'evidence_version' => 'v1',
            'evidence_scope' => "nutrition-calculation:{$calc->id}/portion:PORT-US2@v1",
            'evidence_kind' => 'TEST_FIXTURE', 'validated_by' => 'Automated test', 'validated_on' => '2026-11-12',
        ]);

        $sv = ServingDailyValueCalculation::query()->create([
            'nutrition_calculation_id' => $calc->id,
            'source_recipe_id' => $recipe->id,
            'source_rules_version' => NutritionCalculation::RULES_VERSION,
            'source_yield_value' => '100', 'source_yield_unit' => 'g',
            'portion_value' => '20', 'portion_unit' => 'g',
            'household_measure_mode' => 'PROVIDED', 'household_measure' => '1 colher',
            'portion_reference_code' => 'PORT-US2', 'portion_reference_version' => 'v1',
            'portion_reference_source' => 'Fixture controlada', 'portion_reference_technical_status' => 'AUTHORIZED',
            'portion_evidence_reference' => 'EV-PORT-US2', 'portion_evidence_source' => 'Automated test',
            'portion_evidence_version' => 'v1', 'portion_evidence_scope' => $portionAuth->evidence_scope,
            'portion_evidence_kind' => 'TEST_FIXTURE', 'portion_validated_by' => 'Automated test', 'portion_validated_on' => '2026-11-12',
            'regulatory_profile_code' => '004-US2', 'regulatory_profile_version' => 'v1',
            'regulatory_profile_title' => 'Perfil 004 US2', 'regulatory_profile_status' => 'AUTHORIZED',
            'regulatory_profile_normative_source' => 'Norma US2', 'regulatory_profile_normative_version' => '2026.2',
            'regulatory_profile_consulted_on' => '2026-11-12',
            'energy_numerator' => '217', 'energy_denominator' => '10', 'energy_unit' => 'kcal',
            'energy_is_complete' => true, 'energy_availability' => 'AVAILABLE',
            'rules_version' => ServingDailyValueCalculation::RULES_VERSION,
        ]);

        $svCarb = $sv->nutrients()->create([
            'nutrition_calculation_nutrient_id' => $nCarb->id, 'nutrient_id' => 301, 'nutrient_name' => 'Carboidratos',
            'nutrient_unit' => 'g', 'source_is_complete' => true, 'source_has_missing_relation' => false,
            'portion_numerator' => '13', 'portion_denominator' => '5', 'is_complete' => true, 'has_missing_relation' => false,
        ]);
        $svProt = $sv->nutrients()->create([
            'nutrition_calculation_nutrient_id' => $nProt->id, 'nutrient_id' => 302, 'nutrient_name' => 'Proteinas',
            'nutrient_unit' => 'g', 'source_is_complete' => true, 'source_has_missing_relation' => false,
            'portion_numerator' => '13', 'portion_denominator' => '5', 'is_complete' => true, 'has_missing_relation' => false,
        ]);
        $svFat = $sv->nutrients()->create([
            'nutrition_calculation_nutrient_id' => $nFat->id, 'nutrient_id' => 303, 'nutrient_name' => 'Gorduras Totais',
            'nutrient_unit' => 'g', 'source_is_complete' => true, 'source_has_missing_relation' => false,
            'portion_numerator' => '1', 'portion_denominator' => '10', 'is_complete' => true, 'has_missing_relation' => false,
        ]);

        $resCarb = $sv->regulatoryResults()->create([
            'serving_daily_value_nutrient_id' => $svCarb->id, 'map_code' => 'MAP-CARB', 'component_name' => 'Carboidratos',
            'technical_status' => 'AUTHORIZED', 'nutrient_id' => 301, 'nutrient_name' => 'Carboidratos',
            'source_unit' => 'g', 'regulatory_unit' => 'g', 'map_source_reference' => 'Norma CARB',
            'map_evidence_reference' => 'EV-CARB', 'map_evidence_source' => 'Automated test', 'map_evidence_version' => 'v1',
            'map_evidence_scope' => 'profile:004-US2@v1/map:MAP-CARB/nutrient:301', 'map_evidence_kind' => 'TEST_FIXTURE',
            'map_validated_by' => 'Automated test', 'map_validated_on' => '2026-11-12',
            'conversion_status' => 'NOT_REQUIRED', 'value_numerator' => '13', 'value_denominator' => '5',
            'is_complete' => true, 'availability' => 'AVAILABLE',
        ]);
        $sv->regulatoryResults()->create([
            'serving_daily_value_nutrient_id' => $svProt->id, 'map_code' => 'MAP-PROT', 'component_name' => 'Proteinas',
            'technical_status' => 'AUTHORIZED', 'nutrient_id' => 302, 'nutrient_name' => 'Proteinas',
            'source_unit' => 'g', 'regulatory_unit' => 'g', 'map_source_reference' => 'Norma PROT',
            'map_evidence_reference' => 'EV-PROT', 'map_evidence_source' => 'Automated test', 'map_evidence_version' => 'v1',
            'map_evidence_scope' => 'profile:004-US2@v1/map:MAP-PROT/nutrient:302', 'map_evidence_kind' => 'TEST_FIXTURE',
            'map_validated_by' => 'Automated test', 'map_validated_on' => '2026-11-12',
            'conversion_status' => 'NOT_REQUIRED', 'value_numerator' => '13', 'value_denominator' => '5',
            'is_complete' => true, 'availability' => 'AVAILABLE',
        ]);
        $sv->regulatoryResults()->create([
            'serving_daily_value_nutrient_id' => $svFat->id, 'map_code' => 'MAP-FAT', 'component_name' => 'Gorduras Totais',
            'technical_status' => 'AUTHORIZED', 'nutrient_id' => 303, 'nutrient_name' => 'Gorduras Totais',
            'source_unit' => 'g', 'regulatory_unit' => 'g', 'map_source_reference' => 'Norma FAT',
            'map_evidence_reference' => 'EV-FAT', 'map_evidence_source' => 'Automated test', 'map_evidence_version' => 'v1',
            'map_evidence_scope' => 'profile:004-US2@v1/map:MAP-FAT/nutrient:303', 'map_evidence_kind' => 'TEST_FIXTURE',
            'map_validated_by' => 'Automated test', 'map_validated_on' => '2026-11-12',
            'conversion_status' => 'NOT_REQUIRED', 'value_numerator' => '1', 'value_denominator' => '10',
            'is_complete' => true, 'availability' => 'AVAILABLE',
        ]);

        foreach ([
            ['ENE-001', 'Carboidratos', 'MAP-CARB', '4', '1', 'APPLICABLE', 'NOT_REQUIRED', null],
            ['ENE-002', 'Proteinas', 'MAP-PROT', '4', '1', 'APPLICABLE', 'NOT_REQUIRED', null],
            ['ENE-003', 'Gorduras Totais', 'MAP-FAT', '9', '1', $eneFatApplicability, $eneFatOverlapStatus, $eneFatOverlapGroup],
        ] as [$eneCode, $name, $mapCode, $facNum, $facDen, $appStatus, $overlapStatus, $overlapGroup]) {
            $sv->energyComponents()->create([
                'ene_code' => $eneCode,
                'component_name' => $name,
                'map_code' => $mapCode,
                'applicability_status' => $appStatus,
                'participation' => $appStatus === 'APPLICABLE' && $overlapStatus === 'NOT_REQUIRED' ? 'INCLUDED' : 'BLOCKED',
                'factor_numerator' => $facNum,
                'factor_denominator' => $facDen,
                'factor_input_unit' => 'g',
                'factor_output_unit' => 'kcal',
                'source_reference' => "Norma {$eneCode}",
                'source_version' => 'v1',
                'technical_status' => 'AUTHORIZED',
                'ene_evidence_reference' => "EV-{$eneCode}",
                'ene_evidence_source' => 'Automated test',
                'ene_evidence_version' => 'v1',
                'ene_evidence_scope' => "profile:004-US2@v1/ene:{$eneCode}/map:{$mapCode}",
                'ene_evidence_kind' => 'TEST_FIXTURE',
                'ene_validated_by' => 'Automated test',
                'ene_validated_on' => '2026-11-12',
                'overlap_group' => $overlapGroup,
                'overlap_rule_code' => $overlapGroup !== null ? 'OV-1' : null,
                'overlap_rule_status' => $overlapStatus,
                'applicability_evidence_reference' => "EV-APP-{$eneCode}",
                'applicability_evidence_source' => 'Automated test',
                'applicability_evidence_version' => 'v1',
                'applicability_evidence_scope' => "nutrition-calculation:{$calc->id}/profile:004-US2@v1/ene:{$eneCode}/map:{$mapCode}",
                'applicability_evidence_kind' => 'TEST_FIXTURE',
                'applicability_validated_by' => 'Automated test',
                'applicability_validated_on' => '2026-11-12',
            ]);
        }

        // Daily values:
        // VDR-001 (MAP-CARB: 300 g, derived = 13/15)
        $sv->percentages()->create([
            'vdr_code' => 'VDR-001', 'component_name' => 'Carboidratos', 'map_code' => 'MAP-CARB',
            'serving_daily_value_regulatory_result_id' => $resCarb->id,
            'vdr_numerator' => '300', 'vdr_denominator' => '1', 'vdr_unit' => 'g',
            'vdr_source_reference' => 'Norma VDR-001', 'vdr_source_version' => 'v1', 'technical_status' => 'AUTHORIZED',
            'vdr_evidence_reference' => 'EV-VDR-001', 'vdr_evidence_source' => 'Automated test', 'vdr_evidence_version' => 'v1',
            'vdr_evidence_scope' => 'profile:004-US2@v1/vdr:VDR-001/map:MAP-CARB', 'vdr_evidence_kind' => 'TEST_FIXTURE',
            'vdr_validated_by' => 'Automated test', 'vdr_validated_on' => '2026-11-12',
            'numerator_identity' => 'CARB', 'numerator_numerator' => '13', 'numerator_denominator' => '5',
            'percentage_numerator' => '13', 'percentage_denominator' => '15', 'is_complete' => true, 'availability' => 'AVAILABLE',
        ]);
        // VDR-ENE (Energy: 2000 kcal)
        $sv->percentages()->create([
            'vdr_code' => 'VDR-ENE', 'component_name' => 'Valor Energético', 'map_code' => null,
            'serving_daily_value_regulatory_result_id' => null,
            'vdr_numerator' => '2000', 'vdr_denominator' => '1', 'vdr_unit' => 'kcal',
            'vdr_source_reference' => 'Norma VDR-ENE', 'vdr_source_version' => 'v1', 'technical_status' => 'AUTHORIZED',
            'vdr_evidence_reference' => 'EV-VDR-ENE', 'vdr_evidence_source' => 'Automated test', 'vdr_evidence_version' => 'v1',
            'vdr_evidence_scope' => 'profile:004-US2@v1/vdr:VDR-ENE/energy', 'vdr_evidence_kind' => 'TEST_FIXTURE',
            'vdr_validated_by' => 'Automated test', 'vdr_validated_on' => '2026-11-12',
            'numerator_identity' => 'ENERGY', 'numerator_numerator' => '217', 'numerator_denominator' => '10',
            'percentage_numerator' => '217', 'percentage_denominator' => '200', 'is_complete' => true, 'availability' => 'AVAILABLE',
        ]);
        // VDR-NA (NO_NORMATIVE_VDR)
        $sv->percentages()->create([
            'vdr_code' => 'VDR-NA', 'component_name' => 'Açúcares', 'map_code' => null,
            'serving_daily_value_regulatory_result_id' => null,
            'vdr_numerator' => null, 'vdr_denominator' => null, 'vdr_unit' => null,
            'vdr_source_reference' => 'Norma VDR-NA', 'vdr_source_version' => 'v1', 'technical_status' => 'NO_NORMATIVE_VDR',
            'vdr_evidence_reference' => 'EV-VDR-NA', 'vdr_evidence_source' => 'Automated test', 'vdr_evidence_version' => 'v1',
            'vdr_evidence_scope' => 'profile:004-US2@v1/vdr:VDR-NA/no-normative-vdr', 'vdr_evidence_kind' => 'TEST_FIXTURE',
            'vdr_validated_by' => 'Automated test', 'vdr_validated_on' => '2026-11-12',
            'numerator_identity' => null, 'numerator_numerator' => null, 'numerator_denominator' => null,
            'percentage_numerator' => null, 'percentage_denominator' => null, 'is_complete' => false, 'availability' => 'NO_NORMATIVE_VDR',
        ]);
        // VDR-BLK (BLOCKED VDR)
        $sv->percentages()->create([
            'vdr_code' => 'VDR-BLK', 'component_name' => 'Proteinas', 'map_code' => 'MAP-PROT',
            'serving_daily_value_regulatory_result_id' => null,
            'vdr_numerator' => null, 'vdr_denominator' => null, 'vdr_unit' => 'g',
            'vdr_source_reference' => 'Norma VDR-BLK', 'vdr_source_version' => 'v1', 'technical_status' => 'BLOCKED',
            'numerator_identity' => null, 'numerator_numerator' => null, 'numerator_denominator' => null,
            'percentage_numerator' => null, 'percentage_denominator' => null, 'is_complete' => false, 'availability' => 'UNAVAILABLE',
        ]);

        return $sv;
    }

    private function createDeclarationProfileForUs2(
        ServingDailyValueCalculation $source,
        string $policyStatus = 'AUTHORIZED',
    ): RegulatoryDeclarationProfile {
        $code = 'DECL-US2';
        $version = str()->uuid()->toString();
        $profile = RegulatoryDeclarationProfile::query()->create([
            'code' => $code, 'version' => $version, 'title' => 'Pacote US2',
            'regulatory_profile_code' => $source->regulatory_profile_code,
            'regulatory_profile_version' => $source->regulatory_profile_version,
            'normative_source' => $source->regulatory_profile_normative_source,
            'normative_version' => $source->regulatory_profile_normative_version,
            'consulted_on' => '2026-11-12',
            'technical_status' => 'AUTHORIZED',
            'evidence_reference' => "EV-{$code}", 'evidence_source' => 'Automated test',
            'evidence_version' => $version, 'evidence_scope' => "declaration-profile:{$code}@{$version}",
            'evidence_kind' => 'TEST_FIXTURE', 'validated_by' => 'Automated test', 'validated_on' => '2026-11-12',
        ]);

        RegulatoryRecalculationPolicy::query()->create([
            'declaration_profile_id' => $profile->id,
            'policy_code' => 'REC-001',
            'normative_order_mode' => 'DECLARE_COMPONENTS_THEN_ENERGY_AND_VD',
            'energy_reference_100_input_source' => 'DECLARED_REFERENCE_100',
            'energy_portion_input_source' => 'DECLARED_PORTION',
            'daily_value_input_source' => 'DECLARED_PORTION_AND_DECLARED_ENERGY',
            'requires_complete_energy_inputs' => true,
            'technical_status' => $policyStatus,
            'source_reference' => 'Norma Recalc Policy',
            'evidence_reference' => 'EV-REC-001',
            'evidence_source' => 'Automated test',
            'evidence_version' => $version,
            'evidence_scope' => "declaration-profile:{$code}@{$version}/policy:REC-001",
            'evidence_kind' => 'TEST_FIXTURE',
            'validated_by' => 'Automated test',
            'validated_on' => '2026-11-12',
        ]);

        foreach (['REFERENCE_100', 'PORTION'] as $basis) {
            RegulatoryNonSignificantRule::query()->create([
                'declaration_profile_id' => $profile->id,
                'rule_code' => "NSQ-FAT-{$basis}", 'target_kind' => 'COMPONENT', 'map_code' => 'MAP-FAT', 'basis' => $basis,
                'technical_status' => 'AUTHORIZED', 'comparison_operator' => 'LTE',
                'threshold_numerator' => '1', 'threshold_denominator' => '2', 'threshold_unit' => 'g',
                'declared_string' => '0', 'declared_numerator' => '0', 'declared_denominator' => '1',
                'source_reference' => 'Norma NSQ FAT', 'evidence_reference' => "EV-NSQ-FAT-{$basis}",
                'evidence_source' => 'Automated test', 'evidence_version' => $version,
                'evidence_scope' => "declaration-profile:{$code}@{$version}/nsq:NSQ-FAT-{$basis}",
                'evidence_kind' => 'TEST_FIXTURE', 'validated_by' => 'Automated test', 'validated_on' => '2026-11-12',
            ]);
        }

        // Rounding rules for components (step 1, dec 0), energy (step 1 kcal, dec 0), daily values (step 1 %, dec 0)
        $rules = [];
        foreach (['MAP-CARB', 'MAP-PROT', 'MAP-FAT'] as $mapCode) {
            foreach (['REFERENCE_100', 'PORTION'] as $basis) {
                $rules[] = ['COMPONENT', $mapCode, null, null, $basis, 'g', "RND-{$mapCode}-{$basis}"];
            }
        }
        $rules[] = ['ENERGY', null, null, null, 'REFERENCE_100', 'kcal', 'RND-ENE-REF100'];
        $rules[] = ['ENERGY', null, null, null, 'PORTION', 'kcal', 'RND-ENE-PORTION'];
        $rules[] = ['DAILY_VALUE', null, 'VDR-001', 'DECLARED_COMPONENT', 'PERCENT_VD', '%', 'RND-VDR-001'];
        $rules[] = ['DAILY_VALUE', null, 'VDR-ENE', 'DECLARED_PORTION_ENERGY', 'PERCENT_VD', '%', 'RND-VDR-ENE'];
        $rules[] = ['DAILY_VALUE', null, 'VDR-BLK', 'DECLARED_COMPONENT', 'PERCENT_VD', '%', 'RND-VDR-BLK'];

        foreach ($rules as [$kind, $mapCode, $vdrCode, $vdrNumSrc, $basis, $unit, $ruleCode]) {
            $rule = RegulatoryRoundingRule::query()->create([
                'declaration_profile_id' => $profile->id,
                'rule_code' => $ruleCode,
                'target_kind' => $kind,
                'map_code' => $mapCode,
                'vdr_code' => $vdrCode,
                'vdr_numerator_source' => $vdrNumSrc,
                'basis' => $basis,
                'unit' => $unit,
                'tie_breaker' => 'HALF_UP',
                'technical_status' => 'AUTHORIZED',
                'source_reference' => "Norma {$ruleCode}",
                'evidence_reference' => "EV-{$ruleCode}",
                'evidence_source' => 'Automated test',
                'evidence_version' => $version,
                'evidence_scope' => "declaration-profile:{$code}@{$version}/rounding:{$ruleCode}",
                'evidence_kind' => 'TEST_FIXTURE',
                'validated_by' => 'Automated test',
                'validated_on' => '2026-11-12',
            ]);

            RegulatoryRoundingRuleRange::query()->create([
                'rounding_rule_id' => $rule->id,
                'range_order' => 1,
                'min_numerator' => '0', 'min_denominator' => '1', 'min_inclusive' => true,
                'max_numerator' => null, 'max_denominator' => null, 'max_inclusive' => false,
                'step_numerator' => '1', 'step_denominator' => '1', 'decimal_places' => 0,
            ]);
        }

        return $profile;
    }

    public function test_us3_evaluates_is_label_ready_cumulatively_without_hiding_independent_declared_values(): void
    {
        // 1. Ready: all applicable requirements authorized & available + NOT_APPLICABLE with justification
        $source004 = $this->source004FixtureForUs2();
        $readyProfile = $this->createDeclarationProfileForUs2($source004);
        $this->addRequirements($readyProfile, [
            ['REQ-CARB', 'COMPONENT', 'MAP-CARB', null, 'MANDATORY', 'APPLICABLE', 'AUTHORIZED', null],
            ['REQ-PROT', 'COMPONENT', 'MAP-PROT', null, 'MANDATORY', 'APPLICABLE', 'AUTHORIZED', null],
            ['REQ-FAT', 'COMPONENT', 'MAP-FAT', null, 'MANDATORY', 'APPLICABLE', 'AUTHORIZED', null],
            ['REQ-ENE', 'ENERGY', null, null, 'MANDATORY', 'APPLICABLE', 'AUTHORIZED', null],
            ['REQ-VDR-001', 'DAILY_VALUE', null, 'VDR-001', 'MANDATORY', 'APPLICABLE', 'AUTHORIZED', null],
            ['REQ-VDR-NA', 'DAILY_VALUE', null, 'VDR-NA', 'MANDATORY', 'APPLICABLE', 'AUTHORIZED', null],
            ['REQ-VDR-ENE', 'DAILY_VALUE', null, 'VDR-ENE', 'MANDATORY', 'APPLICABLE', 'AUTHORIZED', null],
            ['REQ-COND-SKIP', 'COMPONENT', 'MAP-OTHER', null, 'CONDITIONAL', 'NOT_APPLICABLE', 'AUTHORIZED', 'Condicional nao aplicavel a este alimento'],
        ]);

        $ready = $this->postJson("/api/serving-daily-value-calculations/{$source004->id}/declared-nutrition-calculations", [
            'regulatory_declaration_profile_id' => $readyProfile->id,
        ])->assertCreated()->json('data');

        $this->assertTrue($ready['is_label_ready']);
        $this->assertSame([], $ready['label_readiness_causes']);

        // 2. Empty requirements set fails closed (deny-by-default) while preserving independent components
        $emptyProfile = $this->createDeclarationProfileForUs2($source004);
        $emptyResp = $this->postJson("/api/serving-daily-value-calculations/{$source004->id}/declared-nutrition-calculations", [
            'regulatory_declaration_profile_id' => $emptyProfile->id,
        ])->assertCreated()->json('data');

        $this->assertFalse($emptyResp['is_label_ready']);
        $this->assertContains('NO_DECLARATION_REQUIREMENTS', array_column($emptyResp['label_readiness_causes'], 'code'));
        $this->assertSame('3', collect($emptyResp['components'])->firstWhere('map_code', 'MAP-CARB')['portion']['declared']['display']);

        // 3. Unknown/Blocked requirement OR missing mandatory component (e.g. sugars/trans) + unauthorized portion -> false with cumulative causes
        $source004BlockedPortion = $this->source004FixtureForUs2();
        DB::statement('ALTER TABLE serving_daily_value_calculations DISABLE TRIGGER ALL');
        $source004BlockedPortion->update(['portion_reference_technical_status' => 'BLOCKED']);
        DB::statement('ALTER TABLE serving_daily_value_calculations ENABLE TRIGGER ALL');

        $badReqProfile = $this->createDeclarationProfileForUs2($source004BlockedPortion);
        $this->addRequirements($badReqProfile, [
            ['REQ-CARB', 'COMPONENT', 'MAP-CARB', null, 'MANDATORY', 'APPLICABLE', 'AUTHORIZED', null],
            ['REQ-SUGARS', 'COMPONENT', 'MAP-SUGARS', null, 'MANDATORY', 'APPLICABLE', 'AUTHORIZED', null],
            ['REQ-TRANS', 'COMPONENT', 'MAP-TRANS', null, 'MANDATORY', 'UNKNOWN', 'BLOCKED', null],
            ['REQ-VDR-BLK', 'DAILY_VALUE', null, 'VDR-BLK', 'MANDATORY', 'APPLICABLE', 'AUTHORIZED', null],
        ]);

        $badResp = $this->postJson("/api/serving-daily-value-calculations/{$source004BlockedPortion->id}/declared-nutrition-calculations", [
            'regulatory_declaration_profile_id' => $badReqProfile->id,
        ])->assertCreated()->json('data');

        $this->assertFalse($badResp['is_label_ready']);
        $causeCodes = array_column($badResp['label_readiness_causes'], 'code');
        $this->assertContains('PORTION_NOT_AUTHORIZED', $causeCodes);
        $this->assertContains('REQUIRED_COMPONENT_UNAVAILABLE', $causeCodes);
        $this->assertContains('REQUIREMENT_NOT_AUTHORIZED', $causeCodes);
        $this->assertContains('REQUIRED_VD_UNAVAILABLE', $causeCodes);
        // Independent CARB stays declared!
        $this->assertSame('3', collect($badResp['components'])->firstWhere('map_code', 'MAP-CARB')['portion']['declared']['display']);
    }

    /** @param array<int, array{string, string, ?string, ?string, string, string, string, ?string}> $specs */
    private function addRequirements(RegulatoryDeclarationProfile $profile, array $specs): void
    {
        foreach ($specs as [$reqCode, $kind, $mapCode, $vdrCode, $level, $appStatus, $techStatus, $justification]) {
            RegulatoryDeclarationRequirement::query()->create([
                'declaration_profile_id' => $profile->id,
                'requirement_code' => $reqCode,
                'target_kind' => $kind,
                'map_code' => $mapCode,
                'vdr_code' => $vdrCode,
                'requirement_level' => $level,
                'applicability_status' => $appStatus,
                'justification' => $justification,
                'technical_status' => $techStatus,
                'source_reference' => "Norma {$reqCode}",
                'evidence_reference' => "EV-{$reqCode}",
                'evidence_source' => 'Automated test',
                'evidence_version' => $profile->version,
                'evidence_scope' => "declaration-profile:{$profile->code}@{$profile->version}/requirement:{$reqCode}",
                'evidence_kind' => 'TEST_FIXTURE',
                'validated_by' => 'Automated test',
                'validated_on' => '2026-11-12',
            ]);
        }
    }
}
