<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use Tests\TestCase;

class ShellTest extends TestCase
{
    public function test_welcome_route_remains_available(): void
    {
        $this->get('/')->assertStatus(200);
    }

    public function test_recipe_screen_renders_prototype_shell_and_unavailable_nav_items(): void
    {
        $response = $this->get('/receitas/nova');

        $response->assertStatus(200);
        $response->assertSee('Rótulo', false);
        $response->assertSee('Nutri', false);
        $response->assertSee('Receitas');
        $response->assertSee('Rótulos');
        $response->assertSee('Configurações');
        $response->assertSee('Tabela nutricional');
        $response->assertSee('Rótulo em PDF');
        $response->assertSee('aria-disabled="true"', false);
    }
}
