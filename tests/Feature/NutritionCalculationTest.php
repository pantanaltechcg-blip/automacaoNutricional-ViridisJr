<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Food;
use App\Models\FoodNutrient;
use App\Models\Nutrient;
use App\Models\Recipe;
use App\Services\NutritionCalculationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
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
    private function createRecipe(array $ingredients): Recipe
    {
        $recipe = Recipe::factory()->create([
            'name' => 'Receita para cálculo de contribuições',
            'yield_value' => '1000',
            'yield_unit' => 'g',
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
}
