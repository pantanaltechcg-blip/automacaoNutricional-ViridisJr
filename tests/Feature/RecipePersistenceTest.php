<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Recipe;
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
