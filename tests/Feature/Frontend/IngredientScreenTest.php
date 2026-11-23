<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class IngredientScreenTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ingredient_screen_renders_required_fields_qualitative_statuses_and_unsupported_options(): void
    {
        $response = $this->get('/ingredientes/novo');

        $response->assertStatus(200);
        $response->assertSee('Cadastrar Ingrediente');
        $response->assertSee('Receita › Ingredientes');
        $response->assertSee('Dados do ingrediente');
        $response->assertSee('Nome do ingrediente');
        $response->assertSee('Origem do dado');
        $response->assertSee('Código na fonte');
        $response->assertSee('Referência da evidência');
        $response->assertSee('Fonte da evidência');
        $response->assertSee('Responsável pelo cadastro');
        $response->assertSee('Data do registro');
        $response->assertSee('100 g');
        $response->assertSee('100 ml');
        $response->assertSee('EXACT');
        $response->assertSee('TRACE');
        $response->assertSee('NA');
        $response->assertSee('ABSENT');
        $response->assertSee('REEVALUATION');
        $response->assertSee('Açúcares totais (g)');
        $response->assertSee('Açúcares adicionados (g)');
        $response->assertSee('Gorduras trans (g)');
        $response->assertSee('Salvar no banco e voltar à receita');
        $response->assertSee('Cancelar');
        $response->assertSee('Como funciona');
    }
}
