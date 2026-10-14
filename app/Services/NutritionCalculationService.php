<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FoodNutrient;
use App\Models\Nutrient;
use App\Models\NutritionCalculation;
use App\Models\NutritionCalculationContribution;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class NutritionCalculationService
{
    /** @var list<string> */
    private const QUALITATIVE_STATE_ORDER = [
        FoodNutrient::STATUS_TRACE,
        FoodNutrient::STATUS_NA,
        FoodNutrient::STATUS_ABSENT,
        FoodNutrient::STATUS_REEVALUATION,
    ];

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
        $foodNutrients = $this->foodNutrientsFor($ingredients, $nutrients);

        return $this->calculateLoadedContributions($ingredients, $nutrients, $foodNutrients);
    }

    public function create(Recipe $recipe): NutritionCalculation
    {
        return DB::transaction(function () use ($recipe): NutritionCalculation {
            $this->useRepeatableReadIsolation();

            $sourceRecipe = Recipe::query()->findOrFail($recipe->getKey());
            $ingredients = $sourceRecipe->ingredients()
                ->with('food')
                ->orderBy('position')
                ->get();
            $nutrients = Nutrient::query()->orderBy('id')->get();
            $foodNutrients = $this->foodNutrientsFor($ingredients, $nutrients);
            $contributions = $this->calculateLoadedContributions(
                $ingredients,
                $nutrients,
                $foodNutrients,
            );
            $consolidations = $this->consolidateContributions($contributions);
            $yieldValue = $sourceRecipe->getRawOriginal('yield_value');

            if (! is_string($yieldValue)) {
                throw new UnexpectedValueException('O rendimento da receita deve ser preservado como string decimal.');
            }

            $calculation = NutritionCalculation::query()->create([
                'recipe_id' => (int) $sourceRecipe->getKey(),
                'yield_value' => $yieldValue,
                'yield_unit' => $sourceRecipe->yield_unit,
                'rules_version' => NutritionCalculation::RULES_VERSION,
            ]);
            $snapshotIngredientIds = [];

            foreach ($ingredients as $ingredient) {
                $quantity = $ingredient->getRawOriginal('quantity');

                if (! is_string($quantity)) {
                    throw new UnexpectedValueException('A quantidade do ingrediente deve ser preservada como string decimal.');
                }

                $snapshotIngredient = $calculation->ingredients()->create([
                    'recipe_ingredient_id' => (int) $ingredient->getKey(),
                    'position' => (int) $ingredient->position,
                    'quantity' => $quantity,
                    'unit' => $ingredient->unit,
                    'food_id' => (int) $ingredient->food_id,
                    'food_source' => $ingredient->food->source,
                    'food_source_code' => $ingredient->food->source_code,
                ]);
                $snapshotIngredientIds[(int) $ingredient->getKey()] = (int) $snapshotIngredient->getKey();
            }

            $consolidationsByNutrient = collect($consolidations)->keyBy('nutrient_id');
            $snapshotNutrientIds = [];

            foreach ($nutrients as $nutrient) {
                $consolidation = $consolidationsByNutrient->get((int) $nutrient->getKey());

                if (! is_array($consolidation)) {
                    throw new UnexpectedValueException('Todo Nutrient deve possuir consolidação na execução.');
                }

                $reference = $this->normalizeConsolidation($consolidation, $yieldValue);
                $snapshotNutrient = $calculation->nutrients()->create([
                    'nutrient_id' => (int) $nutrient->getKey(),
                    'nutrient_name' => $nutrient->name,
                    'nutrient_unit' => $nutrient->unit,
                    'total_numerator' => $consolidation['total_numerator'],
                    'total_denominator' => $consolidation['total_denominator'],
                    'is_complete' => $consolidation['is_complete'],
                    'has_missing_relation' => $consolidation['has_missing_relation'],
                    'reference_basis' => '100',
                    'reference_unit' => $sourceRecipe->yield_unit,
                    'reference_numerator' => $reference['numerator'] ?? null,
                    'reference_denominator' => $reference['denominator'] ?? null,
                ]);
                $snapshotNutrientIds[(int) $nutrient->getKey()] = (int) $snapshotNutrient->getKey();

                foreach ($consolidation['qualitative_states'] as $state) {
                    $snapshotNutrient->states()->create(['state' => $state]);
                }
            }

            foreach ($contributions as $contribution) {
                NutritionCalculationContribution::query()->create([
                    'nutrition_calculation_ingredient_id' => $snapshotIngredientIds[$contribution['recipe_ingredient_id']],
                    'nutrition_calculation_nutrient_id' => $snapshotNutrientIds[$contribution['nutrient_id']],
                    'food_nutrient_id' => $contribution['food_nutrient_id'],
                    'input_status' => $contribution['input_status'],
                    'input_amount_value' => $contribution['input_amount_value'],
                    'is_missing_relation' => $contribution['is_missing_relation'],
                    'value_numerator' => $contribution['value_numerator'],
                    'value_denominator' => $contribution['value_denominator'],
                ]);
            }

            return $this->loadAggregate($calculation);
        });
    }

    /**
     * @param  Collection<int, RecipeIngredient>  $ingredients
     * @param  Collection<int, Nutrient>  $nutrients
     * @return Collection<string, FoodNutrient>
     */
    private function foodNutrientsFor(Collection $ingredients, Collection $nutrients): Collection
    {
        $foodIds = $ingredients->pluck('food_id')->unique()->values();
        $nutrientIds = $nutrients->modelKeys();

        return FoodNutrient::query()
            ->whereIn('food_id', $foodIds)
            ->whereIn('nutrient_id', $nutrientIds)
            ->get()
            ->keyBy(fn (FoodNutrient $foodNutrient): string => $this->relationKey(
                (int) $foodNutrient->food_id,
                (int) $foodNutrient->nutrient_id,
            ));
    }

    /**
     * @param  Collection<int, RecipeIngredient>  $ingredients
     * @param  Collection<int, Nutrient>  $nutrients
     * @param  Collection<string, FoodNutrient>  $foodNutrients
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
    private function calculateLoadedContributions(
        Collection $ingredients,
        Collection $nutrients,
        Collection $foodNutrients,
    ): array {
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
     * @param  list<array{
     *     recipe_ingredient_id: int,
     *     food_id: int,
     *     nutrient_id: int,
     *     food_nutrient_id: ?int,
     *     input_status: ?string,
     *     input_amount_value: ?string,
     *     is_missing_relation: bool,
     *     value_numerator: ?string,
     *     value_denominator: ?string
     * }>  $contributions
     * @return list<array{
     *     nutrient_id: int,
     *     total_numerator: ?string,
     *     total_denominator: ?string,
     *     is_complete: bool,
     *     has_missing_relation: bool,
     *     qualitative_states: list<string>
     * }>
     */
    public function consolidateContributions(array $contributions): array
    {
        $contributionsByNutrient = [];

        foreach ($contributions as $contribution) {
            $contributionsByNutrient[$contribution['nutrient_id']][] = $contribution;
        }

        ksort($contributionsByNutrient, SORT_NUMERIC);
        $consolidations = [];

        foreach ($contributionsByNutrient as $nutrientId => $nutrientContributions) {
            $total = null;
            $isComplete = true;
            $hasMissingRelation = false;
            $stateSet = [];

            foreach ($nutrientContributions as $contribution) {
                if ($contribution['is_missing_relation']) {
                    $hasMissingRelation = true;
                    $isComplete = false;

                    continue;
                }

                $status = $contribution['input_status'];

                if ($status === FoodNutrient::STATUS_EXACT) {
                    $numerator = $contribution['value_numerator'];
                    $denominator = $contribution['value_denominator'];

                    if (! is_string($numerator) || ! is_string($denominator)) {
                        throw new UnexpectedValueException('Contribuições EXACT exigem uma razão numérica.');
                    }

                    $value = $this->arithmetic->fromRatio($numerator, $denominator);
                    $total = $total === null
                        ? $value
                        : $this->arithmetic->addRationals($total, $value);

                    continue;
                }

                if (! is_string($status) || ! in_array($status, self::QUALITATIVE_STATE_ORDER, true)) {
                    throw new UnexpectedValueException('Contribuição sem relação ou estado nutricional reconhecido.');
                }

                $stateSet[$status] = true;
                $isComplete = false;
            }

            $ratio = $total === null ? null : $this->arithmetic->ratio($total);
            $qualitativeStates = array_values(array_filter(
                self::QUALITATIVE_STATE_ORDER,
                fn (string $state): bool => isset($stateSet[$state]),
            ));

            $consolidations[] = [
                'nutrient_id' => (int) $nutrientId,
                'total_numerator' => $ratio['numerator'] ?? null,
                'total_denominator' => $ratio['denominator'] ?? null,
                'is_complete' => $isComplete,
                'has_missing_relation' => $hasMissingRelation,
                'qualitative_states' => $qualitativeStates,
            ];
        }

        return $consolidations;
    }

    /**
     * @param  array{
     *     nutrient_id: int,
     *     total_numerator: ?string,
     *     total_denominator: ?string,
     *     is_complete: bool,
     *     has_missing_relation: bool,
     *     qualitative_states: list<string>
     * }  $consolidation
     * @return array{numerator: string, denominator: string}|null
     */
    private function normalizeConsolidation(array $consolidation, string $yieldValue): ?array
    {
        $numerator = $consolidation['total_numerator'];
        $denominator = $consolidation['total_denominator'];

        if ($numerator === null && $denominator === null) {
            return null;
        }

        if (! is_string($numerator) || ! is_string($denominator)) {
            throw new UnexpectedValueException('A consolidação numérica deve possuir uma razão completa.');
        }

        return $this->arithmetic->ratio(
            $this->arithmetic->normalizeRatio($numerator, $denominator, $yieldValue),
        );
    }

    private function loadAggregate(NutritionCalculation $calculation): NutritionCalculation
    {
        return $calculation->load([
            'ingredients' => fn ($query) => $query->orderBy('position'),
            'nutrients' => fn ($query) => $query->orderBy('nutrient_id'),
            'nutrients.states',
            'nutrients.contributions.ingredient',
        ]);
    }

    private function useRepeatableReadIsolation(): void
    {
        if (DB::transactionLevel() === 1) {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }
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
