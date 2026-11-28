<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use App\Models\DeclaredNutritionCalculation;
use App\Models\NutritionCalculation;
use App\Models\Recipe;
use App\Models\ServingDailyValueCalculation;
use App\Services\LabelExecutionListingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class LabelIndexScreenTest extends TestCase
{
    use DatabaseTransactions;

    public function test_shows_an_empty_state_without_creating_declared_calculations(): void
    {
        $initialCount = DeclaredNutritionCalculation::query()->count();
        $this->mock(LabelExecutionListingService::class)
            ->shouldReceive('paginate')
            ->once()
            ->andReturn(new LengthAwarePaginator([], 0, 25));

        $response = $this->get('/rotulos');

        $response->assertOk();
        $response->assertSee('Nenhuma execução de rótulo disponível');
        $response->assertDontSee('Resultado de demonstração');
        $this->assertSame($initialCount, DeclaredNutritionCalculation::query()->count());
    }

    public function test_lists_saved_calculations_with_origins_readiness_and_all_causes(): void
    {
        $older = $this->createDeclaredCalculation(false, '2026-11-25 10:00:00');
        $older->causes()->create([
            'scope' => 'LABEL_READINESS',
            'scope_key' => 'LABEL_READINESS',
            'category' => 'REGULATORY_BLOCK',
            'code' => 'BLOCKED_REFERENCE',
            'detail' => 'Referência regulatória pendente.',
        ]);
        $older->causes()->create([
            'scope' => 'LABEL_READINESS',
            'scope_key' => 'LABEL_READINESS',
            'category' => 'MISSING_SOURCE',
            'code' => 'MISSING_NUTRIENT_SOURCE',
            'detail' => 'Fonte quantitativa indisponível.',
        ]);

        $newer = $this->createDeclaredCalculation(true, '2026-11-26 10:00:00');

        $response = $this->get('/rotulos');

        $response->assertOk();
        $response->assertSee('Execução #'.$newer->id);
        $response->assertSee('Execução #'.$older->id);
        $response->assertSee('Receita #'.$older->source_recipe_id);
        $response->assertSee('Cálculo nutricional #'.$older->source_nutrition_calculation_id);
        $response->assertSee('Não apta para rotulagem');
        $response->assertSee('Apta segundo a execução registrada');
        $response->assertSee('BLOCKED_REFERENCE');
        $response->assertSee('Referência regulatória pendente.');
        $response->assertSee('MISSING_NUTRIENT_SOURCE');
        $response->assertSee('Fonte quantitativa indisponível.');
        $this->assertLessThan(strpos($response->getContent(), 'Execução #'.$older->id), strpos($response->getContent(), 'Execução #'.$newer->id));
        $response->assertSee(route('labels.assembly', ['declaredNutritionCalculation' => $older->id]));
        $response->assertDontSee('Nome alterado depois da execução');
    }

    public function test_paginates_calculations_and_links_only_to_existing_executions(): void
    {
        $calculations = [];

        for ($index = 1; $index <= 26; $index++) {
            $calculations[] = $this->createDeclaredCalculation(false, sprintf('2026-11-%02d 10:00:00', $index));
        }

        $firstPage = $this->get('/rotulos');
        $firstPage->assertOk();
        $firstPage->assertSee('Execução #'.$calculations[25]->id);
        $firstPage->assertDontSee('Execução #'.$calculations[0]->id);
        $firstPage->assertSee('page=2');

        $secondPage = $this->get('/rotulos?page=2');
        $secondPage->assertOk();
        $secondPage->assertSee('Execução #'.$calculations[0]->id);

        $this->get('/rotulos/'.$calculations[25]->id.'/montagem')->assertOk();
        $this->get('/rotulos/999999/montagem')->assertNotFound();
    }

    public function test_navigation_links_to_labels_and_does_not_offer_settings(): void
    {
        $response = $this->get('/rotulos');

        $response->assertOk();
        $response->assertSee('href="'.route('labels.index').'"', false);
        $response->assertDontSee('Configurações');
    }

    private function createDeclaredCalculation(bool $isLabelReady, string $createdAt): DeclaredNutritionCalculation
    {
        $recipe = Recipe::factory()->create([
            'name' => 'Nome alterado depois da execução',
            'yield_value' => '400',
            'yield_unit' => 'g',
        ]);

        $sourceCalculation = NutritionCalculation::query()->create([
            'recipe_id' => $recipe->id,
            'yield_value' => '400',
            'yield_unit' => 'g',
        ]);

        $servingCalculation = ServingDailyValueCalculation::query()->create([
            'nutrition_calculation_id' => $sourceCalculation->id,
            'source_recipe_id' => $recipe->id,
            'source_rules_version' => NutritionCalculation::RULES_VERSION,
            'source_yield_value' => '400',
            'source_yield_unit' => 'g',
            'portion_value' => '50',
            'portion_unit' => 'g',
            'household_measure_mode' => 'PROVIDED',
            'household_measure' => '1 porção',
            'portion_reference_code' => 'REF-TEST',
            'portion_reference_version' => '1.0',
            'portion_reference_source' => 'TEST',
            'portion_reference_technical_status' => 'AUTHORIZED',
            'portion_evidence_reference' => 'EV-TEST',
            'portion_evidence_source' => 'TEST',
            'portion_evidence_version' => '1.0',
            'portion_evidence_scope' => 'test fixture',
            'portion_evidence_kind' => 'TEST_FIXTURE',
            'portion_validated_by' => 'test',
            'portion_validated_on' => '2026-11-26',
            'regulatory_profile_code' => 'PROFILE-TEST',
            'regulatory_profile_version' => '1.0',
            'regulatory_profile_title' => 'Perfil de teste',
            'regulatory_profile_status' => 'BLOCKED',
            'regulatory_profile_normative_source' => 'Test fixture',
            'regulatory_profile_normative_version' => 'test',
            'regulatory_profile_consulted_on' => '2026-11-26',
        ]);

        return DeclaredNutritionCalculation::query()->create([
            'serving_daily_value_calculation_id' => $servingCalculation->id,
            'source_nutrition_calculation_id' => $sourceCalculation->id,
            'source_recipe_id' => $recipe->id,
            'source_003_rules_version' => NutritionCalculation::RULES_VERSION,
            'source_004_rules_version' => ServingDailyValueCalculation::RULES_VERSION,
            'source_yield_value' => '400',
            'source_yield_unit' => 'g',
            'portion_value' => '50',
            'portion_unit' => 'g',
            'household_measure_mode' => 'PROVIDED',
            'household_measure' => '1 porção',
            'portion_reference_technical_status' => 'AUTHORIZED',
            'portion_evidence_reference' => 'EV-TEST',
            'portion_evidence_source' => 'TEST',
            'portion_evidence_version' => '1.0',
            'portion_evidence_scope' => 'test fixture',
            'portion_evidence_kind' => 'TEST_FIXTURE',
            'portion_validated_by' => 'test',
            'portion_validated_on' => '2026-11-26',
            'regulatory_profile_code' => 'PROFILE-TEST',
            'regulatory_profile_version' => '1.0',
            'regulatory_profile_status' => 'BLOCKED',
            'regulatory_profile_normative_source' => 'Test fixture',
            'regulatory_profile_normative_version' => 'test',
            'declaration_profile_code' => 'DECLARATION-TEST',
            'declaration_profile_version' => '1.0',
            'declaration_profile_title' => 'Declaração de teste',
            'declaration_profile_status' => 'BLOCKED',
            'declaration_profile_normative_source' => 'Test fixture',
            'declaration_profile_normative_version' => 'test',
            'declaration_profile_consulted_on' => '2026-11-26',
            'reference_100_energy_availability' => 'UNAVAILABLE',
            'portion_energy_availability' => 'UNAVAILABLE',
            'is_label_ready' => $isLabelReady,
            'created_at' => $createdAt,
        ]);
    }
}
