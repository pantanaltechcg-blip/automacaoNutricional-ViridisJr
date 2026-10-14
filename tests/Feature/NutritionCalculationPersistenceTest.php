<?php

namespace Tests\Feature;

use App\Models\NutritionCalculation;
use App\Models\NutritionCalculationContribution;
use App\Models\NutritionCalculationIngredient;
use App\Models\NutritionCalculationNutrient;
use App\Models\NutritionCalculationNutrientState;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NutritionCalculationPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_historical_aggregate_tables_have_the_approved_postgresql_columns(): void
    {
        $expectedColumns = [
            'nutrition_calculations' => [
                'id' => ['bigint', 64, 0, 'NO'],
                'recipe_id' => ['bigint', 64, 0, 'NO'],
                'yield_value' => ['numeric', null, null, 'NO'],
                'yield_unit' => ['character varying', null, null, 'NO'],
                'rules_version' => ['text', null, null, 'NO'],
                'created_at' => ['timestamp without time zone', null, null, 'NO'],
            ],
            'nutrition_calculation_ingredients' => [
                'id' => ['bigint', 64, 0, 'NO'],
                'nutrition_calculation_id' => ['bigint', 64, 0, 'NO'],
                'recipe_ingredient_id' => ['bigint', 64, 0, 'NO'],
                'position' => ['bigint', 64, 0, 'NO'],
                'quantity' => ['numeric', null, null, 'NO'],
                'unit' => ['character varying', null, null, 'NO'],
                'food_id' => ['bigint', 64, 0, 'NO'],
                'food_source' => ['text', null, null, 'NO'],
                'food_source_code' => ['text', null, null, 'YES'],
            ],
            'nutrition_calculation_nutrients' => [
                'id' => ['bigint', 64, 0, 'NO'],
                'nutrition_calculation_id' => ['bigint', 64, 0, 'NO'],
                'nutrient_id' => ['bigint', 64, 0, 'NO'],
                'nutrient_name' => ['text', null, null, 'NO'],
                'nutrient_unit' => ['text', null, null, 'NO'],
                'total_numerator' => ['numeric', null, null, 'YES'],
                'total_denominator' => ['numeric', null, null, 'YES'],
                'is_complete' => ['boolean', null, null, 'NO'],
                'has_missing_relation' => ['boolean', null, null, 'NO'],
                'reference_basis' => ['numeric', null, null, 'NO'],
                'reference_unit' => ['character varying', null, null, 'NO'],
                'reference_numerator' => ['numeric', null, null, 'YES'],
                'reference_denominator' => ['numeric', null, null, 'YES'],
            ],
            'nutrition_calculation_contributions' => [
                'id' => ['bigint', 64, 0, 'NO'],
                'nutrition_calculation_ingredient_id' => ['bigint', 64, 0, 'NO'],
                'nutrition_calculation_nutrient_id' => ['bigint', 64, 0, 'NO'],
                'food_nutrient_id' => ['bigint', 64, 0, 'YES'],
                'input_status' => ['text', null, null, 'YES'],
                'input_amount_value' => ['numeric', 7, 2, 'YES'],
                'is_missing_relation' => ['boolean', null, null, 'NO'],
                'value_numerator' => ['numeric', null, null, 'YES'],
                'value_denominator' => ['numeric', null, null, 'YES'],
            ],
            'nutrition_calculation_nutrient_states' => [
                'id' => ['bigint', 64, 0, 'NO'],
                'nutrition_calculation_nutrient_id' => ['bigint', 64, 0, 'NO'],
                'state' => ['text', null, null, 'NO'],
            ],
        ];

        foreach ($expectedColumns as $table => $columns) {
            $actualColumns = $this->columnsFor($table);
            $this->assertEqualsCanonicalizing(array_keys($columns), array_keys($actualColumns), $table);

            foreach ($columns as $column => $definition) {
                $this->assertSame($definition, $actualColumns[$column], "{$table}.{$column}");
            }
        }
    }

    public function test_foreign_keys_are_limited_to_the_recipe_root_and_internal_aggregate_links(): void
    {
        $this->assertSame([
            'nutrition_calculation_contributions.nutrition_calculation_ingredient_id -> nutrition_calculation_ingredients.id ON DELETE CASCADE',
            'nutrition_calculation_contributions.nutrition_calculation_nutrient_id -> nutrition_calculation_nutrients.id ON DELETE CASCADE',
            'nutrition_calculation_ingredients.nutrition_calculation_id -> nutrition_calculations.id ON DELETE CASCADE',
            'nutrition_calculation_nutrient_states.nutrition_calculation_nutrient_id -> nutrition_calculation_nutrients.id ON DELETE CASCADE',
            'nutrition_calculation_nutrients.nutrition_calculation_id -> nutrition_calculations.id ON DELETE CASCADE',
            'nutrition_calculations.recipe_id -> recipes.id ON DELETE NO ACTION',
        ], $this->foreignKeys());
    }

    public function test_approved_unique_and_check_constraints_are_installed(): void
    {
        $constraints = $this->constraints();

        foreach ([
            'nutrition_calc_ingredients_recipe_ingredient_unique',
            'nutrition_calc_ingredients_position_unique',
            'nutrition_calc_nutrients_nutrient_unique',
            'nutrition_calc_contributions_ingredient_nutrient_unique',
            'nutrition_calc_nutrient_states_state_unique',
            'nutrition_calculations_yield_value_positive_finite_check',
            'nutrition_calculations_yield_unit_check',
            'nutrition_calculation_ingredients_position_positive_check',
            'nutrition_calc_ingredients_quantity_positive_finite_check',
            'nutrition_calculation_ingredients_unit_check',
            'nutrition_calculation_nutrients_total_ratio_check',
            'nutrition_calculation_nutrients_reference_ratio_check',
            'nutrition_calculation_nutrients_total_reference_presence_check',
            'nutrition_calculation_nutrients_reference_basis_check',
            'nutrition_calculation_nutrients_reference_unit_check',
            'nutrition_calculation_contributions_status_check',
            'nutrition_calculation_contributions_value_ratio_check',
            'nutrition_calculation_contributions_shape_check',
            'nutrition_calculation_nutrient_states_state_check',
        ] as $constraint) {
            $this->assertArrayHasKey($constraint, $constraints);
        }

        foreach ([
            'nutrition_calculation_nutrients_total_ratio_check',
            'nutrition_calculation_nutrients_reference_ratio_check',
            'nutrition_calculation_contributions_value_ratio_check',
        ] as $ratioConstraint) {
            $definition = $constraints[$ratioConstraint];
            $this->assertStringContainsString('scale(', $definition);
            $this->assertStringContainsString('denominator >', $definition);
            $this->assertStringContainsString('denominator = (1)::numeric', $definition);
            $this->assertStringContainsString('Infinity', $definition);
            $this->assertStringContainsString('NaN', $definition);
        }

        $this->assertStringContainsString('EXACT', $constraints['nutrition_calculation_contributions_shape_check']);
        $this->assertStringContainsString('is_missing_relation', $constraints['nutrition_calculation_contributions_shape_check']);
        $this->assertStringContainsString('REEVALUATION', $constraints['nutrition_calculation_contributions_status_check']);
        $this->assertStringNotContainsString('EXACT', $constraints['nutrition_calculation_nutrient_states_state_check']);
    }

    public function test_models_preserve_decimal_strings_and_use_only_approved_timestamps(): void
    {
        $calculation = new NutritionCalculation;

        $this->assertTrue($calculation->usesTimestamps());
        $this->assertNull(NutritionCalculation::UPDATED_AT);
        $this->assertSame('nutrition-calculation-v1', $calculation->rules_version);
        $this->assertArrayNotHasKey('yield_value', $calculation->getCasts());

        foreach ([
            new NutritionCalculationIngredient,
            new NutritionCalculationNutrient,
            new NutritionCalculationContribution,
            new NutritionCalculationNutrientState,
        ] as $model) {
            $this->assertFalse($model->usesTimestamps());
        }

        $this->assertArrayNotHasKey('quantity', (new NutritionCalculationIngredient)->getCasts());
        $this->assertArrayNotHasKey('total_numerator', (new NutritionCalculationNutrient)->getCasts());
        $this->assertArrayNotHasKey('reference_numerator', (new NutritionCalculationNutrient)->getCasts());
        $this->assertArrayNotHasKey('input_amount_value', (new NutritionCalculationContribution)->getCasts());
        $this->assertArrayNotHasKey('value_numerator', (new NutritionCalculationContribution)->getCasts());
    }

    /**
     * @return array<string, array{string, ?int, ?int, string}>
     */
    private function columnsFor(string $table): array
    {
        return collect(DB::select(<<<'SQL'
            SELECT column_name, data_type, numeric_precision, numeric_scale, is_nullable
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = ?
            ORDER BY ordinal_position
            SQL, [$table]))
            ->mapWithKeys(fn (object $column): array => [
                $column->column_name => [
                    $column->data_type,
                    $column->numeric_precision === null ? null : (int) $column->numeric_precision,
                    $column->numeric_scale === null ? null : (int) $column->numeric_scale,
                    $column->is_nullable,
                ],
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function foreignKeys(): array
    {
        return collect(DB::select(<<<'SQL'
            SELECT
                source_table.relname || '.' || source_column.attname || ' -> ' ||
                target_table.relname || '.' || target_column.attname || ' ON DELETE ' ||
                CASE constraint_data.confdeltype
                    WHEN 'a' THEN 'NO ACTION'
                    WHEN 'c' THEN 'CASCADE'
                    WHEN 'n' THEN 'SET NULL'
                    WHEN 'r' THEN 'RESTRICT'
                    WHEN 'd' THEN 'SET DEFAULT'
                END AS relationship
            FROM pg_constraint AS constraint_data
            JOIN pg_class AS source_table ON source_table.oid = constraint_data.conrelid
            JOIN pg_namespace AS source_namespace ON source_namespace.oid = source_table.relnamespace
            JOIN pg_class AS target_table ON target_table.oid = constraint_data.confrelid
            JOIN unnest(constraint_data.conkey) WITH ORDINALITY AS source_key(attnum, position) ON true
            JOIN unnest(constraint_data.confkey) WITH ORDINALITY AS target_key(attnum, position)
                ON target_key.position = source_key.position
            JOIN pg_attribute AS source_column
                ON source_column.attrelid = source_table.oid AND source_column.attnum = source_key.attnum
            JOIN pg_attribute AS target_column
                ON target_column.attrelid = target_table.oid AND target_column.attnum = target_key.attnum
            WHERE constraint_data.contype = 'f'
              AND source_namespace.nspname = current_schema()
              AND source_table.relname LIKE 'nutrition_calculation%'
            ORDER BY relationship
            SQL))
            ->pluck('relationship')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function constraints(): array
    {
        return collect(DB::select(<<<'SQL'
            SELECT constraint_data.conname, pg_get_constraintdef(constraint_data.oid) AS definition
            FROM pg_constraint AS constraint_data
            JOIN pg_class AS table_data ON table_data.oid = constraint_data.conrelid
            JOIN pg_namespace AS namespace_data ON namespace_data.oid = table_data.relnamespace
            WHERE namespace_data.nspname = current_schema()
              AND table_data.relname LIKE 'nutrition_calculation%'
              AND constraint_data.contype IN ('c', 'u')
            SQL))
            ->mapWithKeys(fn (object $constraint): array => [
                $constraint->conname => $constraint->definition,
            ])
            ->all();
    }
}
