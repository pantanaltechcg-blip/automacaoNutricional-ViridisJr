<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAddedSugarsEvaluationRequest;
use App\Http\Resources\AddedSugarsEvaluationResource;
use App\Models\AddedSugarsEvaluation;
use App\Models\NutritionCalculation;
use App\Services\AddedSugarsEvaluationService;
use Illuminate\Http\JsonResponse;

class AddedSugarsEvaluationController extends Controller
{
    public function __construct(
        private AddedSugarsEvaluationService $service,
    ) {}

    public function store(
        StoreAddedSugarsEvaluationRequest $request,
        NutritionCalculation $nutritionCalculation,
    ): JsonResponse {
        $evaluation = $this->service->create(
            $nutritionCalculation,
            (int) $request->validated('formulation_version_id'),
        );

        return (new AddedSugarsEvaluationResource($evaluation))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AddedSugarsEvaluation $addedSugarsEvaluation): AddedSugarsEvaluationResource
    {
        return new AddedSugarsEvaluationResource(
            $this->service->retrieve($addedSugarsEvaluation),
        );
    }
}
