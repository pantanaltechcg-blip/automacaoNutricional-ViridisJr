<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RecipeManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_post_creates_recipe_with_one_ingredient_and_returns_the_contract(): void
    {
        $food = $this->createFood('RECIPE-HTTP-ONE');

        $response = $this->postJson('/api/recipes', [
            'name' => 'Receita de exemplo',
            'yield_value' => '850.125',
            'yield_unit' => 'g',
            'ingredients' => [[
                'food_id' => $food->id,
                'quantity' => '125.5',
                'unit' => 'g',
                'position' => 1,
            ]],
        ])->assertSuccessful();

        $recipeId = $response->json('data.id');
        $this->assertIsInt($recipeId);
        $response->assertExactJson([
            'data' => [
                'id' => $recipeId,
                'name' => 'Receita de exemplo',
                'yield_value' => '850.125',
                'yield_unit' => 'g',
                'ingredients' => [[
                    'food_id' => $food->id,
                    'quantity' => '125.5',
                    'unit' => 'g',
                    'position' => 1,
                ]],
            ],
        ]);
        $this->assertDatabaseHas('recipes', [
            'id' => $recipeId,
            'name' => 'Receita de exemplo',
            'yield_value' => '850.125',
            'yield_unit' => 'g',
        ]);
        $this->assertDatabaseHas('recipe_ingredients', [
            'recipe_id' => $recipeId,
            'food_id' => $food->id,
            'quantity' => '125.5',
            'unit' => 'g',
            'position' => 1,
        ]);
    }

    public function test_post_returns_multiple_ingredients_by_position_and_preserves_ml_yield(): void
    {
        $firstFood = $this->createFood('RECIPE-HTTP-FIRST');
        $secondFood = $this->createFood('RECIPE-HTTP-SECOND');

        $response = $this->postJson('/api/recipes', [
            'name' => 'Receita líquida',
            'yield_value' => '900.5001',
            'yield_unit' => 'ml',
            'ingredients' => [
                [
                    'food_id' => $secondFood->id,
                    'quantity' => '0.375',
                    'unit' => 'g',
                    'position' => 2,
                ],
                [
                    'food_id' => $firstFood->id,
                    'quantity' => '125.50001',
                    'unit' => 'g',
                    'position' => 1,
                ],
            ],
        ])->assertSuccessful();

        $this->assertSame('900.5001', $response->json('data.yield_value'));
        $this->assertSame('ml', $response->json('data.yield_unit'));
        $this->assertSame(
            [$firstFood->id, $secondFood->id],
            $response->json('data.ingredients.*.food_id'),
        );
        $this->assertSame([1, 2], $response->json('data.ingredients.*.position'));
        $this->assertSame(
            ['125.50001', '0.375'],
            $response->json('data.ingredients.*.quantity'),
        );
        $this->assertIsString($response->json('data.yield_value'));
        $this->assertContainsOnly('string', $response->json('data.ingredients.*.quantity'));
        $this->assertNotSame('125.87501', $response->json('data.yield_value'));
    }

    public function test_post_uses_http_transport_normalization_without_changing_case_or_rounding(): void
    {
        $food = $this->createFood('RECIPE-HTTP-NORMALIZATION');

        $response = $this->postJson('/api/recipes', [
            'name' => ' ReCeItA Especial ',
            'yield_value' => ' 1.250 ',
            'yield_unit' => ' ml ',
            'ingredients' => [[
                'food_id' => $food->id,
                'quantity' => ' 0.3750 ',
                'unit' => ' g ',
                'position' => 1,
            ]],
        ])->assertSuccessful();

        $response
            ->assertJsonPath('data.name', 'ReCeItA Especial')
            ->assertJsonPath('data.yield_value', '1.250')
            ->assertJsonPath('data.yield_unit', 'ml')
            ->assertJsonPath('data.ingredients.0.quantity', '0.3750')
            ->assertJsonPath('data.ingredients.0.unit', 'g');
    }

    public function test_put_replaces_the_complete_recipe_and_returns_ingredients_by_position(): void
    {
        $foodA = $this->createFood('RECIPE-PUT-A');
        $foodB = $this->createFood('RECIPE-PUT-B');
        $foodC = $this->createFood('RECIPE-PUT-C');
        $foodD = $this->createFood('RECIPE-PUT-D');
        $recipe = $this->createRecipe([
            ['food_id' => $foodA->id, 'quantity' => '500', 'unit' => 'g', 'position' => 1],
            ['food_id' => $foodB->id, 'quantity' => '400', 'unit' => 'g', 'position' => 2],
            ['food_id' => $foodC->id, 'quantity' => '100', 'unit' => 'g', 'position' => 3],
        ]);

        $response = $this->putJson("/api/recipes/{$recipe->id}", [
            'name' => 'Receita atualizada',
            'yield_value' => '950.5001',
            'yield_unit' => 'ml',
            'ingredients' => [
                ['food_id' => $foodA->id, 'quantity' => '500', 'unit' => 'g', 'position' => 3],
                ['food_id' => $foodD->id, 'quantity' => '25.0001', 'unit' => 'g', 'position' => 5],
                ['food_id' => $foodC->id, 'quantity' => '125.5001', 'unit' => 'g', 'position' => 1],
            ],
        ])->assertSuccessful();

        $response->assertExactJson([
            'data' => [
                'id' => $recipe->id,
                'name' => 'Receita atualizada',
                'yield_value' => '950.5001',
                'yield_unit' => 'ml',
                'ingredients' => [
                    ['food_id' => $foodC->id, 'quantity' => '125.5001', 'unit' => 'g', 'position' => 1],
                    ['food_id' => $foodA->id, 'quantity' => '500', 'unit' => 'g', 'position' => 3],
                    ['food_id' => $foodD->id, 'quantity' => '25.0001', 'unit' => 'g', 'position' => 5],
                ],
            ],
        ]);
        $this->assertSame(3, $recipe->ingredients()->count());
        $this->assertFalse($recipe->ingredients()->where('food_id', $foodB->id)->exists());
    }

    public function test_put_changes_food_only_when_a_new_food_id_is_explicitly_sent(): void
    {
        $originalFood = $this->createFood('RECIPE-PUT-ORIGINAL');
        $replacementFood = $this->createFood('RECIPE-PUT-REPLACEMENT');
        $recipe = $this->createRecipe([
            ['food_id' => $originalFood->id, 'quantity' => '10.25', 'unit' => 'g', 'position' => 1],
        ]);

        $response = $this->putJson("/api/recipes/{$recipe->id}", [
            'name' => 'Receita com alimento trocado',
            'yield_value' => '20.5',
            'yield_unit' => 'g',
            'ingredients' => [[
                'food_id' => $replacementFood->id,
                'quantity' => '10.25',
                'unit' => 'g',
                'position' => 1,
            ]],
        ])->assertSuccessful();

        $response->assertJsonPath('data.ingredients.0.food_id', $replacementFood->id);
        $this->assertFalse($recipe->ingredients()->where('food_id', $originalFood->id)->exists());
        $this->assertTrue($recipe->ingredients()->where('food_id', $replacementFood->id)->exists());
    }

    public function test_put_rejects_an_empty_final_collection(): void
    {
        $food = $this->createFood('RECIPE-PUT-EMPTY');
        $recipe = $this->createRecipe([
            ['food_id' => $food->id, 'quantity' => '10', 'unit' => 'g', 'position' => 1],
        ]);

        $this->putJson("/api/recipes/{$recipe->id}", [
            'name' => 'Receita inválida',
            'yield_value' => '10',
            'yield_unit' => 'g',
            'ingredients' => [],
        ])->assertJsonValidationErrors('ingredients');

        $this->assertSame(1, $recipe->ingredients()->count());
    }

    public function test_put_rejects_an_incomplete_payload(): void
    {
        $food = $this->createFood('RECIPE-PUT-INCOMPLETE');
        $recipe = $this->createRecipe([
            ['food_id' => $food->id, 'quantity' => '10', 'unit' => 'g', 'position' => 1],
        ]);

        $this->putJson("/api/recipes/{$recipe->id}", [])
            ->assertJsonValidationErrors(['name', 'yield_value', 'yield_unit', 'ingredients']);

        $this->assertSame('Receita original', $recipe->fresh()->name);
        $this->assertSame(1, $recipe->ingredients()->count());
    }

    public function test_put_uses_the_same_http_normalization_as_post_without_rounding_or_unit_conversion(): void
    {
        $food = $this->createFood('RECIPE-PUT-NORMALIZATION');
        $recipe = $this->createRecipe([
            ['food_id' => $food->id, 'quantity' => '10', 'unit' => 'g', 'position' => 1],
        ]);

        $response = $this->putJson("/api/recipes/{$recipe->id}", [
            'name' => ' ReCeItA Atualizada ',
            'yield_value' => ' 1.2500 ',
            'yield_unit' => ' ml ',
            'ingredients' => [[
                'food_id' => $food->id,
                'quantity' => ' 0.37500 ',
                'unit' => ' g ',
                'position' => 7,
            ]],
        ])->assertSuccessful();

        $response
            ->assertJsonPath('data.name', 'ReCeItA Atualizada')
            ->assertJsonPath('data.yield_value', '1.2500')
            ->assertJsonPath('data.yield_unit', 'ml')
            ->assertJsonPath('data.ingredients.0.quantity', '0.37500')
            ->assertJsonPath('data.ingredients.0.unit', 'g')
            ->assertJsonPath('data.ingredients.0.position', 7);
    }

    public function test_get_returns_the_exact_recipe_contract_with_ingredients_ordered_by_position(): void
    {
        $firstFood = $this->createFood('RECIPE-GET-FIRST');
        $secondFood = $this->createFood('RECIPE-GET-SECOND');
        $recipe = $this->createRecipe([
            ['food_id' => $firstFood->id, 'quantity' => '1000.0001', 'unit' => 'g', 'position' => 5],
            ['food_id' => $secondFood->id, 'quantity' => '500.125', 'unit' => 'g', 'position' => 2],
        ]);
        $recipe->update([
            'name' => 'Produto reaberto',
            'yield_value' => '1680.5001',
            'yield_unit' => 'ml',
        ]);

        $response = $this->getJson("/api/recipes/{$recipe->id}")
            ->assertSuccessful();

        $response->assertExactJson([
            'data' => [
                'id' => $recipe->id,
                'name' => 'Produto reaberto',
                'yield_value' => '1680.5001',
                'yield_unit' => 'ml',
                'ingredients' => [
                    ['food_id' => $secondFood->id, 'quantity' => '500.125', 'unit' => 'g', 'position' => 2],
                    ['food_id' => $firstFood->id, 'quantity' => '1000.0001', 'unit' => 'g', 'position' => 5],
                ],
            ],
        ]);
        $this->assertIsString($response->json('data.yield_value'));
        $this->assertContainsOnly('string', $response->json('data.ingredients.*.quantity'));
    }

    public function test_get_fails_for_an_unknown_recipe_without_changing_records(): void
    {
        $food = $this->createFood('RECIPE-GET-NOT-FOUND');
        $recipe = $this->createRecipe([
            ['food_id' => $food->id, 'quantity' => '10.25', 'unit' => 'g', 'position' => 3],
        ]);
        $recipeAttributes = $recipe->only(['id', 'name', 'yield_value', 'yield_unit']);
        $ingredientAttributes = $recipe->ingredients()->firstOrFail()
            ->only(['id', 'recipe_id', 'food_id', 'quantity', 'unit', 'position']);
        $recipeCount = Recipe::query()->count();

        $this->getJson('/api/recipes/'.PHP_INT_MAX)
            ->assertClientError();

        $this->assertSame($recipeCount, Recipe::query()->count());
        $this->assertSame(
            $recipeAttributes,
            $recipe->fresh()->only(['id', 'name', 'yield_value', 'yield_unit']),
        );
        $this->assertSame(
            $ingredientAttributes,
            $recipe->ingredients()->firstOrFail()
                ->only(['id', 'recipe_id', 'food_id', 'quantity', 'unit', 'position']),
        );
    }

    private function createFood(string $sourceCode): Food
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
    private function createRecipe(array $ingredients): Recipe
    {
        $recipe = Recipe::factory()->create([
            'name' => 'Receita original',
            'yield_value' => '1000',
            'yield_unit' => 'g',
        ]);
        $recipe->ingredients()->createMany($ingredients);

        return $recipe;
    }
}
