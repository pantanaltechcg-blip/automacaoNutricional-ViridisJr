<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAddedSugarsFormulationRequest;
use App\Http\Requests\StoreAddedSugarsFormulationValidationRequest;
use App\Http\Resources\AddedSugarsFormulationResource;
use App\Http\Resources\AddedSugarsFormulationValidationResource;
use App\Models\AddedSugarsFormulationVersion;
use App\Models\Recipe;
use App\Services\AddedSugarsFormulationService;
use Illuminate\Http\JsonResponse;

class AddedSugarsFormulationController extends Controller
{
    public function __construct(
        private AddedSugarsFormulationService $service,
    ) {}

    public function store(StoreAddedSugarsFormulationRequest $request, Recipe $recipe): JsonResponse
    {
        $formulation = $this->service->createVersion($recipe, $request->validated());

        return (new AddedSugarsFormulationResource($formulation))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Recipe $recipe): AddedSugarsFormulationResource
    {
        return new AddedSugarsFormulationResource(
            $this->service->retrieveForRecipe($recipe),
        );
    }

    public function storeValidation(
        StoreAddedSugarsFormulationValidationRequest $request,
        AddedSugarsFormulationVersion $version,
    ): JsonResponse {
        $validation = $this->service->recordValidation($version, $request->validated());

        return (new AddedSugarsFormulationValidationResource($validation))
            ->response()
            ->setStatusCode(201);
    }
}
