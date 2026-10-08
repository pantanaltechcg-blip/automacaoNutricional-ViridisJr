<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

class RecipeService
{
    /**
     * @param  array{
     *     name: string,
     *     yield_value: string,
     *     yield_unit: string,
     *     ingredients: list<array{food_id: int, quantity: string, unit: string, position: int}>
     * }  $data
     */
    public function create(array $data): Recipe
    {
        return DB::transaction(function () use ($data): Recipe {
            $recipe = Recipe::query()->create([
                'name' => $data['name'],
                'yield_value' => $data['yield_value'],
                'yield_unit' => $data['yield_unit'],
            ]);

            $recipe->ingredients()->createMany($data['ingredients']);

            return $recipe->load('ingredients');
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     yield_value: string,
     *     yield_unit: string,
     *     ingredients: list<array{food_id: int, quantity: string, unit: string, position: int}>
     * }  $data
     */
    public function update(Recipe $recipe, array $data): Recipe
    {
        return DB::transaction(function () use ($recipe, $data): Recipe {
            $recipe->update([
                'name' => $data['name'],
                'yield_value' => $data['yield_value'],
                'yield_unit' => $data['yield_unit'],
            ]);

            $recipe->ingredients()->delete();
            $recipe->ingredients()->createMany($data['ingredients']);

            return $recipe->load('ingredients');
        });
    }

    public function retrieve(Recipe $recipe): Recipe
    {
        return $recipe->load('ingredients');
    }
}
