<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use App\Models\Recipe;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NutritionTableScreenTest extends TestCase
{
    use DatabaseTransactions;

    public function test_returns_404_when_recipe_does_not_exist(): void
    {
        $this->get('/receitas/999999/tabela')->assertStatus(404);
    }

    public function test_renders_nutrition_table_screen_with_prototype_rows_and_unavailable_servings(): void
    {
        $recipe = Recipe::factory()->create([
            'name' => 'Receita Teste',
            'yield_value' => '500',
            'yield_unit' => 'g',
        ]);

        $response = $this->get("/receitas/{$recipe->id}/tabela");

        $response->assertStatus(200);
        $response->assertSee('Tabela Nutricional');
        $response->assertSee('INFORMAÇÃO NUTRICIONAL');
        $response->assertSee('100 g');
        $response->assertSee('Porção');
        $response->assertSee('%VD*');
        $response->assertSee('Valor energético');
        $response->assertSee('Carboidratos');
        $response->assertSee('Açúcares totais');
        $response->assertSee('Açúcares adicionados');
        $response->assertSee('Proteínas');
        $response->assertSee('Gorduras totais');
        $response->assertSee('Gorduras saturadas');
        $response->assertSee('Gorduras trans');
        $response->assertSee('Fibra alimentar');
        $response->assertSee('Sódio');
        $response->assertSee('Porções por embalagem:');
        $response->assertSee('Modelo do cliente');
        $response->assertSee('Vertical (padrão)');
        $response->assertSee('Aplicar ao rótulo');
        $response->assertSee('Voltar à receita');
    }
}
