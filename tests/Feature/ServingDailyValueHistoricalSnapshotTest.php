<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Food;
use App\Models\FoodNutrient;
use App\Models\Nutrient;
use App\Models\NutritionCalculation;
use App\Models\PortionAuthorization;
use App\Models\Recipe;
use App\Models\RegulatoryProfile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ServingDailyValueHistoricalSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_uses_only_immutable_snapshot_and_each_post_creates_a_new_execution(): void
    {
        $recipe = Recipe::factory()->create(['yield_value' => '100', 'yield_unit' => 'g']);
        $food = Food::query()->create([
            'name' => 'Alimento original',
            'source' => 'TACO',
            'source_code' => 'HISTORY-FOOD',
        ]);
        $nutrient = Nutrient::query()->create(['name' => 'Nutriente original', 'unit' => 'g']);
        $foodNutrient = FoodNutrient::query()->create([
            'food_id' => $food->id,
            'nutrient_id' => $nutrient->id,
            'amount_value' => '5',
            'status' => FoodNutrient::STATUS_EXACT,
        ]);
        $ingredient = $recipe->ingredients()->create([
            'food_id' => $food->id,
            'quantity' => '100',
            'unit' => 'g',
            'position' => 1,
        ]);
        $source = NutritionCalculation::query()->create([
            'recipe_id' => $recipe->id, 'yield_value' => '100', 'yield_unit' => 'g',
            'rules_version' => NutritionCalculation::RULES_VERSION,
        ]);
        $source->nutrients()->create([
            'nutrient_id' => $nutrient->id, 'nutrient_name' => 'Snapshot', 'nutrient_unit' => 'g',
            'total_numerator' => '5', 'total_denominator' => '1', 'is_complete' => true,
            'has_missing_relation' => false, 'reference_basis' => '100', 'reference_unit' => 'g',
            'reference_numerator' => '5', 'reference_denominator' => '1',
        ]);
        $profile = RegulatoryProfile::query()->create([
            'code' => 'HISTORY', 'version' => 'v1', 'title' => 'Histórico',
            'normative_source' => 'Fonte original', 'normative_version' => 'v1',
            'consulted_on' => '2026-11-11', 'technical_status' => 'BLOCKED',
        ]);
        $authorization = PortionAuthorization::query()->create([
            'nutrition_calculation_id' => $source->id, 'reference_code' => 'HISTORY-PORTION',
            'reference_version' => 'v1', 'reference_source' => 'Fonte original',
            'technical_status' => 'AUTHORIZED', 'portion_value' => '20', 'portion_unit' => 'g',
            'household_measure_mode' => 'WAIVED', 'justification' => 'Dispensa documentada.',
            'evidence_reference' => 'E-HISTORY', 'evidence_source' => 'Teste', 'evidence_version' => 'v1',
            'evidence_scope' => "nutrition-calculation:{$source->id}/portion:HISTORY-PORTION@v1",
            'evidence_kind' => 'TEST_FIXTURE', 'validated_by' => 'Automated test', 'validated_on' => '2026-11-11',
        ]);
        $payload = [
            'portion' => ['value' => '20', 'unit' => 'g', 'household_measure' => null],
            'portion_authorization_id' => $authorization->id, 'regulatory_profile_id' => $profile->id,
        ];

        $first = $this->postJson("/api/nutrition-calculations/{$source->id}/serving-daily-value-calculations", $payload)
            ->assertSuccessful()->json('data');
        $this->assertSame('AUTHORIZED', $first['portion']['reference']['technical_status']);

        RegulatoryProfile::query()->create([
            'code' => 'HISTORY', 'version' => 'v2', 'title' => 'Nova versão',
            'normative_source' => 'Nova fonte', 'normative_version' => 'v2',
            'consulted_on' => '2026-11-11', 'technical_status' => 'BLOCKED',
        ]);
        PortionAuthorization::query()->create([
            'nutrition_calculation_id' => $source->id, 'reference_code' => 'HISTORY-PORTION',
            'reference_version' => 'v2', 'reference_source' => 'Nova fonte',
            'technical_status' => 'BLOCKED', 'portion_value' => '25', 'portion_unit' => 'g',
            'household_measure_mode' => 'WAIVED', 'justification' => 'Nova versão bloqueada.',
        ]);

        $recipe->update(['name' => 'Receita atual alterada', 'yield_value' => '150']);
        $ingredient->update(['quantity' => '80']);
        $food->update(['name' => 'Alimento atual alterado', 'source_code' => 'HISTORY-FOOD-UPDATED']);
        $nutrient->update(['name' => 'Nutriente atual alterado', 'unit' => 'mg']);
        $foodNutrient->update(['amount_value' => null, 'status' => FoodNutrient::STATUS_TRACE]);

        $this->getJson("/api/serving-daily-value-calculations/{$first['id']}")
            ->assertSuccessful()->assertExactJson(['data' => $first]);

        $second = $this->postJson("/api/nutrition-calculations/{$source->id}/serving-daily-value-calculations", $payload)
            ->assertSuccessful()->json('data');
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertSame($first['regulatory_profile'], $second['regulatory_profile']);

        $this->putJson("/api/serving-daily-value-calculations/{$first['id']}", [])->assertMethodNotAllowed();
        $this->patchJson("/api/serving-daily-value-calculations/{$first['id']}", [])->assertMethodNotAllowed();
        $this->deleteJson("/api/serving-daily-value-calculations/{$first['id']}")->assertMethodNotAllowed();
        $this->getJson('/api/serving-daily-value-calculations')->assertNotFound();
    }
}
