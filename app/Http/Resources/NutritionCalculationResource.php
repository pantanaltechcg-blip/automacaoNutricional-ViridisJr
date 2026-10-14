<?php

namespace App\Http\Resources;

use App\Models\NutritionCalculation;
use App\Models\NutritionCalculationContribution;
use App\Models\NutritionCalculationIngredient;
use App\Models\NutritionCalculationNutrient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NutritionCalculationResource extends JsonResource
{
    /** @var NutritionCalculation */
    public $resource;

    /** @var list<string> */
    private const QUALITATIVE_STATE_ORDER = [
        'TRACE',
        'NA',
        'ABSENT',
        'REEVALUATION',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->getKey(),
            'recipe_id' => (int) $this->resource->recipe_id,
            'rules_version' => $this->resource->rules_version,
            'yield' => [
                'value' => (string) $this->resource->yield_value,
                'unit' => $this->resource->yield_unit,
            ],
            'ingredients' => $this->resource->ingredients
                ->map(fn (NutritionCalculationIngredient $ingredient): array => [
                    'recipe_ingredient_id' => (int) $ingredient->recipe_ingredient_id,
                    'position' => (int) $ingredient->position,
                    'quantity' => (string) $ingredient->quantity,
                    'unit' => $ingredient->unit,
                    'food' => [
                        'id' => (int) $ingredient->food_id,
                        'source' => $ingredient->food_source,
                        'source_code' => $ingredient->food_source_code,
                    ],
                ])
                ->values()
                ->all(),
            'nutrients' => $this->resource->nutrients
                ->map(fn (NutritionCalculationNutrient $nutrient): array => $this->nutrient($nutrient))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function nutrient(NutritionCalculationNutrient $nutrient): array
    {
        $states = $nutrient->states
            ->pluck('state')
            ->sortBy(fn (string $state): int => array_search($state, self::QUALITATIVE_STATE_ORDER, true))
            ->values()
            ->all();

        return [
            'nutrient' => [
                'id' => (int) $nutrient->nutrient_id,
                'name' => $nutrient->nutrient_name,
                'unit' => $nutrient->nutrient_unit,
            ],
            'contributions' => $nutrient->contributions
                ->sortBy(fn (NutritionCalculationContribution $contribution): int => (int) $contribution->ingredient->position)
                ->map(fn (NutritionCalculationContribution $contribution): array => [
                    'recipe_ingredient_id' => (int) $contribution->ingredient->recipe_ingredient_id,
                    'food_nutrient_id' => $contribution->food_nutrient_id === null
                        ? null
                        : (int) $contribution->food_nutrient_id,
                    'status' => $contribution->input_status,
                    'source_amount_per_100g' => $contribution->input_amount_value === null
                        ? null
                        : (string) $contribution->input_amount_value,
                    'missing_relation' => (bool) $contribution->is_missing_relation,
                    'value' => $this->ratio(
                        $contribution->value_numerator,
                        $contribution->value_denominator,
                    ),
                ])
                ->values()
                ->all(),
            'total' => [
                'value' => $this->ratio($nutrient->total_numerator, $nutrient->total_denominator),
                'complete' => (bool) $nutrient->is_complete,
                'qualitative_states' => $states,
                'has_missing_relation' => (bool) $nutrient->has_missing_relation,
            ],
            'reference' => [
                'basis' => (string) $nutrient->reference_basis,
                'unit' => $nutrient->reference_unit,
                'value' => $this->ratio($nutrient->reference_numerator, $nutrient->reference_denominator),
                'complete' => (bool) $nutrient->is_complete,
                'qualitative_states' => $states,
                'has_missing_relation' => (bool) $nutrient->has_missing_relation,
            ],
        ];
    }

    /**
     * @return array{numerator: string, denominator: string}|null
     */
    private function ratio(mixed $numerator, mixed $denominator): ?array
    {
        if ($numerator === null && $denominator === null) {
            return null;
        }

        return [
            'numerator' => (string) $numerator,
            'denominator' => (string) $denominator,
        ];
    }
}
