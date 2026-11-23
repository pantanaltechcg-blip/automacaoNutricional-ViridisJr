<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use Tests\TestCase;

class RecipeScreenTest extends TestCase
{
    public function test_recipe_screen_contains_prototype_elements_and_no_live_kcal_number(): void
    {
        $response = $this->get('/receitas/nova');

        $response->assertStatus(200);
        $response->assertSee('Cadastro de Receita');
        $response->assertSee('Informações da receita');
        $response->assertSee('Nome da receita');
        $response->assertSee('Tipo do produto');
        $response->assertSee('Sólido ou semissólido');
        $response->assertSee('Líquido');
        $response->assertSee('Peso final da receita (g)');
        $response->assertSee('Porção (g)');
        $response->assertSee('Medida caseira');
        $response->assertSee('Ingredientes e quantidades');
        $response->assertSee('Buscar ingrediente');
        $response->assertSee('Não encontrou? Cadastrar ingrediente manualmente');
        $response->assertSee('Total informativo dos ingredientes');
        $response->assertSee('+ Cadastrar novo ingrediente');
        $response->assertSee('Gerar tabela nutricional');
        $response->assertSee('kcal indisponível');
    }
}
