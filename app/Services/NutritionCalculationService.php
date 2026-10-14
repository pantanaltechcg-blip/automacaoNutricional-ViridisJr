<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FoodNutrient;
use App\Models\Nutrient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use UnexpectedValueException;

class NutritionCalculationService
{
    public function __construct(private NutritionCalculationArithmetic $arithmetic) {}

    /**
     * @return list<array{
     *     recipe_ingredient_id: int,
     *     food_id: int,
     *     nutrient_id: int,
     *     food_nutrient_id: ?int,
     *     input_status: ?string,
     *     input_amount_value: ?string,
     *     is_missing_relation: bool,
     *     value_numerator: ?string,
     *     value_denominator: ?string
     * }>
     */
    public function calculateContributions(Recipe $recipe): array
    {
        $ingredients = $recipe->ingredients()->orderBy('position')->get();
        $nutrients = Nutrient::query()->orderBy('id')->get();
        $foodIds = $ingredients->pluck('food_id')->unique()->values();
        $nutrientIds = $nutrients->modelKeys();
        $foodNutrients = FoodNutrient::query()
            ->whereIn('food_id', $foodIds)
            ->whereIn('nutrient_id', $nutrientIds)
            ->get()
            ->keyBy(fn (FoodNutrient $foodNutrient): string => $this->relationKey(
                (int) $foodNutrient->food_id,
                (int) $foodNutrient->nutrient_id,
            ));
        $contributions = [];

        foreach ($ingredients as $ingredient) {
            foreach ($nutrients as $nutrient) {
                $foodNutrient = $foodNutrients->get($this->relationKey(
                    (int) $ingredient->food_id,
                    (int) $nutrient->getKey(),
                ));

                $contributions[] = $foodNutrient === null
                    ? $this->missingRelationContribution($ingredient, (int) $nutrient->getKey())
                    : $this->relatedContribution($ingredient, $foodNutrient);
            }
        }

        return $contributions;
    }

    /**
     * @return array{
     *     recipe_ingredient_id: int,
     *     food_id: int,
     *     nutrient_id: int,
     *     food_nutrient_id: null,
     *     input_status: null,
     *     input_amount_value: null,
     *     is_missing_relation: true,
     *     value_numerator: null,
     *     value_denominator: null
     * }
     */
    private function missingRelationContribution(RecipeIngredient $ingredient, int $nutrientId): array
    {
        return [
            'recipe_ingredient_id' => (int) $ingredient->getKey(),
            'food_id' => (int) $ingredient->food_id,
            'nutrient_id' => $nutrientId,
            'food_nutrient_id' => null,
            'input_status' => null,
            'input_amount_value' => null,
            'is_missing_relation' => true,
            'value_numerator' => null,
            'value_denominator' => null,
        ];
    }

    /**
     * @return array{
     *     recipe_ingredient_id: int,
     *     food_id: int,
     *     nutrient_id: int,
     *     food_nutrient_id: int,
     *     input_status: string,
     *     input_amount_value: ?string,
     *     is_missing_relation: false,
     *     value_numerator: ?string,
     *     value_denominator: ?string
     * }
     */
    private function relatedContribution(
        RecipeIngredient $ingredient,
        FoodNutrient $foodNutrient,
    ): array {
        $status = $foodNutrient->status;

        if ($status === FoodNutrient::STATUS_EXACT) {
            return $this->exactContribution($ingredient, $foodNutrient);
        }

        if (! in_array($status, [
            FoodNutrient::STATUS_TRACE,
            FoodNutrient::STATUS_NA,
            FoodNutrient::STATUS_ABSENT,
            FoodNutrient::STATUS_REEVALUATION,
        ], true)) {
            throw new UnexpectedValueException("Estado nutricional não suportado: {$status}.");
        }

        return [
            'recipe_ingredient_id' => (int) $ingredient->getKey(),
            'food_id' => (int) $ingredient->food_id,
            'nutrient_id' => (int) $foodNutrient->nutrient_id,
            'food_nutrient_id' => (int) $foodNutrient->getKey(),
            'input_status' => $status,
            'input_amount_value' => null,
            'is_missing_relation' => false,
            'value_numerator' => null,
            'value_denominator' => null,
        ];
    }

    /**
     * @return array{
     *     recipe_ingredient_id: int,
     *     food_id: int,
     *     nutrient_id: int,
     *     food_nutrient_id: int,
     *     input_status: string,
     *     input_amount_value: string,
     *     is_missing_relation: false,
     *     value_numerator: string,
     *     value_denominator: string
     * }
     */
    private function exactContribution(
        RecipeIngredient $ingredient,
        FoodNutrient $foodNutrient,
    ): array {
        $quantity = $ingredient->getRawOriginal('quantity');
        $amountValue = $foodNutrient->getRawOriginal('amount_value');

        if (! is_string($quantity) || ! is_string($amountValue)) {
            throw new UnexpectedValueException('Contribuições EXACT exigem quantidade e valor decimal em string.');
        }

        $ratio = $this->arithmetic->ratio(
            $this->arithmetic->multiplyAndDivide($quantity, $amountValue, '100'),
        );

        return [
            'recipe_ingredient_id' => (int) $ingredient->getKey(),
            'food_id' => (int) $ingredient->food_id,
            'nutrient_id' => (int) $foodNutrient->nutrient_id,
            'food_nutrient_id' => (int) $foodNutrient->getKey(),
            'input_status' => FoodNutrient::STATUS_EXACT,
            'input_amount_value' => $amountValue,
            'is_missing_relation' => false,
            'value_numerator' => $ratio['numerator'],
            'value_denominator' => $ratio['denominator'],
        ];
    }

    private function relationKey(int $foodId, int $nutrientId): string
    {
        return "{$foodId}:{$nutrientId}";
    }
}
