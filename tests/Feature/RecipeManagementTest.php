<?php

namespace Tests\Feature;

use App\Models\Food;
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

    private function createFood(string $sourceCode): Food
    {
        return Food::query()->create([
            'name' => "Alimento {$sourceCode}",
            'category' => 'Categoria de teste',
            'source' => 'TACO',
            'source_code' => $sourceCode,
        ]);
    }
}
