<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Food;
use App\Models\FoodNutrient;
use App\Models\Nutrient;
use App\Models\NutritionCalculationNutrient;
use App\Models\Recipe;
use App\Services\NutritionCalculationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class NutritionCalculationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_calculates_positive_and_zero_exact_contributions_as_reduced_ratios(): void
    {
        $nutrient = $this->createNutrient('Componente exato');
        $positiveFood = $this->createFood('EXACT-POSITIVE');
        $zeroFood = $this->createFood('EXACT-ZERO');
        $positiveRelation = $this->createFoodNutrient($positiveFood, $nutrient, 'EXACT', '12.34');
        $zeroRelation = $this->createFoodNutrient($zeroFood, $nutrient, 'EXACT', '0.00');
        $recipe = $this->createRecipe([
            ['food_id' => $positiveFood->id, 'quantity' => '125.5001', 'unit' => 'g', 'position' => 1],
            ['food_id' => $zeroFood->id, 'quantity' => '25', 'unit' => 'g', 'position' => 2],
        ]);

        $contributions = app(NutritionCalculationService::class)->calculateContributions($recipe);
        $ingredients = $recipe->ingredients()->get();
        $positive = $this->contributionFor($contributions, $ingredients[0]->id, $nutrient->id);
        $zero = $this->contributionFor($contributions, $ingredients[1]->id, $nutrient->id);

        $this->assertSame($positiveRelation->id, $positive['food_nutrient_id']);
        $this->assertSame('EXACT', $positive['input_status']);
        $this->assertSame('12.34', $positive['input_amount_value']);
        $this->assertFalse($positive['is_missing_relation']);
        $this->assertSame('774335617', $positive['value_numerator']);
        $this->assertSame('50000000', $positive['value_denominator']);

        $this->assertSame($zeroRelation->id, $zero['food_nutrient_id']);
        $this->assertSame('EXACT', $zero['input_status']);
        $this->assertSame('0.00', $zero['input_amount_value']);
        $this->assertFalse($zero['is_missing_relation']);
        $this->assertSame('0', $zero['value_numerator']);
        $this->assertSame('1', $zero['value_denominator']);
    }

    #[DataProvider('qualitativeStatusProvider')]
    public function test_preserves_a_qualitative_status_without_a_numeric_value(string $status): void
    {
        $nutrient = $this->createNutrient("Componente {$status}");
        $food = $this->createFood("STATUS-{$status}");
        $relation = $this->createFoodNutrient($food, $nutrient, $status);
        $recipe = $this->createRecipe([
            ['food_id' => $food->id, 'quantity' => '75.5', 'unit' => 'g', 'position' => 1],
        ]);

        $contributions = app(NutritionCalculationService::class)->calculateContributions($recipe);
        $ingredient = $recipe->ingredients()->firstOrFail();
        $contribution = $this->contributionFor($contributions, $ingredient->id, $nutrient->id);

        $this->assertSame($relation->id, $contribution['food_nutrient_id']);
        $this->assertSame($status, $contribution['input_status']);
        $this->assertNull($contribution['input_amount_value']);
        $this->assertFalse($contribution['is_missing_relation']);
        $this->assertNull($contribution['value_numerator']);
        $this->assertNull($contribution['value_denominator']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function qualitativeStatusProvider(): array
    {
        return [
            'TRACE' => ['TRACE'],
            'NA' => ['NA'],
            'ABSENT' => ['ABSENT'],
            'REEVALUATION' => ['REEVALUATION'],
        ];
    }

    public function test_represents_a_missing_food_nutrient_relation_separately_from_every_status(): void
    {
        $nutrient = $this->createNutrient('Componente sem relação');
        $food = $this->createFood('MISSING-RELATION');
        $recipe = $this->createRecipe([
            ['food_id' => $food->id, 'quantity' => '10', 'unit' => 'g', 'position' => 1],
        ]);

        $contributions = app(NutritionCalculationService::class)->calculateContributions($recipe);
        $ingredient = $recipe->ingredients()->firstOrFail();
        $contribution = $this->contributionFor($contributions, $ingredient->id, $nutrient->id);

        $this->assertNull($contribution['food_nutrient_id']);
        $this->assertNull($contribution['input_status']);
        $this->assertNull($contribution['input_amount_value']);
        $this->assertTrue($contribution['is_missing_relation']);
        $this->assertNull($contribution['value_numerator']);
        $this->assertNull($contribution['value_denominator']);
    }

    public function test_processes_every_nutrient_and_repeated_food_occurrence_with_grouped_queries(): void
    {
        $energy = $this->createNutrient('Energia da fonte', 'kcal');
        $protein = $this->createNutrient('Proteína');
        $firstFood = $this->createFood('MATRIX-FIRST');
        $secondFood = $this->createFood('MATRIX-SECOND');
        $this->createFoodNutrient($firstFood, $energy, 'EXACT', '10.00');
        $this->createFoodNutrient($firstFood, $protein, 'EXACT', '2.50');
        $this->createFoodNutrient($secondFood, $protein, 'TRACE');
        $recipe = $this->createRecipe([
            ['food_id' => $firstFood->id, 'quantity' => '100', 'unit' => 'g', 'position' => 1],
            ['food_id' => $secondFood->id, 'quantity' => '50', 'unit' => 'g', 'position' => 2],
            ['food_id' => $firstFood->id, 'quantity' => '250', 'unit' => 'g', 'position' => 3],
        ]);
        $ingredients = $recipe->ingredients()->get();
        $nutrientCount = Nutrient::query()->count();
        $foodNutrientCount = FoodNutrient::query()->count();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $contributions = app(NutritionCalculationService::class)->calculateContributions($recipe);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $selectQueries = collect($queries)
            ->filter(fn (array $query): bool => str_starts_with(strtolower($query['query']), 'select'))
            ->values();

        $this->assertCount(3, $selectQueries);
        $this->assertCount($ingredients->count() * $nutrientCount, $contributions);
        $this->assertSame($foodNutrientCount, FoodNutrient::query()->count());

        $firstEnergy = $this->contributionFor($contributions, $ingredients[0]->id, $energy->id);
        $secondEnergy = $this->contributionFor($contributions, $ingredients[2]->id, $energy->id);
        $missingEnergy = $this->contributionFor($contributions, $ingredients[1]->id, $energy->id);

        $this->assertSame('10', $firstEnergy['value_numerator']);
        $this->assertSame('1', $firstEnergy['value_denominator']);
        $this->assertSame('25', $secondEnergy['value_numerator']);
        $this->assertSame('1', $secondEnergy['value_denominator']);
        $this->assertTrue($missingEnergy['is_missing_relation']);
        $this->assertNotSame($ingredients[0]->id, $ingredients[2]->id);
    }

    public function test_calculating_contributions_does_not_modify_recipe_or_nutrition_base_records(): void
    {
        $nutrient = $this->createNutrient('Componente somente leitura');
        $food = $this->createFood('READ-ONLY');
        $foodNutrient = $this->createFoodNutrient($food, $nutrient, 'EXACT', '7.25');
        $recipe = $this->createRecipe([
            ['food_id' => $food->id, 'quantity' => '15.125', 'unit' => 'g', 'position' => 1],
        ]);
        $ingredient = $recipe->ingredients()->firstOrFail();
        $models = [$recipe, $ingredient, $food, $nutrient, $foodNutrient];
        $snapshots = collect($models)->map(fn (Model $model): array => $model->getRawOriginal())->all();
        $tableCounts = collect(['recipes', 'recipe_ingredients', 'foods', 'nutrients', 'food_nutrients'])
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

        app(NutritionCalculationService::class)->calculateContributions($recipe);

        foreach ($models as $index => $model) {
            $this->assertEquals($snapshots[$index], $model->fresh()->getRawOriginal());
        }

        foreach ($tableCounts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }

        $this->assertDatabaseCount('nutrition_calculations', 0);
        $this->assertDatabaseCount('nutrition_calculation_ingredients', 0);
        $this->assertDatabaseCount('nutrition_calculation_nutrients', 0);
        $this->assertDatabaseCount('nutrition_calculation_contributions', 0);
        $this->assertDatabaseCount('nutrition_calculation_nutrient_states', 0);
    }

    public function test_consolidates_exact_ratios_by_nutrient_without_rounding_and_keeps_zero_numeric(): void
    {
        $contributions = [
            $this->consolidationContribution(101, 'EXACT', '1', '3'),
            $this->consolidationContribution(101, 'EXACT', '1', '6'),
            $this->consolidationContribution(101, 'EXACT', '0', '1'),
            $this->consolidationContribution(202, 'EXACT', '5', '2'),
        ];

        $consolidations = app(NutritionCalculationService::class)->consolidateContributions($contributions);
        $first = $this->consolidationFor($consolidations, 101);
        $second = $this->consolidationFor($consolidations, 202);

        $this->assertCount(2, $consolidations);
        $this->assertSame('1', $first['total_numerator']);
        $this->assertSame('2', $first['total_denominator']);
        $this->assertTrue($first['is_complete']);
        $this->assertFalse($first['has_missing_relation']);
        $this->assertSame([], $first['qualitative_states']);
        $this->assertSame('5', $second['total_numerator']);
        $this->assertSame('2', $second['total_denominator']);
        $this->assertTrue($second['is_complete']);
    }

    #[DataProvider('qualitativeStatusProvider')]
    public function test_consolidates_exact_with_each_qualitative_status_as_an_incomplete_subtotal(string $status): void
    {
        $contributions = [
            $this->consolidationContribution(101, 'EXACT', '5', '2'),
            $this->consolidationContribution(101, $status),
        ];

        $consolidation = $this->consolidationFor(
            app(NutritionCalculationService::class)->consolidateContributions($contributions),
            101,
        );

        $this->assertSame('5', $consolidation['total_numerator']);
        $this->assertSame('2', $consolidation['total_denominator']);
        $this->assertFalse($consolidation['is_complete']);
        $this->assertFalse($consolidation['has_missing_relation']);
        $this->assertSame([$status], $consolidation['qualitative_states']);
    }

    public function test_consolidates_exact_with_missing_relation_separately_from_qualitative_states(): void
    {
        $onlyMissing = [
            $this->consolidationContribution(101, 'EXACT', '7', '3'),
            $this->consolidationContribution(101, null, missingRelation: true),
        ];
        $statesAndMissing = [
            $this->consolidationContribution(202, 'EXACT', '7', '3'),
            $this->consolidationContribution(202, 'NA'),
            $this->consolidationContribution(202, 'TRACE'),
            $this->consolidationContribution(202, 'NA'),
            $this->consolidationContribution(202, null, missingRelation: true),
        ];

        $consolidations = app(NutritionCalculationService::class)->consolidateContributions([
            ...$onlyMissing,
            ...$statesAndMissing,
        ]);
        $missing = $this->consolidationFor($consolidations, 101);
        $combined = $this->consolidationFor($consolidations, 202);

        $this->assertSame('7', $missing['total_numerator']);
        $this->assertSame('3', $missing['total_denominator']);
        $this->assertFalse($missing['is_complete']);
        $this->assertTrue($missing['has_missing_relation']);
        $this->assertSame([], $missing['qualitative_states']);

        $this->assertSame('7', $combined['total_numerator']);
        $this->assertSame('3', $combined['total_denominator']);
        $this->assertFalse($combined['is_complete']);
        $this->assertTrue($combined['has_missing_relation']);
        $this->assertSame(['TRACE', 'NA'], $combined['qualitative_states']);
    }

    #[DataProvider('qualitativeStatusProvider')]
    public function test_consolidates_homogeneous_qualitative_states_without_a_numeric_total(string $status): void
    {
        $contributions = [
            $this->consolidationContribution(101, $status),
            $this->consolidationContribution(101, $status),
        ];

        $consolidation = $this->consolidationFor(
            app(NutritionCalculationService::class)->consolidateContributions($contributions),
            101,
        );

        $this->assertNull($consolidation['total_numerator']);
        $this->assertNull($consolidation['total_denominator']);
        $this->assertFalse($consolidation['is_complete']);
        $this->assertFalse($consolidation['has_missing_relation']);
        $this->assertSame([$status], $consolidation['qualitative_states']);
    }

    public function test_consolidates_different_qualitative_states_without_precedence_or_numeric_total(): void
    {
        $contributions = [
            $this->consolidationContribution(101, 'REEVALUATION'),
            $this->consolidationContribution(101, 'ABSENT'),
            $this->consolidationContribution(101, 'TRACE'),
            $this->consolidationContribution(101, 'NA'),
            $this->consolidationContribution(101, 'TRACE'),
        ];

        $consolidation = $this->consolidationFor(
            app(NutritionCalculationService::class)->consolidateContributions($contributions),
            101,
        );

        $this->assertNull($consolidation['total_numerator']);
        $this->assertNull($consolidation['total_denominator']);
        $this->assertFalse($consolidation['is_complete']);
        $this->assertFalse($consolidation['has_missing_relation']);
        $this->assertSame(['TRACE', 'NA', 'ABSENT', 'REEVALUATION'], $consolidation['qualitative_states']);
    }

    public function test_consolidates_qualitative_states_and_missing_relations_without_a_numeric_total(): void
    {
        $contributions = [
            $this->consolidationContribution(101, 'ABSENT'),
            $this->consolidationContribution(101, 'TRACE'),
            $this->consolidationContribution(101, null, missingRelation: true),
        ];

        $consolidation = $this->consolidationFor(
            app(NutritionCalculationService::class)->consolidateContributions($contributions),
            101,
        );

        $this->assertNull($consolidation['total_numerator']);
        $this->assertNull($consolidation['total_denominator']);
        $this->assertFalse($consolidation['is_complete']);
        $this->assertTrue($consolidation['has_missing_relation']);
        $this->assertSame(['TRACE', 'ABSENT'], $consolidation['qualitative_states']);
    }

    public function test_consolidates_all_missing_relations_without_a_state_or_numeric_total(): void
    {
        $contributions = [
            $this->consolidationContribution(101, null, missingRelation: true),
            $this->consolidationContribution(101, null, missingRelation: true),
        ];

        $consolidation = $this->consolidationFor(
            app(NutritionCalculationService::class)->consolidateContributions($contributions),
            101,
        );

        $this->assertNull($consolidation['total_numerator']);
        $this->assertNull($consolidation['total_denominator']);
        $this->assertFalse($consolidation['is_complete']);
        $this->assertTrue($consolidation['has_missing_relation']);
        $this->assertSame([], $consolidation['qualitative_states']);
    }

    public function test_post_persists_the_complete_snapshot_and_normalizes_complete_and_incomplete_totals_for_100g(): void
    {
        $energy = $this->createNutrient('Energia da fonte', 'kcal');
        $carbohydrate = $this->createNutrient('Carboidrato', 'g');
        $unavailable = $this->createNutrient('Componente indisponível', 'mg');
        $firstFood = $this->createFood('SNAPSHOT-FIRST');
        $secondFood = $this->createFood('SNAPSHOT-SECOND');
        $thirdFood = $this->createFood('SNAPSHOT-THIRD');
        $energyRelations = [
            $this->createFoodNutrient($firstFood, $energy, 'EXACT', '100.00'),
            $this->createFoodNutrient($secondFood, $energy, 'EXACT', '200.00'),
            $this->createFoodNutrient($thirdFood, $energy, 'EXACT', '0.00'),
        ];
        $carbohydrateExact = $this->createFoodNutrient($firstFood, $carbohydrate, 'EXACT', '12.50');
        $carbohydrateTrace = $this->createFoodNutrient($secondFood, $carbohydrate, 'TRACE');
        $unavailableAbsent = $this->createFoodNutrient($firstFood, $unavailable, 'ABSENT');
        $unavailableReevaluation = $this->createFoodNutrient($thirdFood, $unavailable, 'REEVALUATION');
        $recipe = $this->createRecipe([
            ['food_id' => $firstFood->id, 'quantity' => '100', 'unit' => 'g', 'position' => 1],
            ['food_id' => $secondFood->id, 'quantity' => '50', 'unit' => 'g', 'position' => 2],
            ['food_id' => $thirdFood->id, 'quantity' => '25', 'unit' => 'g', 'position' => 3],
        ], '300', 'g');
        $ingredients = $recipe->ingredients()->get();
        $sourceModels = [
            $recipe,
            ...$ingredients,
            $firstFood,
            $secondFood,
            $thirdFood,
            $energy,
            $carbohydrate,
            $unavailable,
            ...$energyRelations,
            $carbohydrateExact,
            $carbohydrateTrace,
            $unavailableAbsent,
            $unavailableReevaluation,
        ];
        $sourceSnapshots = collect($sourceModels)
            ->map(fn (Model $model): array => $model->getRawOriginal())
            ->all();

        $response = $this->postJson("/api/recipes/{$recipe->id}/nutrition-calculations");

        $response->assertSuccessful();
        $data = $response->json('data');

        $this->assertIsArray($data);
        $this->assertSame($recipe->id, $data['recipe_id']);
        $this->assertSame('nutrition-calculation-v1', $data['rules_version']);
        $this->assertSame(['value' => '300', 'unit' => 'g'], $data['yield']);
        $this->assertSame([
            [
                'recipe_ingredient_id' => $ingredients[0]->id,
                'position' => 1,
                'quantity' => '100',
                'unit' => 'g',
                'food' => [
                    'id' => $firstFood->id,
                    'source' => 'TACO',
                    'source_code' => $firstFood->source_code,
                ],
            ],
            [
                'recipe_ingredient_id' => $ingredients[1]->id,
                'position' => 2,
                'quantity' => '50',
                'unit' => 'g',
                'food' => [
                    'id' => $secondFood->id,
                    'source' => 'TACO',
                    'source_code' => $secondFood->source_code,
                ],
            ],
            [
                'recipe_ingredient_id' => $ingredients[2]->id,
                'position' => 3,
                'quantity' => '25',
                'unit' => 'g',
                'food' => [
                    'id' => $thirdFood->id,
                    'source' => 'TACO',
                    'source_code' => $thirdFood->source_code,
                ],
            ],
        ], $data['ingredients']);
        $this->assertCount(Nutrient::query()->count(), $data['nutrients']);

        $energyPayload = $this->nutrientPayloadFor($data['nutrients'], $energy->id);
        $this->assertSame(['id' => $energy->id, 'name' => $energy->name, 'unit' => 'kcal'], $energyPayload['nutrient']);
        $this->assertSame([
            'value' => ['numerator' => '200', 'denominator' => '1'],
            'complete' => true,
            'qualitative_states' => [],
            'has_missing_relation' => false,
        ], $energyPayload['total']);
        $this->assertSame([
            'basis' => '100',
            'unit' => 'g',
            'value' => ['numerator' => '200', 'denominator' => '3'],
            'complete' => true,
            'qualitative_states' => [],
            'has_missing_relation' => false,
        ], $energyPayload['reference']);
        $this->assertSame('0', $energyPayload['contributions'][2]['value']['numerator']);
        $this->assertSame('1', $energyPayload['contributions'][2]['value']['denominator']);

        $carbohydratePayload = $this->nutrientPayloadFor($data['nutrients'], $carbohydrate->id);
        $this->assertSame([
            'value' => ['numerator' => '25', 'denominator' => '2'],
            'complete' => false,
            'qualitative_states' => ['TRACE'],
            'has_missing_relation' => true,
        ], $carbohydratePayload['total']);
        $this->assertSame([
            'basis' => '100',
            'unit' => 'g',
            'value' => ['numerator' => '25', 'denominator' => '6'],
            'complete' => false,
            'qualitative_states' => ['TRACE'],
            'has_missing_relation' => true,
        ], $carbohydratePayload['reference']);
        $this->assertSame($carbohydrateExact->id, $carbohydratePayload['contributions'][0]['food_nutrient_id']);
        $this->assertSame('12.50', $carbohydratePayload['contributions'][0]['source_amount_per_100g']);
        $this->assertSame('TRACE', $carbohydratePayload['contributions'][1]['status']);
        $this->assertTrue($carbohydratePayload['contributions'][2]['missing_relation']);

        $unavailablePayload = $this->nutrientPayloadFor($data['nutrients'], $unavailable->id);
        $this->assertSame([
            'value' => null,
            'complete' => false,
            'qualitative_states' => ['ABSENT', 'REEVALUATION'],
            'has_missing_relation' => true,
        ], $unavailablePayload['total']);
        $this->assertSame([
            'basis' => '100',
            'unit' => 'g',
            'value' => null,
            'complete' => false,
            'qualitative_states' => ['ABSENT', 'REEVALUATION'],
            'has_missing_relation' => true,
        ], $unavailablePayload['reference']);

        $this->assertDatabaseHas('nutrition_calculations', [
            'id' => $data['id'],
            'recipe_id' => $recipe->id,
            'yield_value' => '300',
            'yield_unit' => 'g',
            'rules_version' => 'nutrition-calculation-v1',
        ]);
        $this->assertDatabaseCount('nutrition_calculation_ingredients', 3);
        $this->assertDatabaseCount('nutrition_calculation_nutrients', Nutrient::query()->count());
        $this->assertDatabaseCount(
            'nutrition_calculation_contributions',
            $ingredients->count() * Nutrient::query()->count(),
        );

        foreach ($sourceModels as $index => $model) {
            $this->assertEquals($sourceSnapshots[$index], $model->fresh()->getRawOriginal());
        }
    }

    public function test_post_uses_the_persisted_ml_yield_and_preserves_a_repeating_reference_exactly(): void
    {
        $nutrient = $this->createNutrient('Referência periódica');
        $food = $this->createFood('REFERENCE-ML');
        $this->createFoodNutrient($food, $nutrient, 'EXACT', '1.00');
        $recipe = $this->createRecipe([
            ['food_id' => $food->id, 'quantity' => '1', 'unit' => 'g', 'position' => 1],
        ], '3', 'ml');

        $response = $this->postJson("/api/recipes/{$recipe->id}/nutrition-calculations");

        $response->assertSuccessful();
        $payload = $this->nutrientPayloadFor($response->json('data.nutrients'), $nutrient->id);

        $this->assertSame(['value' => '3', 'unit' => 'ml'], $response->json('data.yield'));
        $this->assertSame(['numerator' => '1', 'denominator' => '100'], $payload['total']['value']);
        $this->assertSame('100', $payload['reference']['basis']);
        $this->assertSame('ml', $payload['reference']['unit']);
        $this->assertSame(['numerator' => '1', 'denominator' => '3'], $payload['reference']['value']);
        $this->assertTrue($payload['reference']['complete']);
    }

    public function test_each_post_creates_a_new_preserved_execution_without_a_functional_payload(): void
    {
        $nutrient = $this->createNutrient('Nova execução');
        $food = $this->createFood('NEW-EXECUTION');
        $this->createFoodNutrient($food, $nutrient, 'EXACT', '5.00');
        $recipe = $this->createRecipe([
            ['food_id' => $food->id, 'quantity' => '10', 'unit' => 'g', 'position' => 1],
        ]);
        $initialCount = DB::table('nutrition_calculations')->count();

        $first = $this->postJson("/api/recipes/{$recipe->id}/nutrition-calculations")->assertSuccessful();
        $second = $this->postJson("/api/recipes/{$recipe->id}/nutrition-calculations")->assertSuccessful();

        $this->assertNotSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame('nutrition-calculation-v1', $first->json('data.rules_version'));
        $this->assertSame('nutrition-calculation-v1', $second->json('data.rules_version'));
        $this->assertSame($initialCount + 2, DB::table('nutrition_calculations')->count());
    }

    public function test_post_rolls_back_the_entire_aggregate_when_a_child_fails_during_persistence(): void
    {
        $nutrient = $this->createNutrient('Rollback integral');
        $food = $this->createFood('ROLLBACK');
        $this->createFoodNutrient($food, $nutrient, 'EXACT', '10.00');
        $recipe = $this->createRecipe([
            ['food_id' => $food->id, 'quantity' => '10', 'unit' => 'g', 'position' => 1],
        ]);
        $tables = [
            'nutrition_calculations',
            'nutrition_calculation_ingredients',
            'nutrition_calculation_nutrients',
            'nutrition_calculation_contributions',
            'nutrition_calculation_nutrient_states',
        ];
        $countsBefore = collect($tables)->mapWithKeys(
            fn (string $table): array => [$table => DB::table($table)->count()],
        );
        $eventName = 'eloquent.creating: '.NutritionCalculationNutrient::class;
        Event::listen($eventName, static function (): never {
            throw new RuntimeException('Falha de persistência provocada pelo teste.');
        });
        $this->withoutExceptionHandling();

        try {
            $this->postJson("/api/recipes/{$recipe->id}/nutrition-calculations");
            $this->fail('A falha provocada durante a persistência não foi propagada.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha de persistência provocada pelo teste.', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }

        foreach ($countsBefore as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
    }

    private function createFood(string $label): Food
    {
        $sourceCode = $label.'-'.str()->uuid()->toString();

        return Food::query()->create([
            'name' => "Alimento {$label}",
            'category' => 'Categoria de teste',
            'source' => 'TACO',
            'source_code' => $sourceCode,
        ]);
    }

    private function createNutrient(string $label, string $unit = 'g'): Nutrient
    {
        return Nutrient::query()->create([
            'name' => $label.' '.str()->uuid()->toString(),
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
     * @param  list<array{food_id: int, quantity: string, unit: string, position: int}>  $ingredients
     */
    private function createRecipe(
        array $ingredients,
        string $yieldValue = '1000',
        string $yieldUnit = 'g',
    ): Recipe {
        $recipe = Recipe::factory()->create([
            'name' => 'Receita para cálculo de contribuições',
            'yield_value' => $yieldValue,
            'yield_unit' => $yieldUnit,
        ]);
        $recipe->ingredients()->createMany($ingredients);

        return $recipe;
    }

    /**
     * @param  list<array{
     *     recipe_ingredient_id: int,
     *     food_id: int,
     *     nutrient_id: int,
     *     food_nutrient_id: ?int,
     *     input_status: ?string,
     *     input_amount_value: ?string,
     *     is_missing_relation: bool,
     *     value_numerator: ?string,
     *     value_denominator: ?string
     * }>  $contributions
     * @return array{
     *     recipe_ingredient_id: int,
     *     food_id: int,
     *     nutrient_id: int,
     *     food_nutrient_id: ?int,
     *     input_status: ?string,
     *     input_amount_value: ?string,
     *     is_missing_relation: bool,
     *     value_numerator: ?string,
     *     value_denominator: ?string
     * }
     */
    private function contributionFor(array $contributions, int $ingredientId, int $nutrientId): array
    {
        $contribution = collect($contributions)->first(
            fn (array $item): bool => $item['recipe_ingredient_id'] === $ingredientId
                && $item['nutrient_id'] === $nutrientId,
        );

        $this->assertNotNull($contribution);

        return $contribution;
    }

    /**
     * @return array{
     *     recipe_ingredient_id: int,
     *     food_id: int,
     *     nutrient_id: int,
     *     food_nutrient_id: ?int,
     *     input_status: ?string,
     *     input_amount_value: ?string,
     *     is_missing_relation: bool,
     *     value_numerator: ?string,
     *     value_denominator: ?string
     * }
     */
    private function consolidationContribution(
        int $nutrientId,
        ?string $status,
        ?string $numerator = null,
        ?string $denominator = null,
        bool $missingRelation = false,
    ): array {
        return [
            'recipe_ingredient_id' => 1,
            'food_id' => 1,
            'nutrient_id' => $nutrientId,
            'food_nutrient_id' => $missingRelation ? null : 1,
            'input_status' => $status,
            'input_amount_value' => $status === 'EXACT' ? '1.00' : null,
            'is_missing_relation' => $missingRelation,
            'value_numerator' => $numerator,
            'value_denominator' => $denominator,
        ];
    }

    /**
     * @param  list<array{
     *     nutrient_id: int,
     *     total_numerator: ?string,
     *     total_denominator: ?string,
     *     is_complete: bool,
     *     has_missing_relation: bool,
     *     qualitative_states: list<string>
     * }>  $consolidations
     * @return array{
     *     nutrient_id: int,
     *     total_numerator: ?string,
     *     total_denominator: ?string,
     *     is_complete: bool,
     *     has_missing_relation: bool,
     *     qualitative_states: list<string>
     * }
     */
    private function consolidationFor(array $consolidations, int $nutrientId): array
    {
        $consolidation = collect($consolidations)->first(
            fn (array $item): bool => $item['nutrient_id'] === $nutrientId,
        );

        $this->assertNotNull($consolidation);

        return $consolidation;
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
