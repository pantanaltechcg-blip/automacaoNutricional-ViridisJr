<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use App\Models\Food;
use App\Models\ManualFoodRegistration;
use App\Models\NutritionCalculation;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ExtendedScreensTest extends TestCase
{
    use DatabaseTransactions;

    public function test_recipe_edit_screen_loads_existing_recipe_and_returns_404_when_missing(): void
    {
        $this->get('/receitas/999999')->assertStatus(404);

        $recipe = Recipe::factory()->create([
            'name' => 'Receita Existente',
            'yield_value' => '300',
            'yield_unit' => 'ml',
        ]);

        $response = $this->get("/receitas/{$recipe->id}");
        $response->assertStatus(200);
        $response->assertSee('Receita Existente');
    }

    public function test_calculation_screen_renders_and_returns_404_when_missing(): void
    {
        $this->get('/calculos/999999')->assertStatus(404);

        $recipe = Recipe::factory()->create();
        $calc = NutritionCalculation::query()->create([
            'recipe_id' => $recipe->id,
            'yield_value' => '100',
            'yield_unit' => 'g',
        ]);

        $response = $this->get("/calculos/{$calc->id}");
        $response->assertStatus(200);
        $response->assertSee("Execução #{$calc->id}");
        $response->assertSee('Nutrientes calculados (razão exata)');
    }

    public function test_manual_ingredient_edit_screen_returns_404_for_non_manual_food_and_200_for_manual(): void
    {
        $tacoFood = Food::query()->create([
            'name' => 'Arroz TACO',
            'source' => 'TACO',
            'source_code' => 'T-999',
        ]);
        $this->get("/ingredientes/{$tacoFood->id}")->assertStatus(404);

        $manualFood = Food::query()->create([
            'name' => 'Ingrediente Manual Teste',
            'source' => 'Fabricante',
            'source_code' => 'FAB-999',
        ]);
        ManualFoodRegistration::query()->create([
            'food_id' => $manualFood->id,
            'reference_unit' => 'g',
            'current_version_number' => 1,
            'is_active' => true,
        ]);

        $response = $this->get("/ingredientes/{$manualFood->id}");
        $response->assertStatus(200);
        $response->assertSee('Editar Ingrediente Manual');
        $response->assertSee('Motivo da alteração');
        $response->assertSee('Status e Versões');
    }
}
