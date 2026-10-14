<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\FoodNutrient;
use App\Models\Nutrient;
use App\Models\NutritionCalculation;
use App\Models\NutritionCalculationContribution;
use App\Models\NutritionCalculationIngredient;
use App\Models\NutritionCalculationNutrient;
use App\Models\NutritionCalculationNutrientState;
use App\Models\Recipe;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_historical_get_remains_identical_after_source_changes_and_a_new_execution_uses_current_data(): void
    {
        $nutrient = $this->createNutrient('Nutriente histórico', 'g');
        $originalFood = $this->createFood('HISTORY-ORIGINAL');
        $replacementFood = $this->createFood('HISTORY-REPLACEMENT');
        $originalRelation = $this->createFoodNutrient($originalFood, $nutrient, 'EXACT', '10.00');
        $this->createFoodNutrient($replacementFood, $nutrient, 'EXACT', '20.00');
        $initialNutrientName = $nutrient->name;
        $recipe = Recipe::factory()->create([
            'name' => 'Receita histórica A',
            'yield_value' => '100',
            'yield_unit' => 'g',
        ]);
        $ingredient = $recipe->ingredients()->create([
            'food_id' => $originalFood->id,
            'quantity' => '100',
            'unit' => 'g',
            'position' => 1,
        ]);

        $firstResponse = $this->postJson("/api/recipes/{$recipe->id}/nutrition-calculations")
            ->assertSuccessful()
            ->json('data');
        $firstCalculationId = $firstResponse['id'];
        $firstNutrient = $this->nutrientPayloadFor($firstResponse['nutrients'], $nutrient->id);

        $this->assertSame(['value' => '100', 'unit' => 'g'], $firstResponse['yield']);
        $this->assertSame('TACO', $firstResponse['ingredients'][0]['food']['source']);
        $this->assertSame($originalFood->source_code, $firstResponse['ingredients'][0]['food']['source_code']);
        $this->assertSame($initialNutrientName, $firstNutrient['nutrient']['name']);
        $this->assertSame('g', $firstNutrient['nutrient']['unit']);
        $this->assertSame('10.00', $firstNutrient['contributions'][0]['source_amount_per_100g']);
        $this->assertSame(['numerator' => '10', 'denominator' => '1'], $firstNutrient['reference']['value']);

        $recipe->update([
            'name' => 'Receita histórica B',
            'yield_value' => '200',
            'yield_unit' => 'ml',
        ]);
        $ingredient->update([
            'food_id' => $replacementFood->id,
            'quantity' => '50',
            'position' => 2,
        ]);
        $originalFood->update([
            'source' => 'FONTE-ALTERADA',
            'source_code' => 'CODIGO-ALTERADO',
        ]);
        $nutrient->update([
            'name' => 'Nutriente histórico alterado',
            'unit' => 'mg',
        ]);
        $originalRelation->update([
            'amount_value' => null,
            'status' => 'TRACE',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $oldAfterChanges = $this->getJson("/api/nutrition-calculations/{$firstCalculationId}")
            ->assertSuccessful()
            ->json('data');
        $historicalQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame($firstResponse, $oldAfterChanges);

        foreach ($historicalQueries as $query) {
            $sql = strtolower($query['query']);

            foreach (['recipes', 'recipe_ingredients', 'foods', 'nutrients', 'food_nutrients'] as $sourceTable) {
                $this->assertStringNotContainsString('from "'.$sourceTable.'"', $sql);
                $this->assertStringNotContainsString('join "'.$sourceTable.'"', $sql);
            }
        }

        $secondResponse = $this->postJson("/api/recipes/{$recipe->id}/nutrition-calculations")
            ->assertSuccessful()
            ->json('data');
        $secondCalculationId = $secondResponse['id'];
        $secondNutrient = $this->nutrientPayloadFor($secondResponse['nutrients'], $nutrient->id);

        $this->assertNotSame($firstCalculationId, $secondCalculationId);
        $this->assertSame('nutrition-calculation-v1', $firstResponse['rules_version']);
        $this->assertSame('nutrition-calculation-v1', $secondResponse['rules_version']);
        $this->assertSame(['value' => '200', 'unit' => 'ml'], $secondResponse['yield']);
        $this->assertSame(2, $secondResponse['ingredients'][0]['position']);
        $this->assertSame('50', $secondResponse['ingredients'][0]['quantity']);
        $this->assertSame($replacementFood->id, $secondResponse['ingredients'][0]['food']['id']);
        $this->assertSame('Nutriente histórico alterado', $secondNutrient['nutrient']['name']);
        $this->assertSame('mg', $secondNutrient['nutrient']['unit']);
        $this->assertSame('20.00', $secondNutrient['contributions'][0]['source_amount_per_100g']);
        $this->assertSame(['numerator' => '5', 'denominator' => '1'], $secondNutrient['reference']['value']);

        $this->getJson("/api/nutrition-calculations/{$firstCalculationId}")
            ->assertSuccessful()
            ->assertExactJson(['data' => $firstResponse]);
        $this->getJson("/api/nutrition-calculations/{$secondCalculationId}")
            ->assertSuccessful()
            ->assertExactJson(['data' => $secondResponse]);
    }

    #[DataProvider('historicalTableMutationProvider')]
    public function test_postgresql_rejects_mutation_of_every_historical_table(
        string $table,
        string $operation,
    ): void {
        $ids = $this->createHistoricalAggregateWithAQualitativeState();
        $id = $ids[$table];

        try {
            DB::transaction(function () use ($table, $operation, $id): void {
                $sql = $operation === 'UPDATE'
                    ? "UPDATE {$table} SET id = id WHERE id = ?"
                    : "DELETE FROM {$table} WHERE id = ?";

                DB::statement($sql, [$id]);
            });
            $this->fail("{$operation} deveria ser bloqueado em {$table}.");
        } catch (QueryException $exception) {
            $this->assertStringContainsStringIgnoringCase('immutable', $exception->getMessage());
        }

        $this->assertDatabaseHas($table, ['id' => $id]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function historicalTableMutationProvider(): array
    {
        $tables = [
            'nutrition_calculations',
            'nutrition_calculation_ingredients',
            'nutrition_calculation_nutrients',
            'nutrition_calculation_contributions',
            'nutrition_calculation_nutrient_states',
        ];
        $cases = [];

        foreach ($tables as $table) {
            $cases["UPDATE {$table}"] = [$table, 'UPDATE'];
            $cases["DELETE {$table}"] = [$table, 'DELETE'];
        }

        return $cases;
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

    private function createFood(string $label): Food
    {
        return Food::query()->create([
            'name' => "Alimento {$label}",
            'category' => 'Categoria histórica',
            'source' => 'TACO',
            'source_code' => $label.'-'.str()->uuid()->toString(),
        ]);
    }

    private function createNutrient(string $name, string $unit): Nutrient
    {
        return Nutrient::query()->create([
            'name' => $name.' '.str()->uuid()->toString(),
            'unit' => $unit,
        ]);
    }

    private function createFoodNutrient(
        Food $food,
        Nutrient $nutrient,
        string $status,
        ?string $amountValue = null,
    ): FoodNutrient {
        return FoodNutrient::query()->create([
            'food_id' => $food->id,
            'nutrient_id' => $nutrient->id,
            'amount_value' => $amountValue,
            'status' => $status,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function createHistoricalAggregateWithAQualitativeState(): array
    {
        $nutrient = $this->createNutrient('Estado histórico', 'g');
        $food = $this->createFood('IMMUTABLE');
        $this->createFoodNutrient($food, $nutrient, 'TRACE');
        $recipe = Recipe::factory()->create([
            'yield_value' => '100',
            'yield_unit' => 'g',
        ]);
        $recipe->ingredients()->create([
            'food_id' => $food->id,
            'quantity' => '10',
            'unit' => 'g',
            'position' => 1,
        ]);
        $calculationId = $this->postJson("/api/recipes/{$recipe->id}/nutrition-calculations")
            ->assertSuccessful()
            ->json('data.id');
        $ingredientId = (int) DB::table('nutrition_calculation_ingredients')
            ->where('nutrition_calculation_id', $calculationId)
            ->value('id');
        $nutrientId = (int) DB::table('nutrition_calculation_nutrients')
            ->where('nutrition_calculation_id', $calculationId)
            ->where('nutrient_id', $nutrient->id)
            ->value('id');

        return [
            'nutrition_calculations' => (int) $calculationId,
            'nutrition_calculation_ingredients' => $ingredientId,
            'nutrition_calculation_nutrients' => $nutrientId,
            'nutrition_calculation_contributions' => (int) DB::table('nutrition_calculation_contributions')
                ->where('nutrition_calculation_ingredient_id', $ingredientId)
                ->where('nutrition_calculation_nutrient_id', $nutrientId)
                ->value('id'),
            'nutrition_calculation_nutrient_states' => (int) DB::table('nutrition_calculation_nutrient_states')
                ->where('nutrition_calculation_nutrient_id', $nutrientId)
                ->value('id'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nutrients
     * @return array<string, mixed>
     */
    private function nutrientPayloadFor(array $nutrients, int $nutrientId): array
    {
        $payload = collect($nutrients)->first(
            fn (array $item): bool => $item['nutrient']['id'] === $nutrientId,
        );

        $this->assertNotNull($payload);

        return $payload;
    }
}
