<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AddedSugarsFormulationValidation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddedSugarsFormulationValidationResource extends JsonResource
{
    /** @var AddedSugarsFormulationValidation */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->getKey(),
            'formulation_version_id' => (int) $this->resource->formulation_version_id,
            'decision_status' => $this->resource->decision_status,
            'normative_source' => $this->resource->normative_source,
            'normative_version' => $this->resource->normative_version,
            'evidence_type' => $this->resource->evidence_type,
            'evidence_reference' => $this->resource->evidence_reference,
            'evidence_source' => $this->resource->evidence_source,
            'evidence_version' => $this->resource->evidence_version,
            'evidence_scope' => $this->resource->evidence_scope,
            'validated_by' => $this->resource->validated_by,
            'validated_on' => $this->resource->validated_on?->format('Y-m-d'),
            'notes' => $this->resource->notes,
        ];
    }
}
