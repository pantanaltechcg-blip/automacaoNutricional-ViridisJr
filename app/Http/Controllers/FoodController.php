<?php

namespace App\Http\Controllers;

use App\Http\Requests\FoodSearchRequest;
use App\Services\NutritionBaseService;
use Illuminate\Http\JsonResponse;

class FoodController extends Controller
{
    public function search(
        FoodSearchRequest $request,
        NutritionBaseService $nutritionBaseService,
    ): JsonResponse {
        return response()->json([
            'data' => $nutritionBaseService->search($request->queryTerm()),
        ]);
    }
}
