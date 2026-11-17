<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManualFoodRequest;
use App\Http\Requests\UpdateManualFoodRequest;
use App\Http\Requests\UpdateManualFoodStatusRequest;
use App\Http\Resources\ManualFoodRegistrationResource;
use App\Models\Food;
use App\Services\ManualFoodRegistrationService;
use Illuminate\Http\JsonResponse;

class ManualFoodRegistrationController extends Controller
{
    public function store(
        StoreManualFoodRequest $request,
        ManualFoodRegistrationService $service,
    ): JsonResponse {
        /** @var array{
         *     name: string,
         *     category?: ?string,
         *     source: string,
         *     source_code: string,
         *     reference_unit: string,
         *     evidence_reference: string,
         *     evidence_source: string,
         *     registered_by: string,
         *     registered_on: string,
         *     nutrients: list<array{nutrient_id: int|string, status: string, amount_value?: ?string}>
         * } $validated
         */
        $validated = $request->validated();

        return (new ManualFoodRegistrationResource($service->create($validated)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Food $food,
        ManualFoodRegistrationService $service,
    ): ManualFoodRegistrationResource {
        return new ManualFoodRegistrationResource($service->retrieve($food));
    }

    public function update(
        UpdateManualFoodRequest $request,
        Food $food,
        ManualFoodRegistrationService $service,
    ): ManualFoodRegistrationResource {
        /** @var array{
         *     name: string,
         *     category?: ?string,
         *     source: string,
         *     source_code: string,
         *     reference_unit: string,
         *     evidence_reference: string,
         *     evidence_source: string,
         *     registered_by: string,
         *     registered_on: string,
         *     change_reason: string,
         *     nutrients: list<array{nutrient_id: int|string, status: string, amount_value?: ?string}>
         * } $validated
         */
        $validated = $request->validated();

        return new ManualFoodRegistrationResource($service->update($food, $validated));
    }

    public function updateStatus(
        UpdateManualFoodStatusRequest $request,
        Food $food,
        ManualFoodRegistrationService $service,
    ): ManualFoodRegistrationResource {
        return new ManualFoodRegistrationResource(
            $service->updateStatus($food, (bool) $request->validated('is_active')),
        );
    }
}
