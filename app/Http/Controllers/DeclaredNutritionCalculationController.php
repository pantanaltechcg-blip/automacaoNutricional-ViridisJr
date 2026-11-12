<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeclaredNutritionCalculationRequest;
use App\Http\Resources\DeclaredNutritionCalculationResource;
use App\Models\DeclaredNutritionCalculation;
use App\Models\ServingDailyValueCalculation;
use App\Services\DeclaredNutritionCalculationService;
use Illuminate\Http\JsonResponse;

class DeclaredNutritionCalculationController extends Controller
{
    public function store(
        ServingDailyValueCalculation $servingDailyValueCalculation,
        StoreDeclaredNutritionCalculationRequest $request,
        DeclaredNutritionCalculationService $service,
    ): JsonResponse {
        /** @var array{regulatory_declaration_profile_id: int} $validated */
        $validated = $request->validated();

        return (new DeclaredNutritionCalculationResource(
            $service->create($servingDailyValueCalculation, $validated),
        ))->response()->setStatusCode(201);
    }

    public function show(
        DeclaredNutritionCalculation $declaredNutritionCalculation,
        DeclaredNutritionCalculationService $service,
    ): DeclaredNutritionCalculationResource {
        return new DeclaredNutritionCalculationResource($service->retrieve($declaredNutritionCalculation));
    }
}
