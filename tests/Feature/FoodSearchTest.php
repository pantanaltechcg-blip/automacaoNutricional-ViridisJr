<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\FoodNutrient;
use App\Models\Nutrient;
use App\Services\NutritionBaseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FoodSearchTest extends TestCase
{
    use DatabaseTransactions;

    public function test_search_returns_foods_by_partial_name(): void
    {
        $food = $this->createFood('Abacaxi, polpa, teste de busca parcial');

        $response = $this->getJson('/api/foods?q=polpa%2C%20teste');

        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $food->id)
            ->assertJsonPath('data.0.name', $food->name);
    }

    public function test_search_ignores_case(): void
    {
        $food = $this->createFood('Banana, CASE TEST, assada');

        $response = $this->getJson('/api/foods?q=banana%2C%20case%20test%2C%20assada');

        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $food->id);
    }

    public function test_search_ignores_accents(): void
    {
        $food = $this->createFood('Maçã, crua, teste de acentuação');

        $response = $this->getJson('/api/foods?q=maca%2C%20crua%2C%20teste%20de%20acentuacao');

        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $food->id)
            ->assertJsonPath('data.0.name', 'Maçã, crua, teste de acentuação');
    }

    public function test_search_without_matches_returns_an_empty_list(): void
    {
        $this->getJson('/api/foods?q=termo-inexistente-para-taco')
            ->assertSuccessful()
            ->assertExactJson(['data' => []]);
    }

    public function test_external_spaces_are_removed_before_the_service_receives_the_query(): void
    {
        $this->mock(NutritionBaseService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('search')
                ->once()
                ->with('maca')
                ->andReturn([]);
        });

        $this->getJson('/api/foods?q=%20maca%20')
            ->assertSuccessful()
            ->assertExactJson(['data' => []]);
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_query_is_rejected_before_the_service_is_called(string $uri): void
    {
        $this->mock(NutritionBaseService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('search');
        });

        $this->getJson($uri)->assertJsonValidationErrors('q');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidQueries(): array
    {
        return [
            'absent' => ['/api/foods'],
            'empty' => ['/api/foods?q='],
            'spaces only' => ['/api/foods?q=%20%20%20'],
        ];
    }

    public function test_response_follows_the_contract_and_preserves_all_nutrient_states(): void
    {
        $food = $this->createFood('Alimento contrato único', 'Categoria de teste', 'CONTRACT-1');
        $relations = [
            $this->createFoodNutrient($food, 'Componente exato', 'g', '12.34', FoodNutrient::STATUS_EXACT),
            $this->createFoodNutrient($food, 'Componente traço', 'mg', null, FoodNutrient::STATUS_TRACE),
            $this->createFoodNutrient($food, 'Componente não aplicável', 'mg', null, FoodNutrient::STATUS_NA),
            $this->createFoodNutrient($food, 'Componente ausente', 'µg', null, FoodNutrient::STATUS_ABSENT),
            $this->createFoodNutrient($food, 'Componente em reavaliação', 'kcal', null, FoodNutrient::STATUS_REEVALUATION),
        ];

        $response = $this->getJson('/api/foods?q=contrato%20unico')->assertSuccessful();
        $payload = $response->json();

        $this->assertCount(1, $payload['data']);
        $this->assertSame(
            ['category', 'id', 'name', 'nutrients', 'source', 'source_code'],
            $this->sortedKeys($payload['data'][0]),
        );
        $this->assertSame($food->id, $payload['data'][0]['id']);
        $this->assertSame('Alimento contrato único', $payload['data'][0]['name']);
        $this->assertSame('Categoria de teste', $payload['data'][0]['category']);
        $this->assertSame('TACO', $payload['data'][0]['source']);
        $this->assertSame('CONTRACT-1', $payload['data'][0]['source_code']);
        $this->assertCount(5, $payload['data'][0]['nutrients']);

        $nutrientsByStatus = collect($payload['data'][0]['nutrients'])->keyBy('status');
        $this->assertSame([
            FoodNutrient::STATUS_ABSENT,
            FoodNutrient::STATUS_EXACT,
            FoodNutrient::STATUS_NA,
            FoodNutrient::STATUS_REEVALUATION,
            FoodNutrient::STATUS_TRACE,
        ], $nutrientsByStatus->keys()->sort()->values()->all());

        foreach ($nutrientsByStatus as $nutrient) {
            $this->assertSame(
                ['amount_value', 'id', 'name', 'status', 'unit'],
                $this->sortedKeys($nutrient),
            );
        }

        $this->assertSame(12.34, $nutrientsByStatus[FoodNutrient::STATUS_EXACT]['amount_value']);
        foreach ([
            FoodNutrient::STATUS_TRACE,
            FoodNutrient::STATUS_NA,
            FoodNutrient::STATUS_ABSENT,
            FoodNutrient::STATUS_REEVALUATION,
        ] as $status) {
            $this->assertNull($nutrientsByStatus[$status]['amount_value']);
        }

        $this->assertSame(
            collect($relations)->map(fn (FoodNutrient $relation): int => $relation->nutrient_id)->sort()->values()->all(),
            $nutrientsByStatus->pluck('id')->sort()->values()->all(),
        );
    }

    private function createFood(
        string $name,
        ?string $category = 'Categoria de teste',
        ?string $sourceCode = 'SEARCH-TEST',
    ): Food {
        return Food::query()->create([
            'name' => $name,
            'category' => $category,
            'source' => 'TACO',
            'source_code' => $sourceCode,
        ]);
    }

    private function createFoodNutrient(
        Food $food,
        string $name,
        string $unit,
        ?string $amountValue,
        string $status,
    ): FoodNutrient {
        $nutrient = Nutrient::query()->create([
            'name' => $name,
            'unit' => $unit,
        ]);

        return FoodNutrient::query()->create([
            'food_id' => $food->id,
            'nutrient_id' => $nutrient->id,
            'amount_value' => $amountValue,
            'status' => $status,
        ]);
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
}
