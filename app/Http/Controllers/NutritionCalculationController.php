<?php

namespace App\Http\Controllers;

use App\Http\Resources\NutritionCalculationResource;
use App\Models\NutritionCalculation;
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

    public function show(
        NutritionCalculation $calculation,
        NutritionCalculationService $nutritionCalculationService,
    ): NutritionCalculationResource {
        return new NutritionCalculationResource(
            $nutritionCalculationService->retrieve($calculation),
        );
    }
}
