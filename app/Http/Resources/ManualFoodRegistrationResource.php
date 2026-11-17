<?php

namespace App\Http\Resources;

use App\Models\FoodNutrient;
use App\Models\ManualFoodRegistration;
use App\Models\ManualFoodVersion;
use App\Models\ManualFoodVersionNutrient;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManualFoodRegistrationResource extends JsonResource
{
    /** @var ManualFoodRegistration */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $versions = $this->resource->versions
            ->sortBy('version_number')
            ->values();

        $currentVersion = $versions->firstWhere('version_number', (int) $this->resource->current_version_number)
            ?? $this->resource->currentVersion;

        return [
            'id' => (int) $this->resource->food_id,
            'is_active' => (bool) $this->resource->is_active,
            'current_version_number' => (int) $this->resource->current_version_number,
            'reference_unit' => $this->resource->reference_unit,
            'name' => $this->resource->food->name,
            'category' => $this->resource->food->category,
            'source' => $this->resource->food->source,
            'source_code' => $this->resource->food->source_code,
            'current_version' => $currentVersion instanceof ManualFoodVersion
                ? $this->serializeVersion($currentVersion)
                : null,
            'versions' => $versions
                ->map(fn (ManualFoodVersion $version): array => $this->serializeVersion($version))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeVersion(ManualFoodVersion $version): array
    {
        return [
            'version_number' => (int) $version->version_number,
            'name' => $version->name,
            'category' => $version->category,
            'source' => $version->source,
            'source_code' => $version->source_code,
            'reference_unit' => $version->reference_unit,
            'evidence_reference' => $version->evidence_reference,
            'evidence_source' => $version->evidence_source,
            'registered_by' => $version->registered_by,
            'registered_on' => $this->formatRegisteredOn($version->registered_on),
            'change_reason' => $version->change_reason,
            'created_at' => $this->formatCreatedAt($version->created_at),
            'nutrients' => $version->nutrients
                ->sortBy('nutrient_id')
                ->map(fn (ManualFoodVersionNutrient $versionNutrient): array => [
                    'id' => (int) $versionNutrient->nutrient_id,
                    'name' => $versionNutrient->nutrient->name,
                    'unit' => $versionNutrient->nutrient->unit,
                    'amount_value' => $this->formatAmountValue($versionNutrient),
                    'status' => $versionNutrient->status,
                ])
                ->values()
                ->all(),
        ];
    }

    private function formatAmountValue(ManualFoodVersionNutrient $versionNutrient): ?string
    {
        if ($versionNutrient->status !== FoodNutrient::STATUS_EXACT || $versionNutrient->amount_value === null) {
            return null;
        }

        return (string) BigDecimal::of((string) $versionNutrient->amount_value)->toScale(2);
    }

    private function formatRegisteredOn(mixed $registeredOn): string
    {
        if ($registeredOn instanceof DateTimeInterface) {
            return $registeredOn->format('Y-m-d');
        }

        return (string) $registeredOn;
    }

    private function formatCreatedAt(mixed $createdAt): ?string
    {
        if ($createdAt instanceof CarbonInterface) {
            return $createdAt->toIso8601ZuluString();
        }

        if ($createdAt instanceof DateTimeInterface) {
            return CarbonImmutable::instance($createdAt)->toIso8601ZuluString();
        }

        return $createdAt === null ? null : (string) $createdAt;
    }
}
