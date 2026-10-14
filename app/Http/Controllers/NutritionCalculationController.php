<?php

namespace App\Http\Controllers;

use App\Http\Resources\NutritionCalculationResource;
use App\Models\Recipe;
use App\Services\NutritionCalculationService;

class NutritionCalculationController extends Controller
{
    public function store(
        Recipe $recipe,
        NutritionCalculationService $nutritionCalculationService,
    ): NutritionCalculationResource {
        return new NutritionCalculationResource(
            $nutritionCalculationService->create($recipe),
        );
    }
}
