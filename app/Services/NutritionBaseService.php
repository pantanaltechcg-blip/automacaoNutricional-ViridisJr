<?php

namespace App\Services;

use App\Models\Food;
use App\Models\FoodNutrient;
use App\Models\Nutrient;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NutritionBaseService
{
    /**
     * @param  list<array{source: string, source_code: string, name: string, category: ?string}>  $foods
     * @param  list<array{name: string, unit: string}>  $nutrients
     * @param  list<array{source: string, source_code: string, nutrient_name: string, nutrient_unit: string, amount_value: ?string, status: string}>  $foodNutrients
     * @return array{foods: int, nutrients: int, food_nutrients: int, statuses: array<string, int>}
     */
    public function import(array $foods, array $nutrients, array $foodNutrients): array
    {
        $this->validateImport($foods, $nutrients, $foodNutrients);

        return DB::transaction(function () use ($foods, $nutrients, $foodNutrients): array {
            $foodIds = $this->persistFoods($foods);
            $nutrientIds = $this->persistNutrients($nutrients);
            $this->persistFoodNutrients($foodNutrients, $foodIds, $nutrientIds);

            return [
                'foods' => count($foods),
                'nutrients' => count($nutrients),
                'food_nutrients' => count($foodNutrients),
                'statuses' => array_count_values(array_column($foodNutrients, 'status')),
            ];
        });
    }

    /**
     * @param  list<array{source: string, source_code: string, name: string, category: ?string}>  $foods
     * @return array<string, int>
     */
    private function persistFoods(array $foods): array
    {
        $foodIds = [];

        foreach ($foods as $foodData) {
            $food = Food::query()->firstOrNew([
                'source' => $foodData['source'],
                'source_code' => $foodData['source_code'],
            ]);
            $food->fill([
                'name' => $foodData['name'],
                'category' => $foodData['category'],
            ]);
            $food->save();

            $foodIds[$this->foodKey($foodData['source'], $foodData['source_code'])] = $food->getKey();
        }

        return $foodIds;
    }

    /**
     * @param  list<array{name: string, unit: string}>  $nutrients
     * @return array<string, int>
     */
    private function persistNutrients(array $nutrients): array
    {
        $nutrientIds = [];

        foreach ($nutrients as $nutrientData) {
            $nutrient = Nutrient::query()->firstOrCreate([
                'name' => $nutrientData['name'],
                'unit' => $nutrientData['unit'],
            ]);

            $nutrientIds[$this->nutrientKey($nutrientData['name'], $nutrientData['unit'])] = $nutrient->getKey();
        }

        return $nutrientIds;
    }

    /**
     * @param  list<array{source: string, source_code: string, nutrient_name: string, nutrient_unit: string, amount_value: ?string, status: string}>  $foodNutrients
     * @param  array<string, int>  $foodIds
     * @param  array<string, int>  $nutrientIds
     */
    private function persistFoodNutrients(array $foodNutrients, array $foodIds, array $nutrientIds): void
    {
        $timestamp = now();
        $rows = [];

        foreach ($foodNutrients as $foodNutrientData) {
            $rows[] = [
                'food_id' => $foodIds[$this->foodKey(
                    $foodNutrientData['source'],
                    $foodNutrientData['source_code'],
                )],
                'nutrient_id' => $nutrientIds[$this->nutrientKey(
                    $foodNutrientData['nutrient_name'],
                    $foodNutrientData['nutrient_unit'],
                )],
                'amount_value' => $foodNutrientData['amount_value'],
                'status' => $foodNutrientData['status'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            FoodNutrient::query()->upsert(
                $chunk,
                ['food_id', 'nutrient_id'],
                ['amount_value', 'status', 'updated_at'],
            );
        }
    }

    /**
     * @param  list<array{source: string, source_code: string, name: string, category: ?string}>  $foods
     * @param  list<array{name: string, unit: string}>  $nutrients
     * @param  list<array{source: string, source_code: string, nutrient_name: string, nutrient_unit: string, amount_value: ?string, status: string}>  $foodNutrients
     */
    private function validateImport(array $foods, array $nutrients, array $foodNutrients): void
    {
        $foodKeys = [];
        foreach ($foods as $food) {
            if ($food['source'] !== 'TACO') {
                throw new InvalidArgumentException('A fonte dos alimentos desta importação deve ser TACO.');
            }

            if (trim($food['source_code']) === '') {
                throw new InvalidArgumentException('Todo alimento da TACO deve possuir source_code.');
            }

            $key = $this->foodKey($food['source'], $food['source_code']);
            if (isset($foodKeys[$key])) {
                throw new InvalidArgumentException("Alimento duplicado na fonte estruturada: {$key}.");
            }

            $foodKeys[$key] = true;
        }

        $nutrientKeys = [];
        foreach ($nutrients as $nutrient) {
            $key = $this->nutrientKey($nutrient['name'], $nutrient['unit']);
            if (isset($nutrientKeys[$key])) {
                throw new InvalidArgumentException("Nutriente duplicado na fonte estruturada: {$key}.");
            }

            $nutrientKeys[$key] = true;
        }

        $relationKeys = [];
        foreach ($foodNutrients as $foodNutrient) {
            $foodKey = $this->foodKey($foodNutrient['source'], $foodNutrient['source_code']);
            $nutrientKey = $this->nutrientKey(
                $foodNutrient['nutrient_name'],
                $foodNutrient['nutrient_unit'],
            );

            if (! isset($foodKeys[$foodKey])) {
                throw new InvalidArgumentException("Relação referencia alimento inexistente: {$foodKey}.");
            }

            if (! isset($nutrientKeys[$nutrientKey])) {
                throw new InvalidArgumentException("Relação referencia nutriente inexistente: {$nutrientKey}.");
            }

            $relationKey = "{$foodKey}|{$nutrientKey}";
            if (isset($relationKeys[$relationKey])) {
                throw new InvalidArgumentException("Relação alimento × nutriente duplicada: {$relationKey}.");
            }

            $relationKeys[$relationKey] = true;
            $this->validateNutrientValue($foodNutrient);
        }
    }

    /**
     * @param  array{source: string, source_code: string, nutrient_name: string, nutrient_unit: string, amount_value: ?string, status: string}  $foodNutrient
     */
    private function validateNutrientValue(array $foodNutrient): void
    {
        $allowedStatuses = [
            FoodNutrient::STATUS_EXACT,
            FoodNutrient::STATUS_TRACE,
            FoodNutrient::STATUS_NA,
            FoodNutrient::STATUS_ABSENT,
            FoodNutrient::STATUS_REEVALUATION,
        ];

        if (! in_array($foodNutrient['status'], $allowedStatuses, true)) {
            throw new InvalidArgumentException("Estado nutricional inválido: {$foodNutrient['status']}.");
        }

        if ($foodNutrient['status'] !== FoodNutrient::STATUS_EXACT) {
            if ($foodNutrient['amount_value'] !== null) {
                throw new InvalidArgumentException(
                    "{$foodNutrient['status']} deve possuir amount_value nulo.",
                );
            }

            return;
        }

        $amountValue = $foodNutrient['amount_value'];
        if ($amountValue === null || preg_match('/^-?\d+(?:\.\d{1,2})?$/', $amountValue) !== 1) {
            throw new InvalidArgumentException('EXACT deve possuir um valor numérico com no máximo duas casas decimais.');
        }

        [$integer, $fraction] = array_pad(explode('.', ltrim($amountValue, '-'), 2), 2, '');
        $integerDigits = max(1, strlen(ltrim($integer, '0')));

        if ($integerDigits > 5 || strlen($fraction) > 2) {
            throw new InvalidArgumentException(
                "Valor {$amountValue} não cabe em NUMERIC(7,2) sem arredondamento.",
            );
        }
    }

    private function foodKey(string $source, string $sourceCode): string
    {
        return "{$source}|{$sourceCode}";
    }

    private function nutrientKey(string $name, string $unit): string
    {
        return "{$name}|{$unit}";
    }
}
