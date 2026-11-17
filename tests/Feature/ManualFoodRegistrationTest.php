<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Food;
use App\Models\FoodNutrient;
use App\Models\ManualFoodRegistration;
use App\Models\Nutrient;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManualFoodRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_post_creates_manual_food_with_exact_and_qualitative_nutrients_omitting_unprovided_and_preserving_taco(): void
    {
        $tacoNutrient = $this->createNutrient('Nutriente TACO preservado');
        $tacoFood = $this->createTacoFood('Alimento TACO Intocado');
        $tacoRelation = FoodNutrient::query()->create([
            'food_id' => $tacoFood->id,
            'nutrient_id' => $tacoNutrient->id,
            'amount_value' => '9.99',
            'status' => FoodNutrient::STATUS_EXACT,
        ]);
        $tacoFoodSnapshot = $tacoFood->fresh()->getRawOriginal();
        $tacoRelationSnapshot = $tacoRelation->fresh()->getRawOriginal();

        $exactZero = $this->createNutrient('Gorduras trans manuais');
        $exactTwoDecimals = $this->createNutrient('Proteína manual');
        $exactOneDecimal = $this->createNutrient('Carboidrato manual');
        $trace = $this->createNutrient('Sódio manual', 'mg');
        $na = $this->createNutrient('Vitamina D manual', 'µg');
        $absent = $this->createNutrient('Álcool manual');
        $reevaluation = $this->createNutrient('Fibra manual');
        $omitted = $this->createNutrient('Nutriente omitido');

        $payload = $this->validPayload([
            [
                'nutrient_id' => $reevaluation->id,
                'status' => FoodNutrient::STATUS_REEVALUATION,
                'amount_value' => null,
            ],
            [
                'nutrient_id' => $exactOneDecimal->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '10.2',
            ],
            [
                'nutrient_id' => $absent->id,
                'status' => FoodNutrient::STATUS_ABSENT,
                'amount_value' => null,
            ],
            [
                'nutrient_id' => $exactZero->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '0',
            ],
            [
                'nutrient_id' => $na->id,
                'status' => FoodNutrient::STATUS_NA,
                'amount_value' => null,
            ],
            [
                'nutrient_id' => $exactTwoDecimals->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '1.45',
            ],
            [
                'nutrient_id' => $trace->id,
                'status' => FoodNutrient::STATUS_TRACE,
                'amount_value' => null,
            ],
        ]);

        $response = $this->postJson('/api/manual-foods', $payload)
            ->assertCreated();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertSame([
            'category',
            'current_version',
            'current_version_number',
            'id',
            'is_active',
            'name',
            'reference_unit',
            'source',
            'source_code',
            'versions',
        ], $this->sortedKeys($data));

        $foodId = $data['id'];
        $this->assertIsInt($foodId);
        $this->assertTrue($data['is_active']);
        $this->assertSame(1, $data['current_version_number']);
        $this->assertSame('g', $data['reference_unit']);
        $this->assertSame($payload['name'], $data['name']);
        $this->assertSame($payload['category'], $data['category']);
        $this->assertSame($payload['source'], $data['source']);
        $this->assertSame($payload['source_code'], $data['source_code']);

        $currentVersion = $data['current_version'];
        $this->assertSame(1, $currentVersion['version_number']);
        $this->assertSame($payload['name'], $currentVersion['name']);
        $this->assertSame($payload['category'], $currentVersion['category']);
        $this->assertSame($payload['source'], $currentVersion['source']);
        $this->assertSame($payload['source_code'], $currentVersion['source_code']);
        $this->assertSame('g', $currentVersion['reference_unit']);
        $this->assertSame($payload['evidence_reference'], $currentVersion['evidence_reference']);
        $this->assertSame($payload['evidence_source'], $currentVersion['evidence_source']);
        $this->assertSame($payload['registered_by'], $currentVersion['registered_by']);
        $this->assertSame($payload['registered_on'], $currentVersion['registered_on']);
        $this->assertNull($currentVersion['change_reason']);
        $this->assertIsString($currentVersion['created_at']);
        $this->assertNotSame('', $currentVersion['created_at']);

        $expectedNutrientIds = [
            $exactZero->id,
            $exactTwoDecimals->id,
            $exactOneDecimal->id,
            $trace->id,
            $na->id,
            $absent->id,
            $reevaluation->id,
        ];
        sort($expectedNutrientIds);

        $this->assertCount(7, $currentVersion['nutrients']);
        $this->assertSame(
            $expectedNutrientIds,
            array_column($currentVersion['nutrients'], 'id'),
        );

        foreach ($currentVersion['nutrients'] as $nutrientItem) {
            $this->assertSame(
                ['amount_value', 'id', 'name', 'status', 'unit'],
                $this->sortedKeys($nutrientItem),
            );
        }

        $zeroItem = $this->nutrientPayloadFor($currentVersion['nutrients'], $exactZero->id);
        $twoDecimalItem = $this->nutrientPayloadFor($currentVersion['nutrients'], $exactTwoDecimals->id);
        $oneDecimalItem = $this->nutrientPayloadFor($currentVersion['nutrients'], $exactOneDecimal->id);
        $traceItem = $this->nutrientPayloadFor($currentVersion['nutrients'], $trace->id);
        $naItem = $this->nutrientPayloadFor($currentVersion['nutrients'], $na->id);
        $absentItem = $this->nutrientPayloadFor($currentVersion['nutrients'], $absent->id);
        $reevaluationItem = $this->nutrientPayloadFor($currentVersion['nutrients'], $reevaluation->id);

        $this->assertSame('0.00', $zeroItem['amount_value']);
        $this->assertSame(FoodNutrient::STATUS_EXACT, $zeroItem['status']);
        $this->assertSame('1.45', $twoDecimalItem['amount_value']);
        $this->assertSame(FoodNutrient::STATUS_EXACT, $twoDecimalItem['status']);
        $this->assertSame('10.20', $oneDecimalItem['amount_value']);
        $this->assertSame(FoodNutrient::STATUS_EXACT, $oneDecimalItem['status']);

        foreach ([
            FoodNutrient::STATUS_TRACE => $traceItem,
            FoodNutrient::STATUS_NA => $naItem,
            FoodNutrient::STATUS_ABSENT => $absentItem,
            FoodNutrient::STATUS_REEVALUATION => $reevaluationItem,
        ] as $expectedStatus => $item) {
            $this->assertSame($expectedStatus, $item['status']);
            $this->assertNull($item['amount_value']);
        }

        $this->assertNotContains($omitted->id, array_column($currentVersion['nutrients'], 'id'));
        $this->assertCount(1, $data['versions']);
        $this->assertSame($currentVersion, $data['versions'][0]);

        $this->assertDatabaseHas('foods', [
            'id' => $foodId,
            'name' => $payload['name'],
            'category' => $payload['category'],
            'source' => $payload['source'],
            'source_code' => $payload['source_code'],
        ]);
        $this->assertDatabaseHas('manual_food_registrations', [
            'food_id' => $foodId,
            'is_active' => true,
            'current_version_number' => 1,
            'reference_unit' => 'g',
        ]);
        $this->assertDatabaseMissing('food_nutrients', [
            'food_id' => $foodId,
            'nutrient_id' => $omitted->id,
        ]);

        $registration = ManualFoodRegistration::query()
            ->where('food_id', $foodId)
            ->firstOrFail();
        $version = $registration->versions()->where('version_number', 1)->firstOrFail();

        $this->assertDatabaseMissing('manual_food_version_nutrients', [
            'manual_food_version_id' => $version->id,
            'nutrient_id' => $omitted->id,
        ]);
        $this->assertSame($tacoFoodSnapshot, $tacoFood->fresh()->getRawOriginal());
        $this->assertSame($tacoRelationSnapshot, $tacoRelation->fresh()->getRawOriginal());
    }

    public function test_get_returns_manual_food_with_full_history_and_allows_null_category(): void
    {
        $nutrient = $this->createNutrient('Proteína GET');
        $payload = $this->validPayload([
            [
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '99999.99',
            ],
        ], ['category' => null]);

        $created = $this->postJson('/api/manual-foods', $payload)
            ->assertCreated()
            ->json('data');

        $this->assertNull($created['category']);
        $this->assertSame('99999.99', $created['current_version']['nutrients'][0]['amount_value']);

        $this->getJson("/api/manual-foods/{$created['id']}")
            ->assertOk()
            ->assertExactJson(['data' => $created]);
    }

    public function test_get_returns_404_for_taco_food_and_nonexistent_food(): void
    {
        $tacoFood = $this->createTacoFood('Alimento TACO protegido');
        $countsBefore = $this->snapshotCounts();

        $this->getJson("/api/manual-foods/{$tacoFood->id}")
            ->assertNotFound();

        $this->getJson('/api/manual-foods/'.PHP_INT_MAX)
            ->assertNotFound();

        $this->assertSame($countsBefore, $this->snapshotCounts());
    }

    public function test_post_applies_http_transport_normalization_before_validation_and_persistence(): void
    {
        $nutrient = $this->createNutrient('Proteína normalizada');

        $response = $this->postJson('/api/manual-foods', [
            'name' => '  Amora preta, polpa pasteurizada  ',
            'category' => '  Frutas e derivados  ',
            'source' => '  FABRICANTE_NORM  ',
            'source_code' => '  AMORA-NORM-01  ',
            'reference_unit' => ' g ',
            'evidence_reference' => '  LAUDO-2026-01  ',
            'evidence_source' => '  Laboratório Central  ',
            'registered_by' => '  Nutricionista Ana  ',
            'registered_on' => ' 2026-11-17 ',
            'nutrients' => [[
                'nutrient_id' => $nutrient->id,
                'status' => ' EXACT ',
                'amount_value' => ' 1.45 ',
            ]],
        ])->assertCreated();

        $data = $response->json('data');
        $this->assertSame('Amora preta, polpa pasteurizada', $data['name']);
        $this->assertSame('Frutas e derivados', $data['category']);
        $this->assertSame('FABRICANTE_NORM', $data['source']);
        $this->assertSame('AMORA-NORM-01', $data['source_code']);
        $this->assertSame('g', $data['reference_unit']);
        $this->assertSame('LAUDO-2026-01', $data['current_version']['evidence_reference']);
        $this->assertSame('Laboratório Central', $data['current_version']['evidence_source']);
        $this->assertSame('Nutricionista Ana', $data['current_version']['registered_by']);
        $this->assertSame('2026-11-17', $data['current_version']['registered_on']);
        $this->assertSame('EXACT', $data['current_version']['nutrients'][0]['status']);
        $this->assertSame('1.45', $data['current_version']['nutrients'][0]['amount_value']);

        $emptyCategoryResponse = $this->postJson('/api/manual-foods', $this->validPayload([
            [
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_TRACE,
                'amount_value' => null,
            ],
        ], ['category' => '   ']))->assertCreated();

        $this->assertNull($emptyCategoryResponse->json('data.category'));
        $this->assertNull($emptyCategoryResponse->json('data.current_version.category'));
    }

    public function test_post_rejects_missing_required_fields_without_modifying_database(): void
    {
        $countsBefore = $this->snapshotCounts();

        $this->postJson('/api/manual-foods', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'source',
                'source_code',
                'reference_unit',
                'evidence_reference',
                'evidence_source',
                'registered_by',
                'registered_on',
                'nutrients',
            ]);

        $this->assertSame($countsBefore, $this->snapshotCounts());
    }

    /**
     * @param  callable(array<string, mixed>, int): array<string, mixed>  $mutator
     */
    #[DataProvider('invalidManualFoodPayloadProvider')]
    public function test_post_rejects_invalid_payloads_without_modifying_database(
        callable $mutator,
        string $expectedErrorKey,
    ): void {
        $nutrient = $this->createNutrient('Nutriente validação');
        $countsBefore = $this->snapshotCounts();

        $payload = $mutator(
            $this->validPayload([
                [
                    'nutrient_id' => $nutrient->id,
                    'status' => FoodNutrient::STATUS_EXACT,
                    'amount_value' => '1.45',
                ],
            ]),
            $nutrient->id,
        );

        $this->postJson('/api/manual-foods', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($expectedErrorKey);

        $this->assertSame($countsBefore, $this->snapshotCounts());
    }

    /**
     * @return array<string, array{callable(array<string, mixed>, int): array<string, mixed>, string}>
     */
    public static function invalidManualFoodPayloadProvider(): array
    {
        return [
            'spaces only name' => [
                function (array $payload): array {
                    $payload['name'] = '   ';

                    return $payload;
                },
                'name',
            ],
            'spaces only source_code' => [
                function (array $payload): array {
                    $payload['source_code'] = '   ';

                    return $payload;
                },
                'source_code',
            ],
            'spaces only evidence_reference' => [
                function (array $payload): array {
                    $payload['evidence_reference'] = '   ';

                    return $payload;
                },
                'evidence_reference',
            ],
            'spaces only evidence_source' => [
                function (array $payload): array {
                    $payload['evidence_source'] = '   ';

                    return $payload;
                },
                'evidence_source',
            ],
            'spaces only registered_by' => [
                function (array $payload): array {
                    $payload['registered_by'] = '   ';

                    return $payload;
                },
                'registered_by',
            ],
            'source TACO uppercase' => [
                function (array $payload): array {
                    $payload['source'] = 'TACO';

                    return $payload;
                },
                'source',
            ],
            'source taco lowercase with spaces' => [
                function (array $payload): array {
                    $payload['source'] = ' taco ';

                    return $payload;
                },
                'source',
            ],
            'reference_unit ml' => [
                function (array $payload): array {
                    $payload['reference_unit'] = 'ml';

                    return $payload;
                },
                'reference_unit',
            ],
            'reference_unit 100g' => [
                function (array $payload): array {
                    $payload['reference_unit'] = '100g';

                    return $payload;
                },
                'reference_unit',
            ],
            'invalid registered_on format' => [
                function (array $payload): array {
                    $payload['registered_on'] = '17/11/2026';

                    return $payload;
                },
                'registered_on',
            ],
            'invalid calendar date' => [
                function (array $payload): array {
                    $payload['registered_on'] = '2026-02-30';

                    return $payload;
                },
                'registered_on',
            ],
            'empty nutrients' => [
                function (array $payload): array {
                    $payload['nutrients'] = [];

                    return $payload;
                },
                'nutrients',
            ],
            'nonexistent nutrient_id' => [
                function (array $payload): array {
                    $payload['nutrients'][0]['nutrient_id'] = PHP_INT_MAX;

                    return $payload;
                },
                'nutrients.0.nutrient_id',
            ],
            'duplicate nutrient_id' => [
                function (array $payload, int $nutrientId): array {
                    $payload['nutrients'][] = [
                        'nutrient_id' => $nutrientId,
                        'status' => FoodNutrient::STATUS_EXACT,
                        'amount_value' => '2.00',
                    ];

                    return $payload;
                },
                'nutrients.1.nutrient_id',
            ],
            'invalid status' => [
                function (array $payload): array {
                    $payload['nutrients'][0]['status'] = 'UNKNOWN';

                    return $payload;
                },
                'nutrients.0.status',
            ],
            'amount_value as JSON float' => [
                function (array $payload): array {
                    $payload['nutrients'][0]['amount_value'] = 1.5;

                    return $payload;
                },
                'nutrients.0.amount_value',
            ],
            'amount_value as JSON integer' => [
                function (array $payload): array {
                    $payload['nutrients'][0]['amount_value'] = 10;

                    return $payload;
                },
                'nutrients.0.amount_value',
            ],
            'amount_value negative' => [
                function (array $payload): array {
                    $payload['nutrients'][0]['amount_value'] = '-1.00';

                    return $payload;
                },
                'nutrients.0.amount_value',
            ],
            'amount_value above 99999.99' => [
                function (array $payload): array {
                    $payload['nutrients'][0]['amount_value'] = '100000.00';

                    return $payload;
                },
                'nutrients.0.amount_value',
            ],
            'amount_value more than 2 decimals' => [
                function (array $payload): array {
                    $payload['nutrients'][0]['amount_value'] = '1.234';

                    return $payload;
                },
                'nutrients.0.amount_value',
            ],
            'amount_value comma decimal' => [
                function (array $payload): array {
                    $payload['nutrients'][0]['amount_value'] = '1,45';

                    return $payload;
                },
                'nutrients.0.amount_value',
            ],
            'amount_value scientific notation' => [
                function (array $payload): array {
                    $payload['nutrients'][0]['amount_value'] = '1e2';

                    return $payload;
                },
                'nutrients.0.amount_value',
            ],
            'amount_value null when EXACT' => [
                function (array $payload): array {
                    $payload['nutrients'][0]['amount_value'] = null;

                    return $payload;
                },
                'nutrients.0.amount_value',
            ],
            'amount_value non-null when TRACE' => [
                function (array $payload): array {
                    $payload['nutrients'][0]['status'] = FoodNutrient::STATUS_TRACE;
                    $payload['nutrients'][0]['amount_value'] = '0.00';

                    return $payload;
                },
                'nutrients.0.amount_value',
            ],
            'amount_value non-null when NA' => [
                function (array $payload): array {
                    $payload['nutrients'][0]['status'] = FoodNutrient::STATUS_NA;
                    $payload['nutrients'][0]['amount_value'] = '1.00';

                    return $payload;
                },
                'nutrients.0.amount_value',
            ],
            'amount_value non-null when ABSENT' => [
                function (array $payload): array {
                    $payload['nutrients'][0]['status'] = FoodNutrient::STATUS_ABSENT;
                    $payload['nutrients'][0]['amount_value'] = '0';

                    return $payload;
                },
                'nutrients.0.amount_value',
            ],
            'amount_value non-null when REEVALUATION' => [
                function (array $payload): array {
                    $payload['nutrients'][0]['status'] = FoodNutrient::STATUS_REEVALUATION;
                    $payload['nutrients'][0]['amount_value'] = '2.50';

                    return $payload;
                },
                'nutrients.0.amount_value',
            ],
        ];
    }

    public function test_post_blocks_normalized_duplicate_source_code_and_name_against_manual_and_taco_foods(): void
    {
        $nutrient = $this->createNutrient('Proteína duplicidade');
        $token = str()->uuid()->toString();

        $existingManual = $this->registerManualFood(
            [[
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '3.40',
            ]],
            [
                'name' => "Maçã, polpa integral {$token}",
                'source' => 'FABRICANTE_DUP',
                'source_code' => "DUP-CODE-{$token}",
            ],
        );
        $existingManualModel = Food::query()->findOrFail($existingManual['id']);
        $existingManualSnapshot = $existingManualModel->fresh()->getRawOriginal();

        $tacoFood = $this->createTacoFood(
            "Caju, polpa TACO {$token}",
            "TACO-DUP-{$token}",
        );
        $tacoSnapshot = $tacoFood->fresh()->getRawOriginal();

        $countsBefore = $this->snapshotCounts();

        $this->postJson('/api/manual-foods', $this->validPayload(
            [[
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '5.00',
            ]],
            [
                'name' => "Outro nome exclusivo {$token}",
                'source' => ' fabricante_dup ',
                'source_code' => '  '.strtolower("dup-code-{$token}").'  ',
            ],
        ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_code');

        $this->postJson('/api/manual-foods', $this->validPayload(
            [[
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '5.00',
            ]],
            [
                'name' => '  '.strtoupper("maca, polpa integral {$token}").'  ',
                'source' => ' fabricante_dup ',
                'source_code' => "DUP-CODE-OUTRO-{$token}",
            ],
        ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->postJson('/api/manual-foods', $this->validPayload(
            [[
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '5.00',
            ]],
            [
                'name' => "Nome exclusivo TACO block {$token}",
                'source' => ' taco ',
                'source_code' => '  '.strtolower("taco-dup-{$token}").'  ',
            ],
        ))->assertUnprocessable();

        $this->postJson('/api/manual-foods', $this->validPayload(
            [[
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '5.00',
            ]],
            [
                'name' => "  CAJU, POLPA TACO {$token}  ",
                'source' => 'TACO',
                'source_code' => "OUTRO-TACO-{$token}",
            ],
        ))->assertUnprocessable();

        $this->assertSame($countsBefore, $this->snapshotCounts());
        $this->assertSame($existingManualSnapshot, $existingManualModel->fresh()->getRawOriginal());
        $this->assertSame($tacoSnapshot, $tacoFood->fresh()->getRawOriginal());

        $this->postJson('/api/manual-foods', $this->validPayload(
            [[
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '3.40',
            ]],
            [
                'name' => "Maçã, polpa integral {$token}",
                'source' => 'OUTRO_FABRICANTE_DUP',
                'source_code' => "DUP-CODE-{$token}",
            ],
        ))->assertCreated();
    }

    public function test_active_manual_food_is_immediately_searchable_and_usable_in_recipes_and_calculations(): void
    {
        $token = str()->uuid()->toString();
        $energy = $this->createNutrient("Energia busca manual {$token}", 'kcal');
        $sodium = $this->createNutrient("Sódio busca manual {$token}", 'mg');
        $omitted = $this->createNutrient("Vitamina omitida busca manual {$token}", 'mg');

        $manual = $this->registerManualFood(
            [
                [
                    'nutrient_id' => $energy->id,
                    'status' => FoodNutrient::STATUS_EXACT,
                    'amount_value' => '45.50',
                ],
                [
                    'nutrient_id' => $sodium->id,
                    'status' => FoodNutrient::STATUS_TRACE,
                    'amount_value' => null,
                ],
            ],
            [
                'name' => "Pitanga, polpa pasteurizada única {$token}",
                'category' => 'Frutas nativas',
                'source' => 'PRODUTOR_LOCAL',
                'source_code' => "PIT-{$token}",
            ],
        );

        $searchResponse = $this->getJson('/api/foods?q='.rawurlencode("pitanga, polpa pasteurizada unica {$token}"))
            ->assertOk();

        $searchItems = $searchResponse->json('data');
        $this->assertCount(1, $searchItems);
        $this->assertSame(
            ['category', 'id', 'name', 'nutrients', 'source', 'source_code'],
            $this->sortedKeys($searchItems[0]),
        );
        $this->assertSame($manual['id'], $searchItems[0]['id']);
        $this->assertSame("Pitanga, polpa pasteurizada única {$token}", $searchItems[0]['name']);
        $this->assertSame('Frutas nativas', $searchItems[0]['category']);
        $this->assertSame('PRODUTOR_LOCAL', $searchItems[0]['source']);
        $this->assertSame("PIT-{$token}", $searchItems[0]['source_code']);
        $this->assertCount(2, $searchItems[0]['nutrients']);

        foreach ($searchItems[0]['nutrients'] as $nutrientItem) {
            $this->assertSame(
                ['amount_value', 'id', 'name', 'status', 'unit'],
                $this->sortedKeys($nutrientItem),
            );
        }

        $searchEnergy = $this->nutrientPayloadFor($searchItems[0]['nutrients'], $energy->id);
        $searchSodium = $this->nutrientPayloadFor($searchItems[0]['nutrients'], $sodium->id);
        $this->assertSame(45.5, $searchEnergy['amount_value']);
        $this->assertSame(FoodNutrient::STATUS_EXACT, $searchEnergy['status']);
        $this->assertNull($searchSodium['amount_value']);
        $this->assertSame(FoodNutrient::STATUS_TRACE, $searchSodium['status']);

        $recipe = $this->postJson('/api/recipes', [
            'name' => "Sorbet de pitanga {$token}",
            'yield_value' => '200',
            'yield_unit' => 'g',
            'ingredients' => [[
                'food_id' => $manual['id'],
                'quantity' => '200',
                'unit' => 'g',
                'position' => 1,
            ]],
        ])->assertSuccessful()->json('data');

        $calculation = $this->postJson("/api/recipes/{$recipe['id']}/nutrition-calculations")
            ->assertSuccessful()
            ->json('data');

        $this->assertSame('nutrition-calculation-v1', $calculation['rules_version']);
        $this->assertSame([
            'id' => $manual['id'],
            'source' => 'PRODUTOR_LOCAL',
            'source_code' => "PIT-{$token}",
        ], $calculation['ingredients'][0]['food']);

        $calcEnergy = $this->calculationNutrientFor($calculation['nutrients'], $energy->id);
        $calcSodium = $this->calculationNutrientFor($calculation['nutrients'], $sodium->id);
        $calcOmitted = $this->calculationNutrientFor($calculation['nutrients'], $omitted->id);

        $this->assertSame(['numerator' => '91', 'denominator' => '1'], $calcEnergy['total']['value']);
        $this->assertSame(['numerator' => '91', 'denominator' => '2'], $calcEnergy['reference']['value']);
        $this->assertTrue($calcEnergy['total']['complete']);
        $this->assertSame('45.50', $calcEnergy['contributions'][0]['source_amount_per_100g']);

        $this->assertNull($calcSodium['total']['value']);
        $this->assertFalse($calcSodium['total']['complete']);
        $this->assertFalse($calcSodium['total']['has_missing_relation']);
        $this->assertSame([FoodNutrient::STATUS_TRACE], $calcSodium['total']['qualitative_states']);
        $this->assertSame(FoodNutrient::STATUS_TRACE, $calcSodium['contributions'][0]['status']);
        $this->assertFalse($calcSodium['contributions'][0]['missing_relation']);

        $this->assertNull($calcOmitted['total']['value']);
        $this->assertFalse($calcOmitted['total']['complete']);
        $this->assertTrue($calcOmitted['total']['has_missing_relation']);
        $this->assertSame([], $calcOmitted['total']['qualitative_states']);
        $this->assertNull($calcOmitted['contributions'][0]['status']);
        $this->assertTrue($calcOmitted['contributions'][0]['missing_relation']);
    }

    public function test_patch_status_deactivates_and_reactivates_manual_food_controlling_search_and_recipes_without_affecting_taco(): void
    {
        $token = str()->uuid()->toString();
        $nutrient = $this->createNutrient("Nutriente status {$token}");

        $tacoFood = $this->createTacoFood("Fruta Status Compartilhada {$token} TACO", "TACO-ST-{$token}");
        $tacoSnapshot = $tacoFood->fresh()->getRawOriginal();

        $manualPrimary = $this->registerManualFood(
            [[
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '8.00',
            ]],
            [
                'name' => "Fruta Status Compartilhada {$token} Manual",
                'source' => 'PRODUTOR_STATUS',
                'source_code' => "ST-1-{$token}",
            ],
        );
        $manualSecondary = $this->registerManualFood(
            [[
                'nutrient_id' => $nutrient->id,
                'status' => FoodNutrient::STATUS_EXACT,
                'amount_value' => '4.00',
            ]],
            [
                'name' => "Fruta Status Secundária {$token}",
                'source' => 'PRODUTOR_STATUS',
                'source_code' => "ST-2-{$token}",
            ],
        );
        $activeOther = $this->createTacoFood("Base Ativa {$token}", "TACO-BASE-{$token}");

        $existingRecipe = Recipe::factory()->create([
            'name' => 'Receita pré-existente com manual',
            'yield_value' => '500',
            'yield_unit' => 'g',
        ]);
        $existingRecipe->ingredients()->create([
            'food_id' => $manualPrimary['id'],
            'quantity' => '100',
            'unit' => 'g',
            'position' => 1,
        ]);

        $otherRecipe = Recipe::factory()->create([
            'name' => 'Outra receita sem manual inativo',
            'yield_value' => '500',
            'yield_unit' => 'g',
        ]);
        $otherRecipe->ingredients()->create([
            'food_id' => $activeOther->id,
            'quantity' => '100',
            'unit' => 'g',
            'position' => 1,
        ]);

        $this->patchJson("/api/manual-foods/{$tacoFood->id}/status", ['is_active' => false])
            ->assertNotFound();
        $this->patchJson('/api/manual-foods/'.PHP_INT_MAX.'/status', ['is_active' => false])
            ->assertNotFound();
        $this->assertSame($tacoSnapshot, $tacoFood->fresh()->getRawOriginal());

        foreach ([
            [],
            ['is_active' => null],
            ['is_active' => 'invalid'],
            ['is_active' => 'false'],
            ['is_active' => 0],
        ] as $invalidStatusPayload) {
            $this->patchJson("/api/manual-foods/{$manualPrimary['id']}/status", $invalidStatusPayload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('is_active');
        }

        $deactivated = $this->patchJson("/api/manual-foods/{$manualPrimary['id']}/status", [
            'is_active' => false,
        ])->assertOk()->json('data');

        $this->assertFalse($deactivated['is_active']);
        $this->assertSame(1, $deactivated['current_version_number']);

        $this->patchJson("/api/manual-foods/{$manualSecondary['id']}/status", [
            'is_active' => false,
        ])->assertOk();

        $searchWhenInactive = $this->getJson('/api/foods?q='.rawurlencode("Fruta Status Compartilhada {$token}"))
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $searchWhenInactive);
        $this->assertSame($tacoFood->id, $searchWhenInactive[0]['id']);

        $this->getJson("/api/manual-foods/{$manualPrimary['id']}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $recipeCountBefore = Recipe::query()->count();
        $this->postJson('/api/recipes', [
            'name' => 'Receita bloqueada por inativo',
            'yield_value' => '200',
            'yield_unit' => 'g',
            'ingredients' => [[
                'food_id' => $manualPrimary['id'],
                'quantity' => '100',
                'unit' => 'g',
                'position' => 1,
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ingredients.0.food_id');
        $this->assertSame($recipeCountBefore, Recipe::query()->count());

        $this->putJson("/api/recipes/{$otherRecipe->id}", [
            'name' => 'Tentativa de adicionar manual inativo novo',
            'yield_value' => '500',
            'yield_unit' => 'g',
            'ingredients' => [
                ['food_id' => $activeOther->id, 'quantity' => '100', 'unit' => 'g', 'position' => 1],
                ['food_id' => $manualPrimary['id'], 'quantity' => '50', 'unit' => 'g', 'position' => 2],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ingredients.1.food_id');
        $this->assertSame(1, $otherRecipe->ingredients()->count());

        $this->putJson("/api/recipes/{$existingRecipe->id}", [
            'name' => 'Receita mantendo manual já vinculado e adicionando outro inativo',
            'yield_value' => '500',
            'yield_unit' => 'g',
            'ingredients' => [
                ['food_id' => $manualPrimary['id'], 'quantity' => '150', 'unit' => 'g', 'position' => 1],
                ['food_id' => $manualSecondary['id'], 'quantity' => '50', 'unit' => 'g', 'position' => 2],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ingredients.1.food_id');

        $updatedExisting = $this->putJson("/api/recipes/{$existingRecipe->id}", [
            'name' => 'Receita mantendo ingrediente inativo pré-existente',
            'yield_value' => '600',
            'yield_unit' => 'g',
            'ingredients' => [
                ['food_id' => $manualPrimary['id'], 'quantity' => '150', 'unit' => 'g', 'position' => 1],
                ['food_id' => $activeOther->id, 'quantity' => '250', 'unit' => 'g', 'position' => 2],
            ],
        ])->assertOk()->json('data');

        $this->assertSame('Receita mantendo ingrediente inativo pré-existente', $updatedExisting['name']);
        $this->assertSame([$manualPrimary['id'], $activeOther->id], array_column($updatedExisting['ingredients'], 'food_id'));

        $reactivated = $this->patchJson("/api/manual-foods/{$manualPrimary['id']}/status", [
            'is_active' => true,
        ])->assertOk()->json('data');
        $this->assertTrue($reactivated['is_active']);

        $searchWhenReactivated = $this->getJson('/api/foods?q='.rawurlencode("Fruta Status Compartilhada {$token}"))
            ->assertOk()
            ->json('data');
        $this->assertCount(2, $searchWhenReactivated);
        $this->assertEqualsCanonicalizing(
            [$tacoFood->id, $manualPrimary['id']],
            array_column($searchWhenReactivated, 'id'),
        );

        $this->postJson('/api/recipes', [
            'name' => 'Receita após reativação',
            'yield_value' => '200',
            'yield_unit' => 'g',
            'ingredients' => [[
                'food_id' => $manualPrimary['id'],
                'quantity' => '100',
                'unit' => 'g',
                'position' => 1,
            ]],
        ])->assertSuccessful();
    }

    private function createNutrient(string $label, string $unit = 'g'): Nutrient
    {
        return Nutrient::query()->create([
            'name' => $label.' '.str()->uuid()->toString(),
            'unit' => $unit,
        ]);
    }

    private function createTacoFood(string $name, ?string $sourceCode = null): Food
    {
        return Food::query()->create([
            'name' => $name,
            'category' => 'Categoria TACO teste',
            'source' => 'TACO',
            'source_code' => $sourceCode ?? 'TACO-TEST-'.str()->uuid()->toString(),
        ]);
    }

    /**
     * @param  list<array{nutrient_id: int, status: string, amount_value: mixed}>  $nutrients
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $nutrients, array $overrides = []): array
    {
        $uniqueSuffix = str()->uuid()->toString();

        return array_merge([
            'name' => "Alimento manual {$uniqueSuffix}",
            'category' => 'Frutas e derivados',
            'source' => 'FABRICANTE_TESTE',
            'source_code' => "MANUAL-{$uniqueSuffix}",
            'reference_unit' => 'g',
            'evidence_reference' => 'LAUDO-LAB-2026-041',
            'evidence_source' => 'Laboratório Analítico Exemplo',
            'registered_by' => 'Nutricionista Responsável',
            'registered_on' => '2026-11-17',
            'nutrients' => $nutrients,
        ], $overrides);
    }

    /**
     * @param  list<array{nutrient_id: int, status: string, amount_value: mixed}>  $nutrients
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registerManualFood(array $nutrients, array $overrides = []): array
    {
        return $this->postJson('/api/manual-foods', $this->validPayload($nutrients, $overrides))
            ->assertCreated()
            ->json('data');
    }

    /**
     * @return array{
     *     foods: int,
     *     food_nutrients: int,
     *     manual_food_registrations: int,
     *     manual_food_versions: int,
     *     manual_food_version_nutrients: int
     * }
     */
    private function snapshotCounts(): array
    {
        return [
            'foods' => DB::table('foods')->count(),
            'food_nutrients' => DB::table('food_nutrients')->count(),
            'manual_food_registrations' => DB::table('manual_food_registrations')->count(),
            'manual_food_versions' => DB::table('manual_food_versions')->count(),
            'manual_food_version_nutrients' => DB::table('manual_food_version_nutrients')->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function sortedKeys(array $values): array
    {
        $keys = array_keys($values);
        sort($keys);

        return $keys;
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
