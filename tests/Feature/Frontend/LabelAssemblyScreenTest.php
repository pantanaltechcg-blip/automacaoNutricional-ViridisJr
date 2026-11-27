<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use App\Models\DeclaredNutritionCalculation;
use App\Models\NutritionCalculation;
use App\Models\Recipe;
use App\Models\ServingDailyValueCalculation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LabelAssemblyScreenTest extends TestCase
{
    use DatabaseTransactions;

    public function test_returns_404_when_declared_calculation_does_not_exist(): void
    {
        $this->get('/rotulos/999999/montagem')->assertStatus(404);
    }

    public function test_renders_label_assembly_screen_with_disabled_pdf_when_declared_calculation_exists(): void
    {
        $recipe = Recipe::factory()->create([
            'name' => 'Produto Teste',
            'yield_value' => '400',
            'yield_unit' => 'g',
        ]);

        $calc003 = NutritionCalculation::query()->create([
            'recipe_id' => $recipe->id,
            'yield_value' => '400',
            'yield_unit' => 'g',
        ]);

        $calc004 = ServingDailyValueCalculation::query()->create([
            'nutrition_calculation_id' => $calc003->id,
            'source_recipe_id' => $recipe->id,
            'source_rules_version' => NutritionCalculation::RULES_VERSION,
            'source_yield_value' => '400',
            'source_yield_unit' => 'g',
            'portion_value' => '50',
            'portion_unit' => 'g',
            'household_measure_mode' => 'PROVIDED',
            'household_measure' => '1 fatia',
            'portion_reference_code' => 'REF-1',
            'portion_reference_version' => '1.0',
            'portion_reference_source' => 'TEST',
            'portion_reference_technical_status' => 'AUTHORIZED',
            'portion_evidence_reference' => 'EV-1',
            'portion_evidence_source' => 'TEST',
            'portion_evidence_version' => '1.0',
            'portion_evidence_scope' => 'test',
            'portion_evidence_kind' => 'TEST_FIXTURE',
            'portion_validated_by' => 'tester',
            'portion_validated_on' => '2026-11-11',
            'regulatory_profile_code' => 'PROF-TEST',
            'regulatory_profile_version' => '1.0',
            'regulatory_profile_title' => 'Perfil Teste',
            'regulatory_profile_status' => 'BLOCKED',
            'regulatory_profile_normative_source' => 'RDC 429/2020',
            'regulatory_profile_normative_version' => '2020',
            'regulatory_profile_consulted_on' => '2026-11-12',
        ]);

        $declared = DeclaredNutritionCalculation::query()->create([
            'serving_daily_value_calculation_id' => $calc004->id,
            'source_nutrition_calculation_id' => $calc003->id,
            'source_recipe_id' => $recipe->id,
            'source_003_rules_version' => NutritionCalculation::RULES_VERSION,
            'source_004_rules_version' => ServingDailyValueCalculation::RULES_VERSION,
            'source_yield_value' => '400',
            'source_yield_unit' => 'g',
            'portion_value' => '50',
            'portion_unit' => 'g',
            'household_measure_mode' => 'PROVIDED',
            'household_measure' => '1 fatia',
            'portion_reference_technical_status' => 'AUTHORIZED',
            'portion_evidence_reference' => 'EV-1',
            'portion_evidence_source' => 'TEST',
            'portion_evidence_version' => '1.0',
            'portion_evidence_scope' => 'test',
            'portion_evidence_kind' => 'TEST_FIXTURE',
            'portion_validated_by' => 'tester',
            'portion_validated_on' => '2026-11-11',
            'regulatory_profile_code' => 'PROF-TEST',
            'regulatory_profile_version' => '1.0',
            'regulatory_profile_status' => 'BLOCKED',
            'regulatory_profile_normative_source' => 'RDC 429/2020',
            'regulatory_profile_normative_version' => '2020',
            'declaration_profile_code' => 'DECL-TEST',
            'declaration_profile_version' => '1.0',
            'declaration_profile_title' => 'Decl Teste',
            'declaration_profile_status' => 'BLOCKED',
            'declaration_profile_normative_source' => 'RDC 429/2020',
            'declaration_profile_normative_version' => '2020',
            'declaration_profile_consulted_on' => '2026-11-12',
            'reference_100_energy_availability' => 'UNAVAILABLE',
            'portion_energy_availability' => 'UNAVAILABLE',
            'is_label_ready' => false,
        ]);

        $response = $this->get("/rotulos/{$declared->id}/montagem");

        $response->assertStatus(200);
        $response->assertSee('Montagem do Rótulo');
        $response->assertSee('[Nome do produto]');
        $response->assertSee('Arquivo para impressão');
        $response->assertSee('Tamanho do rótulo');
        $response->assertSee('Formato');
        $response->assertSee('Nome do arquivo');
        $response->assertSee('Baixar PDF');
        $response->assertSee('disabled', false);
        $response->assertSee('Editar informações');
    }
}
