<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\RecipeService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecipePersistenceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rejects_recipe_without_ingredients_without_persisting_recipe(): void
    {
        $recipeCount = Recipe::query()->count();
        $food = $this->createFood();
        $payload = $this->validPayload($food);
        $payload['ingredients'] = [];

        $this->postJson('/api/recipes', $payload)
            ->assertJsonValidationErrors('ingredients');

        $this->assertSame($recipeCount, Recipe::query()->count());
    }

    public function test_rejects_missing_required_recipe_fields_without_persisting_recipe(): void
    {
        $recipeCount = Recipe::query()->count();

        $this->postJson('/api/recipes', [])
            ->assertJsonValidationErrors(['name', 'yield_value', 'yield_unit', 'ingredients']);

        $this->assertSame($recipeCount, Recipe::query()->count());
    }

    public function test_rejects_missing_required_ingredient_fields_without_persisting_recipe(): void
    {
        $recipeCount = Recipe::query()->count();

        $this->postJson('/api/recipes', [
            'name' => 'Receita inválida',
            'yield_value' => '1',
            'yield_unit' => 'g',
            'ingredients' => [[]],
        ])->assertJsonValidationErrors([
            'ingredients.0.food_id',
            'ingredients.0.quantity',
            'ingredients.0.unit',
            'ingredients.0.position',
        ]);

        $this->assertSame($recipeCount, Recipe::query()->count());
    }

    #[DataProvider('emptyRequiredStringProvider')]
    public function test_rejects_empty_required_string_after_http_normalization(
        string $field,
        string $value,
        string $errorKey,
    ): void {
        $recipeCount = Recipe::query()->count();
        $food = $this->createFood();
        $payload = $this->validPayload($food);
        data_set($payload, $field, $value);

        $this->postJson('/api/recipes', $payload)
            ->assertJsonValidationErrors($errorKey);

        $this->assertSame($recipeCount, Recipe::query()->count());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function emptyRequiredStringProvider(): array
    {
        return [
            'empty name' => ['name', '', 'name'],
            'spaces-only yield value' => ['yield_value', '   ', 'yield_value'],
            'empty yield unit' => ['yield_unit', '', 'yield_unit'],
            'spaces-only quantity' => ['ingredients.0.quantity', '   ', 'ingredients.0.quantity'],
            'empty ingredient unit' => ['ingredients.0.unit', '', 'ingredients.0.unit'],
        ];
    }

    public function test_rejects_nonexistent_food_without_persisting_recipe(): void
    {
        $recipeCount = Recipe::query()->count();
        $food = $this->createFood();
        $payload = $this->validPayload($food);
        $payload['ingredients'][0]['food_id'] = PHP_INT_MAX;

        $this->postJson('/api/recipes', $payload)
            ->assertJsonValidationErrors('ingredients.0.food_id');

        $this->assertSame($recipeCount, Recipe::query()->count());
    }

    #[DataProvider('invalidQuantityProvider')]
    public function test_rejects_invalid_quantity_without_persisting_recipe(mixed $quantity): void
    {
        $recipeCount = Recipe::query()->count();
        $food = $this->createFood();
        $payload = $this->validPayload($food);
        $payload['ingredients'][0]['quantity'] = $quantity;

        $this->postJson('/api/recipes', $payload)
            ->assertJsonValidationErrors('ingredients.0.quantity');

        $this->assertSame($recipeCount, Recipe::query()->count());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidQuantityProvider(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'exponent' => ['1e3'],
            'incomplete fraction' => ['1.'],
            'JSON number' => [1.25],
        ];
    }

    public function test_accepts_positive_fractional_quantity(): void
    {
        $food = $this->createFood();
        $payload = $this->validPayload($food);
        $payload['ingredients'][0]['quantity'] = '0.000125';

        $response = $this->postJson('/api/recipes', $payload)
            ->assertSuccessful();

        $this->assertSame('0.000125', $response->json('data.ingredients.0.quantity'));
        $this->assertDatabaseHas('recipe_ingredients', [
            'recipe_id' => $response->json('data.id'),
            'quantity' => '0.000125',
        ]);
    }

    #[DataProvider('invalidIngredientUnitProvider')]
    public function test_rejects_invalid_ingredient_unit_without_persisting_recipe(string $unit): void
    {
        $recipeCount = Recipe::query()->count();
        $food = $this->createFood();
        $payload = $this->validPayload($food);
        $payload['ingredients'][0]['unit'] = $unit;

        $this->postJson('/api/recipes', $payload)
            ->assertJsonValidationErrors('ingredients.0.unit');

        $this->assertSame($recipeCount, Recipe::query()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIngredientUnitProvider(): array
    {
        return [
            'kilograms' => ['kg'],
            'milliliters' => ['ml'],
            'uppercase grams' => ['G'],
        ];
    }

    #[DataProvider('invalidYieldValueProvider')]
    public function test_rejects_invalid_yield_value_without_persisting_recipe(mixed $yieldValue): void
    {
        $recipeCount = Recipe::query()->count();
        $food = $this->createFood();
        $payload = $this->validPayload($food);
        $payload['yield_value'] = $yieldValue;

        $this->postJson('/api/recipes', $payload)
            ->assertJsonValidationErrors('yield_value');

        $this->assertSame($recipeCount, Recipe::query()->count());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidYieldValueProvider(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'exponent' => ['1e3'],
            'incomplete fraction' => ['1.'],
            'JSON number' => [1.25],
        ];
    }

    #[DataProvider('invalidYieldUnitProvider')]
    public function test_rejects_invalid_yield_unit_without_persisting_recipe(string $yieldUnit): void
    {
        $recipeCount = Recipe::query()->count();
        $food = $this->createFood();
        $payload = $this->validPayload($food);
        $payload['yield_unit'] = $yieldUnit;

        $this->postJson('/api/recipes', $payload)
            ->assertJsonValidationErrors('yield_unit');

        $this->assertSame($recipeCount, Recipe::query()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidYieldUnitProvider(): array
    {
        return [
            'kilograms' => ['kg'],
            'uppercase grams' => ['G'],
            'uppercase milliliters' => ['ML'],
        ];
    }

    #[DataProvider('nonPositivePositionProvider')]
    public function test_rejects_non_positive_position_without_persisting_recipe(int $position): void
    {
        $recipeCount = Recipe::query()->count();
        $food = $this->createFood();
        $payload = $this->validPayload($food);
        $payload['ingredients'][0]['position'] = $position;

        $this->postJson('/api/recipes', $payload)
            ->assertJsonValidationErrors('ingredients.0.position');

        $this->assertSame($recipeCount, Recipe::query()->count());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function nonPositivePositionProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    public function test_rejects_repeated_positions_without_persisting_recipe(): void
    {
        $recipeCount = Recipe::query()->count();
        $firstFood = $this->createFood('RECIPE-PERSISTENCE-FIRST');
        $secondFood = $this->createFood('RECIPE-PERSISTENCE-SECOND');
        $payload = $this->validPayload($firstFood);
        $payload['ingredients'][] = [
            'food_id' => $secondFood->id,
            'quantity' => '2',
            'unit' => 'g',
            'position' => '1',
        ];

        $this->postJson('/api/recipes', $payload)
            ->assertJsonValidationErrors('ingredients.1.position');

        $this->assertSame($recipeCount, Recipe::query()->count());
    }

    public function test_preserves_decimal_precision_without_fixed_scale(): void
    {
        $food = $this->createFood();

        $response = $this->postJson('/api/recipes', [
            'name' => 'Receita de alta precisão',
            'yield_value' => '12345678901234567890.12345678901234567890',
            'yield_unit' => 'g',
            'ingredients' => [[
                'food_id' => $food->id,
                'quantity' => '0.12345678901234567890123456789',
                'unit' => 'g',
                'position' => 1,
            ]],
        ])->assertSuccessful();

        $recipe = Recipe::query()->with('ingredients')->findOrFail($response->json('data.id'));
        $this->assertSame('12345678901234567890.12345678901234567890', $recipe->yield_value);
        $this->assertSame('0.12345678901234567890123456789', $recipe->ingredients->first()->quantity);
        $this->assertSame($recipe->yield_value, $response->json('data.yield_value'));
        $this->assertSame($recipe->ingredients->first()->quantity, $response->json('data.ingredients.0.quantity'));
    }

    public function test_service_rolls_back_recipe_when_an_ingredient_cannot_be_persisted(): void
    {
        $recipeCount = Recipe::query()->count();
        $firstFood = $this->createFood('RECIPE-ROLLBACK-FIRST');
        $secondFood = $this->createFood('RECIPE-ROLLBACK-SECOND');
        $payload = $this->validPayload($firstFood);
        $payload['ingredients'][] = [
            'food_id' => $secondFood->id,
            'quantity' => '2',
            'unit' => 'g',
            'position' => 1,
        ];

        try {
            app(RecipeService::class)->create($payload);
            $this->fail('A constraint de posição deveria impedir o segundo ingrediente.');
        } catch (QueryException) {
            $this->assertSame($recipeCount, Recipe::query()->count());
        }
    }

    public function test_recipe_creation_does_not_create_update_or_delete_food(): void
    {
        $food = $this->createFood();
        $foodCount = Food::query()->count();
        $foodAttributes = $food->getAttributes();

        $this->postJson('/api/recipes', $this->validPayload($food))
            ->assertSuccessful();

        $this->assertSame($foodCount, Food::query()->count());
        $freshFood = $food->fresh();
        foreach ($foodAttributes as $attribute => $value) {
            $this->assertSame($value, $freshFood->getRawOriginal($attribute));
        }
    }

    public function test_update_rolls_back_to_the_exact_previous_state_when_an_ingredient_fails(): void
    {
        $firstFood = $this->createFood('RECIPE-UPDATE-ROLLBACK-FIRST');
        $secondFood = $this->createFood('RECIPE-UPDATE-ROLLBACK-SECOND');
        $recipe = $this->createRecipe([
            ['food_id' => $firstFood->id, 'quantity' => '10.25', 'unit' => 'g', 'position' => 1],
            ['food_id' => $secondFood->id, 'quantity' => '20.5', 'unit' => 'g', 'position' => 3],
        ]);
        $previousRecipe = $recipe->only(['name', 'yield_value', 'yield_unit']);
        $previousIngredients = $this->ingredientSignature($recipe);

        try {
            app(RecipeService::class)->update($recipe, [
                'name' => 'Estado que deve ser revertido',
                'yield_value' => '999.999',
                'yield_unit' => 'ml',
                'ingredients' => [
                    ['food_id' => $firstFood->id, 'quantity' => '1', 'unit' => 'g', 'position' => 1],
                    ['food_id' => $secondFood->id, 'quantity' => '2', 'unit' => 'g', 'position' => 1],
                ],
            ]);
            $this->fail('A constraint de posição deveria impedir a substituição.');
        } catch (QueryException) {
            $recipe->refresh();
            $this->assertSame($previousRecipe, $recipe->only(['name', 'yield_value', 'yield_unit']));
            $this->assertSame($previousIngredients, $this->ingredientSignature($recipe));
        }
    }

    public function test_reordering_changes_only_positions(): void
    {
        $firstFood = $this->createFood('RECIPE-UPDATE-ORDER-FIRST');
        $secondFood = $this->createFood('RECIPE-UPDATE-ORDER-SECOND');
        $recipe = $this->createRecipe([
            ['food_id' => $firstFood->id, 'quantity' => '10.250', 'unit' => 'g', 'position' => 1],
            ['food_id' => $secondFood->id, 'quantity' => '20.500', 'unit' => 'g', 'position' => 3],
        ], '500.125', 'ml');

        $this->putJson("/api/recipes/{$recipe->id}", [
            'name' => 'Receita original',
            'yield_value' => '500.125',
            'yield_unit' => 'ml',
            'ingredients' => [
                ['food_id' => $firstFood->id, 'quantity' => '10.250', 'unit' => 'g', 'position' => 8],
                ['food_id' => $secondFood->id, 'quantity' => '20.500', 'unit' => 'g', 'position' => 2],
            ],
        ])->assertSuccessful();

        $recipe->refresh();
        $this->assertSame('500.125', $recipe->yield_value);
        $this->assertSame('ml', $recipe->yield_unit);
        $this->assertSame([
            "{$secondFood->id}|20.500|g|2",
            "{$firstFood->id}|10.250|g|8",
        ], $this->ingredientSignature($recipe));
    }

    public function test_omitted_ingredient_is_removed_without_changing_any_food(): void
    {
        $keptFood = $this->createFood('RECIPE-UPDATE-KEEP');
        $omittedFood = $this->createFood('RECIPE-UPDATE-OMIT');
        $recipe = $this->createRecipe([
            ['food_id' => $keptFood->id, 'quantity' => '10', 'unit' => 'g', 'position' => 1],
            ['food_id' => $omittedFood->id, 'quantity' => '20', 'unit' => 'g', 'position' => 2],
        ]);
        $foodCount = Food::query()->count();
        $keptFoodAttributes = $keptFood->getAttributes();
        $omittedFoodAttributes = $omittedFood->getAttributes();

        $this->putJson("/api/recipes/{$recipe->id}", [
            'name' => 'Receita sem ingrediente omitido',
            'yield_value' => '30',
            'yield_unit' => 'g',
            'ingredients' => [[
                'food_id' => $keptFood->id,
                'quantity' => '10',
                'unit' => 'g',
                'position' => 1,
            ]],
        ])->assertSuccessful();

        $this->assertSame(1, $recipe->ingredients()->count());
        $this->assertFalse($recipe->ingredients()->where('food_id', $omittedFood->id)->exists());
        $this->assertSame($foodCount, Food::query()->count());
        $this->assertFoodUnchanged($keptFood, $keptFoodAttributes);
        $this->assertFoodUnchanged($omittedFood, $omittedFoodAttributes);
    }

    public function test_update_allows_the_same_food_in_different_positions_without_upsert(): void
    {
        $food = $this->createFood('RECIPE-UPDATE-REPEATED-FOOD');
        $recipe = $this->createRecipe([
            ['food_id' => $food->id, 'quantity' => '1', 'unit' => 'g', 'position' => 1],
        ]);

        $this->putJson("/api/recipes/{$recipe->id}", [
            'name' => 'Receita com alimento repetido',
            'yield_value' => '3',
            'yield_unit' => 'g',
            'ingredients' => [
                ['food_id' => $food->id, 'quantity' => '1', 'unit' => 'g', 'position' => 1],
                ['food_id' => $food->id, 'quantity' => '2', 'unit' => 'g', 'position' => 4],
            ],
        ])->assertSuccessful();

        $this->assertSame(2, $recipe->ingredients()->where('food_id', $food->id)->count());
        $this->assertSame([1, 4], $recipe->ingredients()->pluck('position')->all());
    }

    public function test_update_preserves_decimal_strings_without_fixed_scale(): void
    {
        $food = $this->createFood('RECIPE-UPDATE-PRECISION');
        $recipe = $this->createRecipe([
            ['food_id' => $food->id, 'quantity' => '1', 'unit' => 'g', 'position' => 1],
        ]);

        $response = $this->putJson("/api/recipes/{$recipe->id}", [
            'name' => 'Receita atualizada com precisão',
            'yield_value' => '12345678901234567890.12345678901234567890',
            'yield_unit' => 'g',
            'ingredients' => [[
                'food_id' => $food->id,
                'quantity' => '0.12345678901234567890123456789',
                'unit' => 'g',
                'position' => 1,
            ]],
        ])->assertSuccessful();

        $recipe->refresh();
        $this->assertSame('12345678901234567890.12345678901234567890', $recipe->yield_value);
        $this->assertSame('0.12345678901234567890123456789', $recipe->ingredients->first()->quantity);
        $this->assertSame($recipe->yield_value, $response->json('data.yield_value'));
        $this->assertSame($recipe->ingredients->first()->quantity, $response->json('data.ingredients.0.quantity'));
    }

    private function createFood(string $sourceCode = 'RECIPE-PERSISTENCE'): Food
    {
        return Food::query()->create([
            'name' => "Alimento {$sourceCode}",
            'category' => 'Categoria de teste',
            'source' => 'TACO',
            'source_code' => $sourceCode,
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
            'name' => 'Receita original',
            'yield_value' => $yieldValue,
            'yield_unit' => $yieldUnit,
        ]);
        $recipe->ingredients()->createMany($ingredients);

        return $recipe;
    }

    /**
     * @return list<string>
     */
    private function ingredientSignature(Recipe $recipe): array
    {
        return $recipe->ingredients()->get()
            ->map(fn (RecipeIngredient $ingredient): string => implode('|', [
                $ingredient->food_id,
                $ingredient->quantity,
                $ingredient->unit,
                $ingredient->position,
            ]))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $expectedAttributes
     */
    private function assertFoodUnchanged(Food $food, array $expectedAttributes): void
    {
        $freshFood = $food->fresh();
        foreach ($expectedAttributes as $attribute => $value) {
            $this->assertSame($value, $freshFood->getRawOriginal($attribute));
        }
    }

    /**
     * @return array{
     *     name: string,
     *     yield_value: string,
     *     yield_unit: string,
     *     ingredients: list<array{food_id: int, quantity: string, unit: string, position: int}>
     * }
     */
    private function validPayload(Food $food): array
    {
        return [
            'name' => 'Receita válida',
            'yield_value' => '10.25',
            'yield_unit' => 'g',
            'ingredients' => [[
                'food_id' => $food->id,
                'quantity' => '1.125',
                'unit' => 'g',
                'position' => 1,
            ]],
        ];
    }
}
