<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DeclaredNutritionCalculation;
use App\Models\DeclaredNutritionCause;
use App\Models\DeclaredNutritionComponent;
use App\Models\DeclaredNutritionComponentState;
use App\Models\DeclaredNutritionEnergyComponent;
use App\Models\DeclaredNutritionPercentage;
use App\Models\Nutrient;
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
use App\Services\ServingDailyValueCalculationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeclaredNutritionPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<string> */
    private const TABLES = [
        'regulatory_declaration_profiles',
        'regulatory_recalculation_policies',
        'regulatory_non_significant_rules',
        'regulatory_rounding_rules',
        'regulatory_rounding_rule_ranges',
        'regulatory_declaration_requirements',
        'declared_nutrition_calculations',
        'declared_nutrition_components',
        'declared_nutrition_component_states',
        'declared_nutrition_energy_components',
        'declared_nutrition_percentages',
        'declared_nutrition_causes',
    ];

    public function test_all_planned_postgresql_tables_exist(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_rational_columns_are_unscaled_postgresql_numeric_columns(): void
    {
        $columns = DB::select(<<<'SQL'
            SELECT table_name, column_name, data_type, numeric_precision, numeric_scale
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND (column_name LIKE '%_numerator' OR column_name LIKE '%_denominator')
              AND (
                  table_name LIKE 'declared_nutrition%'
                  OR table_name LIKE 'regulatory_%'
              )
            SQL);

        $this->assertNotEmpty($columns);

        foreach ($columns as $column) {
            $this->assertSame('numeric', $column->data_type, "{$column->table_name}.{$column->column_name}");
            $this->assertNull($column->numeric_precision, "{$column->table_name}.{$column->column_name}");
            $this->assertNull($column->numeric_scale, "{$column->table_name}.{$column->column_name}");
        }
    }

    public function test_models_use_only_the_approved_timestamp_columns(): void
    {
        foreach ([new RegulatoryDeclarationProfile, new DeclaredNutritionCalculation] as $model) {
            $this->assertTrue($model->usesTimestamps());
            $this->assertNull($model::UPDATED_AT);
        }

        foreach ([
            new RegulatoryRecalculationPolicy,
            new RegulatoryNonSignificantRule,
            new RegulatoryRoundingRule,
            new RegulatoryRoundingRuleRange,
            new RegulatoryDeclarationRequirement,
            new DeclaredNutritionComponent,
            new DeclaredNutritionComponentState,
            new DeclaredNutritionEnergyComponent,
            new DeclaredNutritionPercentage,
            new DeclaredNutritionCause,
        ] as $model) {
            $this->assertFalse($model->usesTimestamps());
        }
    }

    public function test_all_twelve_tables_have_update_and_delete_protection(): void
    {
        $protectedTables = collect(DB::select(<<<'SQL'
            SELECT c.relname AS table_name
            FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            JOIN pg_proc p ON p.oid = t.tgfoid
            WHERE NOT t.tgisinternal
              AND p.proname = 'prevent_declared_nutrition_mutation'
            ORDER BY c.relname
            SQL))->pluck('table_name')->all();

        $expected = self::TABLES;
        sort($expected);
        $this->assertSame($expected, $protectedTables);
    }

    public function test_authorized_declaration_profile_without_complete_evidence_is_rejected_by_postgresql(): void
    {
        try {
            DB::transaction(function (): void {
                DB::table('regulatory_declaration_profiles')->insert([
                    'code' => 'INVALID',
                    'version' => 'v1',
                    'title' => 'Invalid',
                    'regulatory_profile_code' => '004-CQ002',
                    'regulatory_profile_version' => 'v1',
                    'normative_source' => 'RDC 429/2020',
                    'normative_version' => 'v1',
                    'consulted_on' => '2026-11-12',
                    'technical_status' => 'AUTHORIZED',
                ]);
            });
            $this->fail('AUTHORIZED sem evidência completa deveria ser rejeitado.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('regulatory_declaration_profiles_evidence_check', $exception->getMessage());
        }
    }

    public function test_authorized_recalculation_policy_requires_complete_energy_inputs(): void
    {
        $profile = $this->createDeclarationProfile();

        try {
            DB::transaction(function () use ($profile): void {
                DB::table('regulatory_recalculation_policies')->insert([
                    'declaration_profile_id' => $profile->id,
                    'policy_code' => 'REC-INVALID',
                    'normative_order_mode' => 'DECLARE_COMPONENTS_THEN_ENERGY_AND_VD',
                    'energy_reference_100_input_source' => 'DECLARED_REFERENCE_100',
                    'energy_portion_input_source' => 'DECLARED_PORTION',
                    'daily_value_input_source' => 'DECLARED_PORTION_AND_DECLARED_ENERGY',
                    'requires_complete_energy_inputs' => false,
                    'technical_status' => 'AUTHORIZED',
                    'source_reference' => 'RDC 429/2020',
                    ...$this->evidence("declaration-profile:{$profile->code}@{$profile->version}/policy:REC-INVALID", $profile->version),
                ]);
            });
            $this->fail('Política AUTHORIZED com requires_complete_energy_inputs=false deveria falhar.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('regulatory_recalculation_policies_authorized_check', $exception->getMessage());
        }
    }

    public function test_non_canonical_zero_and_fractional_rationals_are_rejected(): void
    {
        $profile = $this->createDeclarationProfile();
        $rule = RegulatoryRoundingRule::query()->create([
            'declaration_profile_id' => $profile->id,
            'rule_code' => 'RND-CHECK',
            'target_kind' => 'COMPONENT',
            'map_code' => 'MAP-002',
            'basis' => 'PORTION',
            'unit' => 'g',
            'tie_breaker' => 'HALF_UP',
            'technical_status' => 'BLOCKED',
        ]);

        foreach ([
            ['min_numerator' => '0', 'min_denominator' => '2', 'step_numerator' => '1', 'step_denominator' => '1'],
            ['min_numerator' => '1', 'min_denominator' => '1', 'step_numerator' => '0', 'step_denominator' => '1'],
            ['min_numerator' => '1.5', 'min_denominator' => '1', 'step_numerator' => '1', 'step_denominator' => '1'],
        ] as $payload) {
            try {
                DB::transaction(function () use ($rule, $payload): void {
                    DB::table('regulatory_rounding_rule_ranges')->insert([
                        'rounding_rule_id' => $rule->id,
                        'range_order' => 1,
                        'min_inclusive' => true,
                        'max_inclusive' => true,
                        'decimal_places' => 0,
                        ...$payload,
                    ]);
                });
                $this->fail('Razão inválida deveria ser rejeitada pelo PostgreSQL.');
            } catch (QueryException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_postgresql_rejects_update_and_delete_on_all_twelve_protected_tables(): void
    {
        $rowIds = $this->seedCompleteProtectedAggregate();

        foreach ($rowIds as $table => $rowId) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                try {
                    DB::transaction(function () use ($operation, $rowId, $table): void {
                        $sql = $operation === 'UPDATE'
                            ? "UPDATE {$table} SET id = id WHERE id = ?"
                            : "DELETE FROM {$table} WHERE id = ?";
                        DB::statement($sql, [$rowId]);
                    });
                    $this->fail("{$operation} deveria ser bloqueado em {$table}.");
                } catch (QueryException $exception) {
                    $this->assertStringContainsStringIgnoringCase('immutable', $exception->getMessage());
                }
            }
        }
    }

    public function test_multiple_posts_create_distinct_snapshots_get_ignores_later_profile_versions_and_001_004_untouched(): void
    {
        $recipe = Recipe::factory()->create(['yield_value' => '100', 'yield_unit' => 'g']);
        $calc = NutritionCalculation::query()->create([
            'recipe_id' => $recipe->id,
            'yield_value' => '100',
            'yield_unit' => 'g',
            'rules_version' => NutritionCalculation::RULES_VERSION,
        ]);
        $calcNutrient = $calc->nutrients()->create([
            'nutrient_id' => 901,
            'nutrient_name' => 'Carboidratos Hist',
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
        $portionAuth = PortionAuthorization::query()->create([
            'nutrition_calculation_id' => $calc->id,
            'reference_code' => 'PORT-HIST',
            'reference_version' => 'v1',
            'reference_source' => 'Fixture controlada',
            'technical_status' => 'AUTHORIZED',
            'portion_value' => '25',
            'portion_unit' => 'g',
            'household_measure_mode' => 'PROVIDED',
            'household_measure' => '1 fatia',
            'evidence_reference' => 'EV-PORT-HIST',
            'evidence_source' => 'Automated test',
            'evidence_version' => 'v1',
            'evidence_scope' => "nutrition-calculation:{$calc->id}/portion:PORT-HIST@v1",
            'evidence_kind' => 'TEST_FIXTURE',
            'validated_by' => 'Automated test',
            'validated_on' => '2026-11-12',
        ]);
        $profile004 = RegulatoryProfile::query()->create([
            'code' => '004-HIST',
            'version' => 'v1',
            'title' => 'Perfil 004 Historico',
            'normative_source' => 'Norma Hist',
            'normative_version' => '2026',
            'consulted_on' => '2026-11-12',
            'technical_status' => 'BLOCKED',
        ]);
        $sv = ServingDailyValueCalculation::query()->create([
            'nutrition_calculation_id' => $calc->id,
            'source_recipe_id' => $recipe->id,
            'source_rules_version' => NutritionCalculation::RULES_VERSION,
            'source_yield_value' => '100',
            'source_yield_unit' => 'g',
            'portion_value' => '25',
            'portion_unit' => 'g',
            'household_measure_mode' => 'PROVIDED',
            'household_measure' => '1 fatia',
            'portion_reference_code' => 'PORT-HIST',
            'portion_reference_version' => 'v1',
            'portion_reference_source' => 'Fixture controlada',
            'portion_reference_technical_status' => 'AUTHORIZED',
            'portion_evidence_reference' => 'EV-PORT-HIST',
            'portion_evidence_source' => 'Automated test',
            'portion_evidence_version' => 'v1',
            'portion_evidence_scope' => $portionAuth->evidence_scope,
            'portion_evidence_kind' => 'TEST_FIXTURE',
            'portion_validated_by' => 'Automated test',
            'portion_validated_on' => '2026-11-12',
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
        $svNut = $sv->nutrients()->create([
            'nutrition_calculation_nutrient_id' => $calcNutrient->id,
            'nutrient_id' => 901,
            'nutrient_name' => 'Carboidratos Hist',
            'nutrient_unit' => 'g',
            'source_is_complete' => true,
            'source_has_missing_relation' => false,
            'portion_numerator' => '5',
            'portion_denominator' => '2',
            'is_complete' => true,
            'has_missing_relation' => false,
        ]);
        $sv->regulatoryResults()->create([
            'serving_daily_value_nutrient_id' => $svNut->id,
            'map_code' => 'MAP-HIST',
            'component_name' => 'Carboidratos',
            'technical_status' => 'AUTHORIZED',
            'nutrient_id' => 901,
            'nutrient_name' => 'Carboidratos Hist',
            'source_unit' => 'g',
            'regulatory_unit' => 'g',
            'map_source_reference' => 'Norma MAP',
            'map_evidence_reference' => 'EV-MAP-HIST',
            'map_evidence_source' => 'Automated test',
            'map_evidence_version' => 'v1',
            'map_evidence_scope' => 'profile:004-HIST@v1/map:MAP-HIST/nutrient:901',
            'map_evidence_kind' => 'TEST_FIXTURE',
            'map_validated_by' => 'Automated test',
            'map_validated_on' => '2026-11-12',
            'conversion_status' => 'NOT_REQUIRED',
            'value_numerator' => '5',
            'value_denominator' => '2',
            'is_complete' => true,
            'availability' => 'AVAILABLE',
        ]);

        $v1 = 'hist-v1-'.str()->uuid();
        $declProfileV1 = RegulatoryDeclarationProfile::query()->create([
            'code' => 'DECL-HIST-API',
            'version' => $v1,
            'title' => 'Versao 1',
            'regulatory_profile_code' => $profile004->code,
            'regulatory_profile_version' => $profile004->version,
            'normative_source' => $profile004->normative_source,
            'normative_version' => $profile004->normative_version,
            'consulted_on' => '2026-11-12',
            'technical_status' => 'AUTHORIZED',
            ...$this->evidence("declaration-profile:DECL-HIST-API@{$v1}", $v1),
        ]);
        foreach (['REFERENCE_100', 'PORTION'] as $basis) {
            $rule = RegulatoryRoundingRule::query()->create([
                'declaration_profile_id' => $declProfileV1->id,
                'rule_code' => "RND-HIST-{$basis}",
                'target_kind' => 'COMPONENT',
                'map_code' => 'MAP-HIST',
                'basis' => $basis,
                'unit' => 'g',
                'tie_breaker' => 'HALF_UP',
                'technical_status' => 'AUTHORIZED',
                'source_reference' => 'Norma RND',
                ...$this->evidence("declaration-profile:DECL-HIST-API@{$v1}/rounding:RND-HIST-{$basis}", $v1),
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
                'step_numerator' => '1',
                'step_denominator' => '1',
                'decimal_places' => 0,
            ]);
        }

        $countsBefore = [
            'foods' => DB::table('foods')->count(),
            'recipes' => DB::table('recipes')->count(),
            'nutrition_calculations' => DB::table('nutrition_calculations')->count(),
            'serving_daily_value_calculations' => DB::table('serving_daily_value_calculations')->count(),
        ];

        $first = $this->postJson("/api/serving-daily-value-calculations/{$sv->id}/declared-nutrition-calculations", [
            'regulatory_declaration_profile_id' => $declProfileV1->id,
        ])->assertCreated()->json('data');

        // Create a newer profile version with different rounding step (0.1 instead of 1)
        $v2 = 'hist-v2-'.str()->uuid();
        $declProfileV2 = RegulatoryDeclarationProfile::query()->create([
            'code' => 'DECL-HIST-API',
            'version' => $v2,
            'title' => 'Versao 2',
            'regulatory_profile_code' => $profile004->code,
            'regulatory_profile_version' => $profile004->version,
            'normative_source' => $profile004->normative_source,
            'normative_version' => $profile004->normative_version,
            'consulted_on' => '2026-11-12',
            'technical_status' => 'AUTHORIZED',
            ...$this->evidence("declaration-profile:DECL-HIST-API@{$v2}", $v2),
        ]);
        foreach (['REFERENCE_100', 'PORTION'] as $basis) {
            $rule2 = RegulatoryRoundingRule::query()->create([
                'declaration_profile_id' => $declProfileV2->id,
                'rule_code' => "RND-HIST2-{$basis}",
                'target_kind' => 'COMPONENT',
                'map_code' => 'MAP-HIST',
                'basis' => $basis,
                'unit' => 'g',
                'tie_breaker' => 'HALF_UP',
                'technical_status' => 'AUTHORIZED',
                'source_reference' => 'Norma RND v2',
                ...$this->evidence("declaration-profile:DECL-HIST-API@{$v2}/rounding:RND-HIST2-{$basis}", $v2),
            ]);
            RegulatoryRoundingRuleRange::query()->create([
                'rounding_rule_id' => $rule2->id,
                'range_order' => 1,
                'min_numerator' => '0',
                'min_denominator' => '1',
                'min_inclusive' => true,
                'max_numerator' => null,
                'max_denominator' => null,
                'max_inclusive' => false,
                'step_numerator' => '1',
                'step_denominator' => '10',
                'decimal_places' => 1,
            ]);
        }

        $second = $this->postJson("/api/serving-daily-value-calculations/{$sv->id}/declared-nutrition-calculations", [
            'regulatory_declaration_profile_id' => $declProfileV2->id,
        ])->assertCreated()->json('data');

        $this->assertNotSame($first['id'], $second['id']);
        $this->assertSame('3', $first['components'][0]['portion']['declared']['display']);
        $this->assertSame('2.5', $second['components'][0]['portion']['declared']['display']);

        $this->assertSame($first, $this->getJson("/api/declared-nutrition-calculations/{$first['id']}")->assertOk()->json('data'));
        $this->assertSame($second, $this->getJson("/api/declared-nutrition-calculations/{$second['id']}")->assertOk()->json('data'));

        foreach ($countsBefore as $table => $expected) {
            $this->assertSame($expected, DB::table($table)->count());
        }
    }

    private function createDeclarationProfile(): RegulatoryDeclarationProfile
    {
        $version = str()->uuid()->toString();

        return RegulatoryDeclarationProfile::query()->create([
            'code' => 'DECL-IMMUTABLE',
            'version' => $version,
            'title' => 'Perfil de declaração protegido',
            'regulatory_profile_code' => '004-IMMUTABLE',
            'regulatory_profile_version' => $version,
            'normative_source' => 'RDC 429/2020 + IN 75/2020',
            'normative_version' => $version,
            'consulted_on' => '2026-11-12',
            'technical_status' => 'AUTHORIZED',
            ...$this->evidence("declaration-profile:DECL-IMMUTABLE@{$version}", $version),
        ]);
    }

    /** @return array<string, int> */
    private function seedCompleteProtectedAggregate(): array
    {
        $recipe = Recipe::factory()->create(['yield_value' => '100', 'yield_unit' => 'g']);
        $source = NutritionCalculation::query()->create([
            'recipe_id' => $recipe->id,
            'yield_value' => '100',
            'yield_unit' => 'g',
            'rules_version' => NutritionCalculation::RULES_VERSION,
        ]);
        $nutrient = Nutrient::query()->create([
            'name' => 'Nutriente declarado protegido '.str()->uuid()->toString(),
            'unit' => 'g',
        ]);
        $source->nutrients()->create([
            'nutrient_id' => $nutrient->id,
            'nutrient_name' => $nutrient->name,
            'nutrient_unit' => 'g',
            'total_numerator' => '50',
            'total_denominator' => '1',
            'is_complete' => true,
            'has_missing_relation' => false,
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => '50',
            'reference_denominator' => '1',
        ]);

        $version = str()->uuid()->toString();
        $profile = RegulatoryProfile::query()->create([
            'code' => '004-IMMUTABLE',
            'version' => $version,
            'title' => 'Perfil 004 protegido',
            'normative_source' => 'RDC 429/2020 + IN 75/2020',
            'normative_version' => $version,
            'consulted_on' => '2026-11-12',
            'technical_status' => 'AUTHORIZED',
            ...$this->evidence("profile:004-IMMUTABLE@{$version}", $version),
        ]);
        $portion = PortionAuthorization::query()->create([
            'nutrition_calculation_id' => $source->id,
            'reference_code' => 'PORTION-005',
            'reference_version' => 'v1',
            'reference_source' => 'Fonte controlada',
            'technical_status' => 'AUTHORIZED',
            'portion_value' => '20',
            'portion_unit' => 'g',
            'household_measure_mode' => 'WAIVED',
            'justification' => 'Dispensa controlada.',
            ...$this->evidence("nutrition-calculation:{$source->id}/portion:PORTION-005@v1", 'v1'),
        ]);
        $servingCalc = app(ServingDailyValueCalculationService::class)->create($source, [
            'portion' => ['value' => '20', 'unit' => 'g', 'household_measure' => null],
            'portion_authorization_id' => $portion->id,
            'regulatory_profile_id' => $profile->id,
        ]);

        $declProfile = RegulatoryDeclarationProfile::query()->create([
            'code' => 'DECL-IMMUTABLE',
            'version' => $version,
            'title' => 'Perfil de declaração protegido',
            'regulatory_profile_code' => $profile->code,
            'regulatory_profile_version' => $profile->version,
            'normative_source' => $profile->normative_source,
            'normative_version' => $profile->normative_version,
            'consulted_on' => '2026-11-12',
            'technical_status' => 'AUTHORIZED',
            ...$this->evidence("declaration-profile:DECL-IMMUTABLE@{$version}", $version),
        ]);
        RegulatoryRecalculationPolicy::query()->create([
            'declaration_profile_id' => $declProfile->id,
            'policy_code' => 'REC-001',
            'normative_order_mode' => 'DECLARE_COMPONENTS_THEN_ENERGY_AND_VD',
            'energy_reference_100_input_source' => 'DECLARED_REFERENCE_100',
            'energy_portion_input_source' => 'DECLARED_PORTION',
            'daily_value_input_source' => 'DECLARED_PORTION_AND_DECLARED_ENERGY',
            'requires_complete_energy_inputs' => true,
            'technical_status' => 'AUTHORIZED',
            'source_reference' => 'RDC 429/2020',
            ...$this->evidence("declaration-profile:DECL-IMMUTABLE@{$version}/policy:REC-001", $version),
        ]);
        RegulatoryNonSignificantRule::query()->create([
            'declaration_profile_id' => $declProfile->id,
            'rule_code' => 'NSQ-001',
            'target_kind' => 'COMPONENT',
            'map_code' => 'MAP-002',
            'basis' => 'PORTION',
            'technical_status' => 'AUTHORIZED',
            'comparison_operator' => 'LTE',
            'threshold_numerator' => '1',
            'threshold_denominator' => '2',
            'threshold_unit' => 'g',
            'declared_string' => '0',
            'declared_numerator' => '0',
            'declared_denominator' => '1',
            'source_reference' => 'IN 75/2020',
            ...$this->evidence("declaration-profile:DECL-IMMUTABLE@{$version}/nsq:NSQ-001", $version),
        ]);
        $roundingRule = RegulatoryRoundingRule::query()->create([
            'declaration_profile_id' => $declProfile->id,
            'rule_code' => 'RND-001',
            'target_kind' => 'COMPONENT',
            'map_code' => 'MAP-002',
            'basis' => 'PORTION',
            'unit' => 'g',
            'tie_breaker' => 'HALF_UP',
            'technical_status' => 'AUTHORIZED',
            'source_reference' => 'IN 75/2020',
            ...$this->evidence("declaration-profile:DECL-IMMUTABLE@{$version}/rounding:RND-001", $version),
        ]);
        RegulatoryRoundingRuleRange::query()->create([
            'rounding_rule_id' => $roundingRule->id,
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
        RegulatoryDeclarationRequirement::query()->create([
            'declaration_profile_id' => $declProfile->id,
            'requirement_code' => 'REQ-001',
            'target_kind' => 'COMPONENT',
            'map_code' => 'MAP-002',
            'requirement_level' => 'MANDATORY',
            'applicability_status' => 'APPLICABLE',
            'technical_status' => 'AUTHORIZED',
            'source_reference' => 'RDC 429/2020',
            ...$this->evidence("declaration-profile:DECL-IMMUTABLE@{$version}/requirement:REQ-001", $version),
        ]);

        $root = DeclaredNutritionCalculation::query()->create([
            'serving_daily_value_calculation_id' => $servingCalc->id,
            'source_nutrition_calculation_id' => $source->id,
            'source_recipe_id' => $recipe->id,
            'source_003_rules_version' => $source->rules_version,
            'source_004_rules_version' => $servingCalc->rules_version,
            'source_yield_value' => '100',
            'source_yield_unit' => 'g',
            'portion_value' => '20',
            'portion_unit' => 'g',
            'household_measure_mode' => 'WAIVED',
            'portion_justification' => 'Dispensa controlada.',
            'portion_reference_technical_status' => 'AUTHORIZED',
            ...$this->evidence("nutrition-calculation:{$source->id}/portion:PORTION-005@v1", 'v1', 'portion_'),
            'regulatory_profile_code' => $profile->code,
            'regulatory_profile_version' => $profile->version,
            'regulatory_profile_status' => $profile->technical_status,
            'regulatory_profile_normative_source' => $profile->normative_source,
            'regulatory_profile_normative_version' => $profile->normative_version,
            'declaration_profile_code' => $declProfile->code,
            'declaration_profile_version' => $declProfile->version,
            'declaration_profile_title' => $declProfile->title,
            'declaration_profile_status' => $declProfile->technical_status,
            'declaration_profile_normative_source' => $declProfile->normative_source,
            'declaration_profile_normative_version' => $declProfile->normative_version,
            'declaration_profile_consulted_on' => '2026-11-12',
            ...$this->evidence("declaration-profile:DECL-IMMUTABLE@{$version}", $version, 'declaration_profile_'),
            'reference_100_energy_nsq_applied' => false,
            'reference_100_energy_is_complete' => false,
            'reference_100_energy_availability' => 'UNAVAILABLE',
            'portion_energy_nsq_applied' => false,
            'portion_energy_is_complete' => false,
            'portion_energy_availability' => 'UNAVAILABLE',
            'is_label_ready' => false,
            'rules_version' => DeclaredNutritionCalculation::RULES_VERSION,
        ]);

        $component = $root->components()->create([
            'map_code' => 'MAP-002',
            'component_name' => 'Carboidratos',
            'map_technical_status' => 'AUTHORIZED',
            'nutrient_id' => $nutrient->id,
            'nutrient_name' => $nutrient->name,
            'source_unit' => 'g',
            'regulatory_unit' => 'g',
            'conversion_status' => 'NOT_REQUIRED',
            'source_is_complete' => true,
            'source_has_missing_relation' => false,
            'reference_100_is_exact_zero' => false,
            'reference_100_nsq_applied' => false,
            'reference_100_is_complete' => false,
            'reference_100_availability' => 'UNAVAILABLE',
            'portion_is_exact_zero' => false,
            'portion_nsq_applied' => false,
            'portion_is_complete' => false,
            'portion_availability' => 'UNAVAILABLE',
        ]);
        $component->states()->create(['state' => 'TRACE']);
        $root->energyComponents()->create([
            'basis' => 'PORTION',
            'ene_code' => 'ENE-001',
            'component_name' => 'Carboidratos',
            'map_code' => 'MAP-002',
            'applicability_status' => 'APPLICABLE',
            'participation' => 'BLOCKED',
            'ene_technical_status' => 'BLOCKED',
        ]);
        $root->percentages()->create([
            'vdr_code' => 'VDR-002',
            'component_name' => 'Carboidratos',
            'map_code' => 'MAP-002',
            'vdr_technical_status' => 'BLOCKED',
            'is_complete' => false,
            'availability' => 'UNAVAILABLE',
        ]);
        $root->causes()->create([
            'scope' => 'LABEL_READINESS',
            'scope_key' => 'LABEL_READINESS',
            'category' => 'REGULATORY_BLOCK',
            'code' => 'IMMUTABILITY_PROBE',
        ]);

        $rowIds = [];

        foreach (self::TABLES as $table) {
            $rowIds[$table] = (int) DB::table($table)->max('id');
            $this->assertGreaterThan(0, $rowIds[$table], "Nenhuma linha encontrada em {$table}.");
        }

        return $rowIds;
    }

    /** @return array<string, string> */
    private function evidence(string $scope, string $version, string $prefix = ''): array
    {
        return [
            $prefix.'evidence_reference' => 'EVIDENCE-'.$scope,
            $prefix.'evidence_source' => 'Automated test',
            $prefix.'evidence_version' => $version,
            $prefix.'evidence_scope' => $scope,
            $prefix.'evidence_kind' => 'TEST_FIXTURE',
            $prefix.'validated_by' => 'Automated test',
            $prefix.'validated_on' => '2026-11-12',
        ];
    }
}
