<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Contracts\View\View;

class RecipeScreenController extends Controller
{
    public function create(): View
    {
        return view('rotulonutri.recipe', [
            'initialRecipe' => null,
        ]);
    }

    public function edit(Recipe $recipe): View
    {
        $recipe->load('ingredients.food');

        return view('rotulonutri.recipe', [
            'initialRecipe' => [
                'recipeId' => (int) $recipe->id,
                'name' => $recipe->name,
                'productType' => $recipe->yield_unit === 'ml' ? 'LIQUID' : 'SOLID',
                'yieldValue' => (string) $recipe->yield_value,
                'ingredients' => $recipe->ingredients
                    ->map(static fn (RecipeIngredient $ingredient): array => [
                        'foodId' => (int) $ingredient->food_id,
                        'name' => $ingredient->food?->name ?? "Alimento #{$ingredient->food_id}",
                        'source' => $ingredient->food?->source ?? '',
                        'quantity' => (string) $ingredient->quantity,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }
}
