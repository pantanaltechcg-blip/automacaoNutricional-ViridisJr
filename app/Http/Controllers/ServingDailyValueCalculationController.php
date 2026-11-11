<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServingDailyValueCalculationRequest;
use App\Http\Resources\ServingDailyValueCalculationResource;
use App\Models\NutritionCalculation;
use App\Models\ServingDailyValueCalculation;
use App\Services\ServingDailyValueCalculationService;

class ServingDailyValueCalculationController extends Controller
{
    public function store(
        NutritionCalculation $nutritionCalculation,
        StoreServingDailyValueCalculationRequest $request,
        ServingDailyValueCalculationService $service,
    ): ServingDailyValueCalculationResource {
        /** @var array{portion: array{value: string, unit: string, household_measure: ?string}, portion_authorization_id: int, regulatory_profile_id: int} $validated */
        $validated = $request->validated();

        return new ServingDailyValueCalculationResource($service->create($nutritionCalculation, $validated));
    }

    public function show(
        ServingDailyValueCalculation $servingDailyValueCalculation,
        ServingDailyValueCalculationService $service,
    ): ServingDailyValueCalculationResource {
        return new ServingDailyValueCalculationResource($service->retrieve($servingDailyValueCalculation));
    }
}
