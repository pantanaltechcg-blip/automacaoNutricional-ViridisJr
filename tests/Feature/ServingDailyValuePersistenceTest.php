<?php

namespace Tests\Feature;

use App\Models\Nutrient;
use App\Models\NutritionCalculation;
use App\Models\PortionAuthorization;
use App\Models\Recipe;
use App\Models\RegulatoryDailyValue;
use App\Models\RegulatoryEnergyApplicability;
use App\Models\RegulatoryEnergyComponent;
use App\Models\RegulatoryNutrientMapping;
use App\Models\RegulatoryProfile;
use App\Models\ServingDailyValueCalculation;
use App\Models\ServingDailyValueEnergyComponent;
use App\Models\ServingDailyValueNutrient;
use App\Models\ServingDailyValueNutrientState;
use App\Models\ServingDailyValuePercentage;
use App\Models\ServingDailyValueRegulatoryResult;
use App\Models\ServingDailyValueResultCause;
use App\Services\ServingDailyValueCalculationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServingDailyValuePersistenceTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<string> */
    private const TABLES = [
        'regulatory_profiles',
        'regulatory_nutrient_mappings',
        'regulatory_energy_components',
        'regulatory_daily_values',
        'portion_authorizations',
        'regulatory_energy_applicabilities',
        'serving_daily_value_calculations',
        'serving_daily_value_nutrients',
        'serving_daily_value_nutrient_states',
        'serving_daily_value_regulatory_results',
        'serving_daily_value_energy_components',
        'serving_daily_value_percentages',
        'serving_daily_value_result_causes',
    ];

    public function test_all_planned_postgresql_tables_exist(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumn(
            'serving_daily_value_calculations',
            'portion_reference_technical_status',
        ));
    }

    public function test_rational_columns_are_unscaled_postgresql_numeric_columns(): void
    {
        $columns = DB::select(<<<'SQL'
            SELECT table_name, column_name, data_type, numeric_precision, numeric_scale
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND column_name LIKE '%_numerator'
              AND table_name LIKE 'serving_daily_value%'
            SQL);

        $this->assertNotEmpty($columns);

        foreach ($columns as $column) {
            $this->assertSame('numeric', $column->data_type);
            $this->assertNull($column->numeric_precision);
            $this->assertNull($column->numeric_scale);
        }
    }

    public function test_models_use_only_the_approved_timestamp_columns(): void
    {
        foreach ([new RegulatoryProfile, new PortionAuthorization, new ServingDailyValueCalculation] as $model) {
            $this->assertTrue($model->usesTimestamps());
            $this->assertNull($model::UPDATED_AT);
        }

        foreach ([
            new RegulatoryNutrientMapping, new RegulatoryEnergyComponent, new RegulatoryDailyValue,
            new RegulatoryEnergyApplicability, new ServingDailyValueNutrient,
            new ServingDailyValueNutrientState, new ServingDailyValueRegulatoryResult,
            new ServingDailyValueEnergyComponent, new ServingDailyValuePercentage,
            new ServingDailyValueResultCause,
        ] as $model) {
            $this->assertFalse($model->usesTimestamps());
        }
    }

    public function test_all_thirteen_tables_have_update_and_delete_protection(): void
    {
        $protectedTables = collect(DB::select(<<<'SQL'
            SELECT c.relname AS table_name
            FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            JOIN pg_proc p ON p.oid = t.tgfoid
            WHERE NOT t.tgisinternal
              AND p.proname = 'prevent_serving_daily_value_mutation'
            ORDER BY c.relname
            SQL))->pluck('table_name')->all();

        $expected = self::TABLES;
        sort($expected);
        $this->assertSame($expected, $protectedTables);
    }

    public function test_authorized_profile_without_complete_evidence_is_rejected_by_postgresql(): void
    {
        try {
            DB::transaction(function (): void {
                DB::table('regulatory_profiles')->insert([
                    'code' => 'INVALID', 'version' => 'v1', 'title' => 'Invalid',
                    'normative_source' => 'source', 'normative_version' => 'v1',
                    'consulted_on' => '2026-11-11', 'technical_status' => 'AUTHORIZED',
                ]);
            });
            $this->fail('AUTHORIZED sem evidência completa deveria ser rejeitado.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('regulatory_profiles_evidence_check', $exception->getMessage());
        }
    }

    public function test_postgresql_rejects_update_and_delete_on_all_thirteen_protected_tables(): void
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
            'name' => 'Componente protegido '.str()->uuid()->toString(),
            'unit' => 'g',
        ]);
        $sourceNutrient = $source->nutrients()->create([
            'nutrient_id' => $nutrient->id,
            'nutrient_name' => $nutrient->name,
            'nutrient_unit' => 'g',
            'total_numerator' => '50',
            'total_denominator' => '1',
            'is_complete' => false,
            'has_missing_relation' => true,
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => '50',
            'reference_denominator' => '1',
        ]);
        $sourceNutrient->states()->create(['state' => 'TRACE']);

        $version = str()->uuid()->toString();
        $profile = RegulatoryProfile::query()->create([
            'code' => 'IMMUTABLE',
            'version' => $version,
            'title' => 'Perfil protegido',
            'normative_source' => 'Fonte controlada',
            'normative_version' => $version,
            'consulted_on' => '2026-11-11',
            'technical_status' => 'AUTHORIZED',
            ...$this->evidence("profile:IMMUTABLE@{$version}", $version),
        ]);
        $mapping = RegulatoryNutrientMapping::query()->create([
            'profile_id' => $profile->id,
            'map_code' => 'MAP-IMMUTABLE',
            'component_name' => 'Componente protegido',
            'nutrient_id' => $nutrient->id,
            'source_unit' => 'g',
            'regulatory_unit' => 'g',
            'technical_status' => 'AUTHORIZED',
            'source_reference' => 'Fonte quantitativa controlada',
            'conversion_status' => 'NOT_REQUIRED',
            ...$this->evidence("profile:IMMUTABLE@{$version}/map:MAP-IMMUTABLE/nutrient:{$nutrient->id}", $version),
            ...$this->evidence("profile:IMMUTABLE@{$version}/map:MAP-IMMUTABLE/nutrient:{$nutrient->id}/conversion:g->g", $version, 'conversion_'),
        ]);
        $energyComponent = RegulatoryEnergyComponent::query()->create([
            'profile_id' => $profile->id,
            'ene_code' => 'ENE-IMMUTABLE',
            'component_name' => 'Componente protegido',
            'mapping_id' => $mapping->id,
            'factor_numerator' => '4',
            'factor_denominator' => '1',
            'factor_input_unit' => 'g',
            'factor_output_unit' => 'kcal',
            'technical_status' => 'AUTHORIZED',
            'source_reference' => 'Fonte energética controlada',
            'overlap_rule_status' => 'NOT_REQUIRED',
            ...$this->evidence("profile:IMMUTABLE@{$version}/ene:ENE-IMMUTABLE/map:MAP-IMMUTABLE", $version),
        ]);
        RegulatoryDailyValue::query()->create([
            'profile_id' => $profile->id,
            'vdr_code' => 'VDR-IMMUTABLE',
            'component_name' => 'Componente protegido',
            'mapping_id' => $mapping->id,
            'vdr_numerator' => '50',
            'vdr_denominator' => '1',
            'unit' => 'g',
            'technical_status' => 'AUTHORIZED',
            'source_reference' => 'Fonte VDR controlada',
            ...$this->evidence("profile:IMMUTABLE@{$version}/vdr:VDR-IMMUTABLE/map:MAP-IMMUTABLE", $version),
        ]);
        RegulatoryEnergyApplicability::query()->create([
            'nutrition_calculation_id' => $source->id,
            'profile_id' => $profile->id,
            'energy_component_id' => $energyComponent->id,
            'status' => 'APPLICABLE',
            'source_reference' => 'Aplicabilidade controlada',
            ...$this->evidence("nutrition-calculation:{$source->id}/profile:IMMUTABLE@{$version}/ene:ENE-IMMUTABLE/map:MAP-IMMUTABLE", $version),
        ]);
        $portion = PortionAuthorization::query()->create([
            'nutrition_calculation_id' => $source->id,
            'reference_code' => 'PORTION-IMMUTABLE',
            'reference_version' => 'v1',
            'reference_source' => 'Fonte de porção controlada',
            'technical_status' => 'AUTHORIZED',
            'portion_value' => '20',
            'portion_unit' => 'g',
            'household_measure_mode' => 'WAIVED',
            'justification' => 'Dispensa controlada.',
            ...$this->evidence("nutrition-calculation:{$source->id}/portion:PORTION-IMMUTABLE@v1", 'v1'),
        ]);

        $calculation = app(ServingDailyValueCalculationService::class)->create($source, [
            'portion' => ['value' => '20', 'unit' => 'g', 'household_measure' => null],
            'portion_authorization_id' => $portion->id,
            'regulatory_profile_id' => $profile->id,
        ]);
        $calculation->causes()->create([
            'scope' => 'REGULATORY_VALUE',
            'scope_key' => 'MAP-IMMUTABLE',
            'category' => 'INCOMPLETE_NUTRITION',
            'code' => 'IMMUTABILITY_PROBE',
            'detail' => 'Registro controlado para provar a proteção PostgreSQL.',
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
            $prefix.'validated_on' => '2026-11-11',
        ];
    }
}
