<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\RecipeService;
use Illuminate\Http\JsonResponse;

class RecipeController extends Controller
{
    public function store(
        StoreRecipeRequest $request,
        RecipeService $recipeService,
    ): JsonResponse {
        $recipe = $recipeService->create($request->validated());

        return $this->recipeResponse($recipe);
    }

    public function update(
        UpdateRecipeRequest $request,
        Recipe $recipe,
        RecipeService $recipeService,
    ): JsonResponse {
        $recipe = $recipeService->update($recipe, $request->validated());

        return $this->recipeResponse($recipe);
    }

    public function show(Recipe $recipe, RecipeService $recipeService): JsonResponse
    {
        return $this->recipeResponse($recipeService->retrieve($recipe));
    }

    private function recipeResponse(Recipe $recipe): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'yield_value' => $recipe->yield_value,
                'yield_unit' => $recipe->yield_unit,
                'ingredients' => $recipe->ingredients
                    ->map(static fn (RecipeIngredient $ingredient): array => [
                        'food_id' => $ingredient->food_id,
                        'quantity' => $ingredient->quantity,
                        'unit' => $ingredient->unit,
                        'position' => $ingredient->position,
                    ])
                    ->all(),
            ],
        ]);
    }
}
