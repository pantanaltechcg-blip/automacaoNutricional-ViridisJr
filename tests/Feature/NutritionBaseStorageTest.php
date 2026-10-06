<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\FoodNutrient;
use App\Models\Nutrient;
use App\Services\NutritionBaseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class NutritionBaseStorageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_import_preserves_every_structured_taco_record(): void
    {
        $this->artisan('nutrition:import-taco')->assertSuccessful();

        $foods = $this->readCsv('foods.csv');
        $nutrients = $this->readCsv('nutrients.csv');
        $foodNutrients = $this->readCsv('food_nutrients.csv');

        $this->assertCount(597, $foods);
        $this->assertCount(66, $nutrients);
        $this->assertCount(25296, $foodNutrients);
        $this->assertDatabaseCount('foods', count($foods));
        $this->assertDatabaseCount('nutrients', count($nutrients));
        $this->assertDatabaseCount('food_nutrients', count($foodNutrients));

        $expectedFoods = array_map(
            fn (array $food): string => implode('|', [
                $food['source'],
                $food['source_code'],
                $food['name'],
                $food['category'],
            ]),
            $foods,
        );
        $actualFoods = Food::query()->get()
            ->map(fn (Food $food): string => implode('|', [
                $food->source,
                $food->source_code,
                $food->name,
                $food->category ?? '',
            ]))
            ->all();
        $this->assertSame($this->canonicalHash($expectedFoods), $this->canonicalHash($actualFoods));

        $expectedNutrients = array_map(
            fn (array $nutrient): string => "{$nutrient['name']}|{$nutrient['unit']}",
            $nutrients,
        );
        $actualNutrients = Nutrient::query()->get()
            ->map(fn (Nutrient $nutrient): string => "{$nutrient->name}|{$nutrient->unit}")
            ->all();
        $this->assertSame(
            $this->canonicalHash($expectedNutrients),
            $this->canonicalHash($actualNutrients),
        );

        $expectedFoodNutrients = array_map(
            fn (array $foodNutrient): string => implode('|', [
                $foodNutrient['source'],
                $foodNutrient['source_code'],
                $foodNutrient['nutrient_name'],
                $foodNutrient['nutrient_unit'],
                $this->normalizeDecimal($foodNutrient['amount_value']),
                $foodNutrient['status'],
            ]),
            $foodNutrients,
        );
        $actualFoodNutrients = DB::table('food_nutrients')
            ->join('foods', 'foods.id', '=', 'food_nutrients.food_id')
            ->join('nutrients', 'nutrients.id', '=', 'food_nutrients.nutrient_id')
            ->get([
                'foods.source',
                'foods.source_code',
                'nutrients.name as nutrient_name',
                'nutrients.unit as nutrient_unit',
                'food_nutrients.amount_value',
                'food_nutrients.status',
            ])
            ->map(fn (object $foodNutrient): string => implode('|', [
                $foodNutrient->source,
                $foodNutrient->source_code,
                $foodNutrient->nutrient_name,
                $foodNutrient->nutrient_unit,
                $this->normalizeDecimal($foodNutrient->amount_value),
                $foodNutrient->status,
            ]))
            ->all();
        $this->assertSame(
            $this->canonicalHash($expectedFoodNutrients),
            $this->canonicalHash($actualFoodNutrients),
        );

        $expectedStatuses = array_count_values(array_column($foodNutrients, 'status'));
        ksort($expectedStatuses);
        $actualStatuses = DB::table('food_nutrients')
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (int|string $count): int => (int) $count)
            ->sortKeys()
            ->all();

        $this->assertSame([
            'ABSENT' => 4297,
            'EXACT' => 17451,
            'NA' => 907,
            'REEVALUATION' => 76,
            'TRACE' => 2565,
        ], $expectedStatuses);
        $this->assertSame($expectedStatuses, $actualStatuses);

        $this->assertGreaterThan(
            0,
            DB::table('food_nutrients')
                ->where('status', FoodNutrient::STATUS_EXACT)
                ->where('amount_value', 0)
                ->count(),
        );
        $this->assertSame(
            0,
            DB::table('food_nutrients')
                ->whereIn('status', [
                    FoodNutrient::STATUS_TRACE,
                    FoodNutrient::STATUS_NA,
                    FoodNutrient::STATUS_ABSENT,
                    FoodNutrient::STATUS_REEVALUATION,
                ])
                ->whereNotNull('amount_value')
                ->count(),
        );
        $this->assertSame(76, DB::table('food_nutrients')
            ->where('status', FoodNutrient::STATUS_REEVALUATION)
            ->whereNull('amount_value')
            ->count());

        $this->assertSame(
            count($foodNutrients),
            count(array_unique(array_map(
                fn (array $foodNutrient): string => implode('|', [
                    $foodNutrient['source'],
                    $foodNutrient['source_code'],
                    $foodNutrient['nutrient_name'],
                    $foodNutrient['nutrient_unit'],
                ]),
                $foodNutrients,
            ))),
        );
        $this->assertLessThan(count($foods) * count($nutrients), count($foodNutrients));
        $this->assertSame(
            count($foodNutrients),
            DB::table('food_nutrients')->distinct()->count('id'),
        );

        $this->assertSame(
            5,
            count(array_unique([
                FoodNutrient::STATUS_EXACT,
                FoodNutrient::STATUS_TRACE,
                FoodNutrient::STATUS_NA,
                FoodNutrient::STATUS_ABSENT,
                FoodNutrient::STATUS_REEVALUATION,
            ])),
        );
    }

    public function test_reimport_updates_the_same_records_without_changing_food_ids(): void
    {
        $this->artisan('nutrition:import-taco')->assertSuccessful();

        $foodIds = $this->sortByKey(Food::query()->pluck('id', 'source_code')->all());
        $nutrientIds = $this->sortByKey(Nutrient::query()->get()
            ->mapWithKeys(fn (Nutrient $nutrient): array => [
                "{$nutrient->name}|{$nutrient->unit}" => $nutrient->getKey(),
            ])->all());
        $foodNutrientIds = $this->sortByKey(FoodNutrient::query()->get()
            ->mapWithKeys(fn (FoodNutrient $foodNutrient): array => [
                "{$foodNutrient->food_id}|{$foodNutrient->nutrient_id}" => $foodNutrient->getKey(),
            ])->all());

        $this->artisan('nutrition:import-taco')->assertSuccessful();

        $this->assertSame($foodIds, $this->sortByKey(Food::query()->pluck('id', 'source_code')->all()));
        $this->assertSame($nutrientIds, $this->sortByKey(Nutrient::query()->get()
            ->mapWithKeys(fn (Nutrient $nutrient): array => [
                "{$nutrient->name}|{$nutrient->unit}" => $nutrient->getKey(),
            ])->all()));
        $this->assertSame($foodNutrientIds, $this->sortByKey(FoodNutrient::query()->get()
            ->mapWithKeys(fn (FoodNutrient $foodNutrient): array => [
                "{$foodNutrient->food_id}|{$foodNutrient->nutrient_id}" => $foodNutrient->getKey(),
            ])->all()));
        $this->assertDatabaseCount('foods', 597);
        $this->assertDatabaseCount('nutrients', 66);
        $this->assertDatabaseCount('food_nutrients', 25296);
    }

    public function test_food_without_source_code_is_rejected_without_name_fallback(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Todo alimento da TACO deve possuir source_code.');

        app(NutritionBaseService::class)->import([
            [
                'source' => 'TACO',
                'source_code' => '',
                'name' => 'Alimento sem código',
                'category' => null,
            ],
        ], [], []);
    }

    public function test_value_that_does_not_fit_numeric_seven_two_is_rejected_before_persistence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('não cabe em NUMERIC(7,2) sem arredondamento');

        app(NutritionBaseService::class)->import(
            [[
                'source' => 'TACO',
                'source_code' => '1',
                'name' => 'Alimento',
                'category' => null,
            ]],
            [[
                'name' => 'Componente',
                'unit' => 'g',
            ]],
            [[
                'source' => 'TACO',
                'source_code' => '1',
                'nutrient_name' => 'Componente',
                'nutrient_unit' => 'g',
                'amount_value' => '100000.00',
                'status' => FoodNutrient::STATUS_EXACT,
            ]],
        );
    }

    /**
     * @return list<array<string, string>>
     */
    private function readCsv(string $filename): array
    {
        $stream = fopen(resource_path("data/taco/processed/{$filename}"), 'rb');
        $this->assertNotFalse($stream);

        $headers = fgetcsv($stream, 0, ',', '"', '');
        $this->assertIsArray($headers);

        $records = [];
        while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            if ($values === [null] || $values === []) {
                continue;
            }

            $record = array_combine($headers, $values);
            $this->assertIsArray($record);
            $records[] = $record;
        }

        fclose($stream);

        return $records;
    }

    /**
     * @param  list<string>  $records
     */
    private function canonicalHash(array $records): string
    {
        sort($records, SORT_STRING);

        return hash('sha256', implode("\n", $records));
    }

    private function normalizeDecimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        [$integer, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');

        return $integer.'.'.str_pad($fraction, 2, '0');
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param  array<TKey, TValue>  $values
     * @return array<TKey, TValue>
     */
    private function sortByKey(array $values): array
    {
        ksort($values, SORT_STRING);

        return $values;
    }
}
