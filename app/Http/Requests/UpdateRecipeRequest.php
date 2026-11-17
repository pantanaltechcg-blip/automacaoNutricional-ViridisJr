<?php

namespace App\Http\Requests;

use App\Models\Recipe;
use App\Models\RecipeIngredient;

class UpdateRecipeRequest extends StoreRecipeRequest
{
    /**
     * @return list<int>
     */
    protected function allowedInactiveFoodIds(): array
    {
        $recipe = $this->route('recipe');

        if ($recipe instanceof Recipe) {
            return $recipe->ingredients()
                ->pluck('food_id')
                ->map(static fn (mixed $foodId): int => (int) $foodId)
                ->values()
                ->all();
        }

        if (is_numeric($recipe)) {
            return RecipeIngredient::query()
                ->where('recipe_id', (int) $recipe)
                ->pluck('food_id')
                ->map(static fn (mixed $foodId): int => (int) $foodId)
                ->values()
                ->all();
        }

        return [];
    }
}
