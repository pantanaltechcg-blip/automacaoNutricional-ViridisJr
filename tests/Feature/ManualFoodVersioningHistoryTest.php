<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Food;
use App\Models\FoodNutrient;
use App\Models\ManualFoodRegistration;
use App\Models\Nutrient;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ManualFoodVersioningHistoryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_put_creates_immutable_new_version_preserving_prior_versions_and_updating_current_projection(): void
    {
        $energy = $this->createNutrient('Energia versão', 'kcal');
        $protein = $this->createNutrient('Proteína versão');
        $sodium = $this->createNutrient('Sódio versão', 'mg');
        $calcium = $this->createNutrient('Cálcio versão', 'mg');

        $v1Response = $this->postJson('/api/manual-foods', [
            'name' => 'Polpa de cupuaçu integral v1',
            'category' => 'Frutas amazônicas',
            'source' => 'LAB_AMAZONIA',
            'source_code' => 'CUP-V1',
            'reference_unit' => 'g',
            'evidence_reference' => 'LAUDO-CUP-01',
            'evidence_source' => 'Lab Amazônia',
            'registered_by' => 'Ana Souza',
            'registered_on' => '2026-11-10',
            'nutrients' => [
                [
                    'nutrient_id' => $energy->id,
                    'status' => FoodNutrient::STATUS_EXACT,
                    'amount_value' => '120.00',
                ],
                [
                    'nutrient_id' => $protein->id,
                    'status' => FoodNutrient::STATUS_EXACT,
                    'amount_value' => '3.50',
                ],
                [
                    'nutrient_id' => $sodium->id,
                    'status' => FoodNutrient::STATUS_TRACE,
                    'amount_value' => null,
                ],
            ],
        ])->assertCreated()->json('data');

        $foodId = $v1Response['id'];
        $v1Snapshot = $v1Response['current_version'];

        $v2Response = $this->putJson("/api/manual-foods/{$foodId}", [
            'name' => '  Polpa de cupuaçu integral revisada  ',
            'category' => 'Frutas nativas revisadas',
            'source' => 'LAB_AMAZONIA_REV',
            'source_code' => 'CUP-V2',
            'reference_unit' => 'g',
            'evidence_reference' => 'LAUDO-CUP-02',
            'evidence_source' => 'Lab Amazônia Revisado',
            'registered_by' => 'Carlos Lima',
            'registered_on' => '2026-11-17',
            'change_reason' => '  Correção conforme novo laudo laboratorial  ',
            'nutrients' => [
                [
                    'nutrient_id' => $calcium->id,
                    'status' => FoodNutrient::STATUS_ABSENT,
                    'amount_value' => null,
                ],
                [
                    'nutrient_id' => $sodium->id,
                    'status' => FoodNutrient::STATUS_EXACT,
                    'amount_value' => '12',
                ],
                [
                    'nutrient_id' => $energy->id,
                    'status' => FoodNutrient::STATUS_EXACT,
                    'amount_value' => '140.25',
                ],
            ],
        ])->assertOk()->json('data');

        $this->assertSame($foodId, $v2Response['id']);
        $this->assertTrue($v2Response['is_active']);
        $this->assertSame(2, $v2Response['current_version_number']);
        $this->assertSame('Polpa de cupuaçu integral revisada', $v2Response['name']);
        $this->assertSame('Frutas nativas revisadas', $v2Response['category']);
        $this->assertSame('LAB_AMAZONIA_REV', $v2Response['source']);
        $this->assertSame('CUP-V2', $v2Response['source_code']);

        $this->assertSame(2, $v2Response['current_version']['version_number']);
        $this->assertSame('Correção conforme novo laudo laboratorial', $v2Response['current_version']['change_reason']);
        $this->assertSame('LAUDO-CUP-02', $v2Response['current_version']['evidence_reference']);
        $this->assertSame('Carlos Lima', $v2Response['current_version']['registered_by']);
        $this->assertSame('2026-11-17', $v2Response['current_version']['registered_on']);

        $this->assertCount(2, $v2Response['versions']);
        $this->assertSame([1, 2], array_column($v2Response['versions'], 'version_number'));
        $this->assertSame($v1Snapshot, $v2Response['versions'][0]);
        $this->assertSame($v2Response['current_version'], $v2Response['versions'][1]);

        $expectedV2NutrientIds = [$energy->id, $sodium->id, $calcium->id];
        sort($expectedV2NutrientIds);
        $this->assertSame(
            $expectedV2NutrientIds,
            array_column($v2Response['current_version']['nutrients'], 'id'),
        );
        $this->assertNotContains($protein->id, array_column($v2Response['current_version']['nutrients'], 'id'));
        $this->assertSame('140.25', $this->nutrientPayloadFor($v2Response['current_version']['nutrients'], $energy->id)['amount_value']);
        $this->assertSame('12.00', $this->nutrientPayloadFor($v2Response['current_version']['nutrients'], $sodium->id)['amount_value']);
        $this->assertNull($this->nutrientPayloadFor($v2Response['current_version']['nutrients'], $calcium->id)['amount_value']);
        $this->assertSame(
            FoodNutrient::STATUS_ABSENT,
            $this->nutrientPayloadFor($v2Response['current_version']['nutrients'], $calcium->id)['status'],
        );

        $this->getJson("/api/manual-foods/{$foodId}")
            ->assertOk()
            ->assertExactJson(['data' => $v2Response]);

        $this->assertDatabaseHas('foods', [
            'id' => $foodId,
            'name' => 'Polpa de cupuaçu integral revisada',
            'category' => 'Frutas nativas revisadas',
            'source' => 'LAB_AMAZONIA_REV',
            'source_code' => 'CUP-V2',
        ]);
        $this->assertDatabaseMissing('food_nutrients', [
            'food_id' => $foodId,
            'nutrient_id' => $protein->id,
        ]);

        $registration = ManualFoodRegistration::query()->where('food_id', $foodId)->firstOrFail();
        $v1Model = $registration->versions()->where('version_number', 1)->firstOrFail();
        $this->assertDatabaseHas('manual_food_version_nutrients', [
            'manual_food_version_id' => $v1Model->id,
            'nutrient_id' => $protein->id,
            'status' => FoodNutrient::STATUS_EXACT,
            'amount_value' => '3.50',
        ]);
    }

    public function test_put_requires_non_empty_change_reason_after_trim_and_rejects_invalid_updates(): void
    {
        $nutrient = $this->createNutrient('Proteína validação PUT');
        $basePayload = [
            'name' => 'Alimento PUT validação',
            'category' => 'Categoria teste',
            'source' => 'LAB_PUT_VAL',
            'source_code' => 'PUT-VAL-01',
            'reference_unit' => 'g',
            'evidence_reference' => 'LAUDO-01',
            'evidence_source' => 'Lab Teste',
            'registered_by' => 'Ana',
            'registered_on' => '2026-11-17',
            'nutrients' => [[
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '5.00',
            ]],
        ];

        $created = $this->postJson('/api/manual-foods', $basePayload)
            ->assertCreated()
            ->json('data');

        $this->putJson("/api/manual-foods/{$created['id']}", $basePayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('change_reason');

        foreach (['', '   ', null] as $blankReason) {
            $this->putJson("/api/manual-foods/{$created['id']}", array_merge($basePayload, [
                'change_reason' => $blankReason,
            ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('change_reason');
        }

        $this->putJson("/api/manual-foods/{$created['id']}", array_merge($basePayload, [
            'source' => ' taco ',
            'change_reason' => 'Motivo válido',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source');

        $this->putJson("/api/manual-foods/{$created['id']}", array_merge($basePayload, [
            'change_reason' => 'Motivo válido',
            'nutrients' => [[
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => 2.5,
            ]],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nutrients.0.amount_value');

        $this->getJson("/api/manual-foods/{$created['id']}")
            ->assertOk()
            ->assertExactJson(['data' => $created]);
    }

    public function test_put_allows_keeping_own_identity_while_blocking_collision_with_other_foods(): void
    {
        $nutrient = $this->createNutrient('Proteína colisão PUT');
        $token = str()->uuid()->toString();

        $foodA = $this->postJson('/api/manual-foods', [
            'name' => "Maçã manual A {$token}",
            'category' => 'Frutas',
            'source' => 'FONTE_COLISAO',
            'source_code' => "COL-A-{$token}",
            'reference_unit' => 'g',
            'evidence_reference' => 'LAUDO-A1',
            'evidence_source' => 'Lab A',
            'registered_by' => 'Responsável A',
            'registered_on' => '2026-11-17',
            'nutrients' => [[
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '1.00',
            ]],
        ])->assertCreated()->json('data');

        $foodB = $this->postJson('/api/manual-foods', [
            'name' => "Maçã manual B {$token}",
            'category' => 'Frutas',
            'source' => 'FONTE_COLISAO',
            'source_code' => "COL-B-{$token}",
            'reference_unit' => 'g',
            'evidence_reference' => 'LAUDO-B1',
            'evidence_source' => 'Lab B',
            'registered_by' => 'Responsável B',
            'registered_on' => '2026-11-17',
            'nutrients' => [[
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '2.00',
            ]],
        ])->assertCreated()->json('data');

        $foodAUpdated = $this->putJson("/api/manual-foods/{$foodA['id']}", [
            'name' => "  MACA MANUAL A {$token}  ",
            'category' => 'Frutas',
            'source' => ' fonte_colisao ',
            'source_code' => '  '.strtolower("col-a-{$token}").'  ',
            'reference_unit' => 'g',
            'evidence_reference' => 'LAUDO-A2',
            'evidence_source' => 'Lab A',
            'registered_by' => 'Responsável A',
            'registered_on' => '2026-11-17',
            'change_reason' => 'Ajuste nutricional mantendo identidade',
            'nutrients' => [[
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '1.50',
            ]],
        ])->assertOk()->json('data');

        $this->assertSame(2, $foodAUpdated['current_version_number']);

        $this->putJson("/api/manual-foods/{$foodA['id']}", [
            'name' => "Nome exclusivo sem colisão {$token}",
            'category' => 'Frutas',
            'source' => ' fonte_colisao ',
            'source_code' => '  '.strtolower("col-b-{$token}").'  ',
            'reference_unit' => 'g',
            'evidence_reference' => 'LAUDO-A3',
            'evidence_source' => 'Lab A',
            'registered_by' => 'Responsável A',
            'registered_on' => '2026-11-17',
            'change_reason' => 'Tentativa de colisão por código',
            'nutrients' => [[
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '3.00',
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_code');

        $this->putJson("/api/manual-foods/{$foodA['id']}", [
            'name' => '  '.strtoupper("maca manual b {$token}").'  ',
            'category' => 'Frutas',
            'source' => ' fonte_colisao ',
            'source_code' => "COL-A-NOVO-{$token}",
            'reference_unit' => 'g',
            'evidence_reference' => 'LAUDO-A3',
            'evidence_source' => 'Lab A',
            'registered_by' => 'Responsável A',
            'registered_on' => '2026-11-17',
            'change_reason' => 'Tentativa de colisão por nome',
            'nutrients' => [[
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '3.00',
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->getJson("/api/manual-foods/{$foodA['id']}")
            ->assertOk()
            ->assertExactJson(['data' => $foodAUpdated]);
        $this->getJson("/api/manual-foods/{$foodB['id']}")
            ->assertOk()
            ->assertExactJson(['data' => $foodB]);
    }

    public function test_put_returns_404_for_taco_food_and_nonexistent_food(): void
    {
        $nutrient = $this->createNutrient('Proteína TACO PUT 404');
        $tacoFood = Food::query()->create([
            'name' => 'Alimento TACO imutável PUT',
            'category' => 'Cereais',
            'source' => 'TACO',
            'source_code' => 'TACO-PUT-'.str()->uuid()->toString(),
        ]);
        $tacoRelation = FoodNutrient::query()->create([
            'food_id' => $tacoFood->id,
            'nutrient_id' => $nutrient->id,
            'amount_value' => '7.50',
            'status' => FoodNutrient::STATUS_EXACT,
        ]);
        $tacoFoodSnapshot = $tacoFood->fresh()->getRawOriginal();
        $tacoRelationSnapshot = $tacoRelation->fresh()->getRawOriginal();

        $payload = [
            'name' => 'Tentativa de sobrescrever TACO',
            'category' => 'Cereais',
            'source' => 'LAB_OUTRO',
            'source_code' => 'OUTRO-01',
            'reference_unit' => 'g',
            'evidence_reference' => 'LAUDO-01',
            'evidence_source' => 'Lab Teste',
            'registered_by' => 'Ana',
            'registered_on' => '2026-11-17',
            'change_reason' => 'Tentativa indevida',
            'nutrients' => [[
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '1.00',
            ]],
        ];

        $this->putJson("/api/manual-foods/{$tacoFood->id}", $payload)->assertNotFound();
        $this->putJson('/api/manual-foods/'.PHP_INT_MAX, $payload)->assertNotFound();

        $this->assertSame($tacoFoodSnapshot, $tacoFood->fresh()->getRawOriginal());
        $this->assertSame($tacoRelationSnapshot, $tacoRelation->fresh()->getRawOriginal());
    }

    public function test_updating_and_inactivating_manual_food_preserves_historical_calculations_while_new_calculations_use_latest_version(): void
    {
        $energy = $this->createNutrient('Energia histórico manual', 'kcal');
        $protein = $this->createNutrient('Proteína histórico manual');
        $sodium = $this->createNutrient('Sódio histórico manual', 'mg');
        $calcium = $this->createNutrient('Cálcio histórico manual', 'mg');

        $manualFood = $this->postJson('/api/manual-foods', [
            'name' => 'Farinha de babaçu artesanal',
            'category' => 'Farinhas',
            'source' => 'LAB_HIST',
            'source_code' => 'HIST-V1',
            'reference_unit' => 'g',
            'evidence_reference' => 'LAUDO-HIST-01',
            'evidence_source' => 'Laboratório Histórico',
            'registered_by' => 'Dra. Marina',
            'registered_on' => '2026-11-10',
            'nutrients' => [
                [
                    'nutrient_id' => $energy->id,
                    'status' => FoodNutrient::STATUS_EXACT,
                    'amount_value' => '100.00',
                ],
                [
                    'nutrient_id' => $protein->id,
                    'status' => FoodNutrient::STATUS_EXACT,
                    'amount_value' => '2.00',
                ],
                [
                    'nutrient_id' => $sodium->id,
                    'status' => FoodNutrient::STATUS_TRACE,
                    'amount_value' => null,
                ],
            ],
        ])->assertCreated()->json('data');

        $recipe = Recipe::factory()->create([
            'name' => 'Bolo de babaçu histórico',
            'yield_value' => '200',
            'yield_unit' => 'g',
        ]);
        $recipe->ingredients()->create([
            'food_id' => $manualFood['id'],
            'quantity' => '200',
            'unit' => 'g',
            'position' => 1,
        ]);

        $calc1 = $this->postJson("/api/recipes/{$recipe->id}/nutrition-calculations")
            ->assertSuccessful()
            ->json('data');
        $calc1Id = $calc1['id'];

        $this->assertSame([
            'id' => $manualFood['id'],
            'source' => 'LAB_HIST',
            'source_code' => 'HIST-V1',
        ], $calc1['ingredients'][0]['food']);

        $calc1Energy = $this->calculationNutrientFor($calc1['nutrients'], $energy->id);
        $calc1Protein = $this->calculationNutrientFor($calc1['nutrients'], $protein->id);
        $calc1Sodium = $this->calculationNutrientFor($calc1['nutrients'], $sodium->id);
        $calc1Calcium = $this->calculationNutrientFor($calc1['nutrients'], $calcium->id);

        $this->assertSame(['numerator' => '200', 'denominator' => '1'], $calc1Energy['total']['value']);
        $this->assertSame(['numerator' => '100', 'denominator' => '1'], $calc1Energy['reference']['value']);
        $this->assertSame('100.00', $calc1Energy['contributions'][0]['source_amount_per_100g']);
        $this->assertSame(['numerator' => '4', 'denominator' => '1'], $calc1Protein['total']['value']);
        $this->assertSame(['numerator' => '2', 'denominator' => '1'], $calc1Protein['reference']['value']);
        $this->assertSame(FoodNutrient::STATUS_TRACE, $calc1Sodium['contributions'][0]['status']);
        $this->assertSame([FoodNutrient::STATUS_TRACE], $calc1Sodium['total']['qualitative_states']);
        $this->assertTrue($calc1Calcium['contributions'][0]['missing_relation']);
        $this->assertTrue($calc1Calcium['total']['has_missing_relation']);

        $this->putJson("/api/manual-foods/{$manualFood['id']}", [
            'name' => 'Farinha de babaçu artesanal revisada',
            'category' => 'Farinhas',
            'source' => 'LAB_HIST_REV',
            'source_code' => 'HIST-V2',
            'reference_unit' => 'g',
            'evidence_reference' => 'LAUDO-HIST-02',
            'evidence_source' => 'Laboratório Histórico',
            'registered_by' => 'Dra. Marina',
            'registered_on' => '2026-11-17',
            'change_reason' => 'Reanálise cromatográfica completa',
            'nutrients' => [
                [
                    'nutrient_id' => $energy->id,
                    'status' => FoodNutrient::STATUS_EXACT,
                    'amount_value' => '150.00',
                ],
                [
                    'nutrient_id' => $protein->id,
                    'status' => FoodNutrient::STATUS_EXACT,
                    'amount_value' => '5.50',
                ],
                [
                    'nutrient_id' => $sodium->id,
                    'status' => FoodNutrient::STATUS_EXACT,
                    'amount_value' => '20.00',
                ],
                [
                    'nutrient_id' => $calcium->id,
                    'status' => FoodNutrient::STATUS_ABSENT,
                    'amount_value' => null,
                ],
            ],
        ])->assertOk();

        $this->getJson("/api/nutrition-calculations/{$calc1Id}")
            ->assertOk()
            ->assertExactJson(['data' => $calc1]);

        $calc2 = $this->postJson("/api/recipes/{$recipe->id}/nutrition-calculations")
            ->assertSuccessful()
            ->json('data');
        $calc2Id = $calc2['id'];

        $this->assertNotSame($calc1Id, $calc2Id);
        $this->assertSame([
            'id' => $manualFood['id'],
            'source' => 'LAB_HIST_REV',
            'source_code' => 'HIST-V2',
        ], $calc2['ingredients'][0]['food']);

        $calc2Energy = $this->calculationNutrientFor($calc2['nutrients'], $energy->id);
        $calc2Protein = $this->calculationNutrientFor($calc2['nutrients'], $protein->id);
        $calc2Sodium = $this->calculationNutrientFor($calc2['nutrients'], $sodium->id);
        $calc2Calcium = $this->calculationNutrientFor($calc2['nutrients'], $calcium->id);

        $this->assertSame(['numerator' => '300', 'denominator' => '1'], $calc2Energy['total']['value']);
        $this->assertSame(['numerator' => '150', 'denominator' => '1'], $calc2Energy['reference']['value']);
        $this->assertSame('150.00', $calc2Energy['contributions'][0]['source_amount_per_100g']);

        $this->assertSame(['numerator' => '11', 'denominator' => '1'], $calc2Protein['total']['value']);
        $this->assertSame(['numerator' => '11', 'denominator' => '2'], $calc2Protein['reference']['value']);
        $this->assertSame('5.50', $calc2Protein['contributions'][0]['source_amount_per_100g']);

        $this->assertSame(['numerator' => '40', 'denominator' => '1'], $calc2Sodium['total']['value']);
        $this->assertSame(['numerator' => '20', 'denominator' => '1'], $calc2Sodium['reference']['value']);
        $this->assertTrue($calc2Sodium['total']['complete']);
        $this->assertSame([], $calc2Sodium['total']['qualitative_states']);

        $this->assertNull($calc2Calcium['total']['value']);
        $this->assertFalse($calc2Calcium['total']['has_missing_relation']);
        $this->assertSame([FoodNutrient::STATUS_ABSENT], $calc2Calcium['total']['qualitative_states']);
        $this->assertSame(FoodNutrient::STATUS_ABSENT, $calc2Calcium['contributions'][0]['status']);
        $this->assertFalse($calc2Calcium['contributions'][0]['missing_relation']);

        $this->getJson("/api/nutrition-calculations/{$calc1Id}")
            ->assertOk()
            ->assertExactJson(['data' => $calc1]);

        $this->patchJson("/api/manual-foods/{$manualFood['id']}/status", [
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);

        $this->getJson("/api/nutrition-calculations/{$calc1Id}")
            ->assertOk()
            ->assertExactJson(['data' => $calc1]);
        $this->getJson("/api/nutrition-calculations/{$calc2Id}")
            ->assertOk()
            ->assertExactJson(['data' => $calc2]);

        $manualAfterInactivation = $this->getJson("/api/manual-foods/{$manualFood['id']}")
            ->assertOk()
            ->json('data');
        $this->assertFalse($manualAfterInactivation['is_active']);
        $this->assertSame(2, $manualAfterInactivation['current_version_number']);
        $this->assertCount(2, $manualAfterInactivation['versions']);

        $this->getJson("/api/recipes/{$recipe->id}")
            ->assertOk()
            ->assertJsonPath('data.ingredients.0.food_id', $manualFood['id']);
    }

    private function createNutrient(string $label, string $unit = 'g'): Nutrient
    {
        return Nutrient::query()->create([
            'name' => $label.' '.str()->uuid()->toString(),
            'unit' => $unit,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $nutrients
     * @return array<string, mixed>
     */
    private function nutrientPayloadFor(array $nutrients, int $nutrientId): array
    {
        $found = collect($nutrients)->first(
            fn (array $item): bool => $item['id'] === $nutrientId,
        );

        $this->assertNotNull($found);

        return $found;
    }

    /**
     * @param  list<array<string, mixed>>  $nutrients
     * @return array<string, mixed>
     */
    private function calculationNutrientFor(array $nutrients, int $nutrientId): array
    {
        $found = collect($nutrients)->first(
            fn (array $item): bool => $item['nutrient']['id'] === $nutrientId,
        );

        $this->assertNotNull($found);

        return $found;
    }
}
