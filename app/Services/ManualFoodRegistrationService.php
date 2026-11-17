<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Food;
use App\Models\FoodNutrient;
use App\Models\ManualFoodRegistration;
use App\Models\ManualFoodVersion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualFoodRegistrationService
{
    /**
     * @param  array{
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
     * }  $data
     */
    public function create(array $data): ManualFoodRegistration
    {
        return DB::transaction(function () use ($data): ManualFoodRegistration {
            $this->useRepeatableReadIsolation();

            $normalized = $this->normalizePayload($data, requireChangeReason: false);
            $this->assertBusinessInvariants($normalized);

            $food = Food::query()->create([
                'name' => $normalized['name'],
                'category' => $normalized['category'],
                'source' => $normalized['source'],
                'source_code' => $normalized['source_code'],
            ]);

            $registration = ManualFoodRegistration::query()->create([
                'food_id' => (int) $food->getKey(),
                'is_active' => true,
                'current_version_number' => 1,
                'reference_unit' => $normalized['reference_unit'],
            ]);

            $version = $registration->versions()->create([
                'version_number' => 1,
                'name' => $normalized['name'],
                'category' => $normalized['category'],
                'source' => $normalized['source'],
                'source_code' => $normalized['source_code'],
                'reference_unit' => $normalized['reference_unit'],
                'evidence_reference' => $normalized['evidence_reference'],
                'evidence_source' => $normalized['evidence_source'],
                'registered_by' => $normalized['registered_by'],
                'registered_on' => $normalized['registered_on'],
                'change_reason' => null,
            ]);

            $this->syncNutrients($food, $version, $normalized['nutrients']);

            return $this->loadAggregate($registration);
        });
    }

    public function retrieve(Food $food): ManualFoodRegistration
    {
        $registration = $this->requireManualRegistration($food);

        return $this->loadAggregate($registration);
    }

    /**
     * @param  array{
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
     * }  $data
     */
    public function update(Food $food, array $data): ManualFoodRegistration
    {
        return DB::transaction(function () use ($food, $data): ManualFoodRegistration {
            $this->useRepeatableReadIsolation();

            $targetFood = Food::query()->lockForUpdate()->findOrFail($food->getKey());
            $registration = $this->requireManualRegistration($targetFood, lockForUpdate: true);

            $normalized = $this->normalizePayload($data, requireChangeReason: true);
            $this->assertBusinessInvariants($normalized, (int) $targetFood->getKey());

            $nextVersionNumber = (int) $registration->current_version_number + 1;

            $registration->update([
                'current_version_number' => $nextVersionNumber,
            ]);

            $version = $registration->versions()->create([
                'version_number' => $nextVersionNumber,
                'name' => $normalized['name'],
                'category' => $normalized['category'],
                'source' => $normalized['source'],
                'source_code' => $normalized['source_code'],
                'reference_unit' => $normalized['reference_unit'],
                'evidence_reference' => $normalized['evidence_reference'],
                'evidence_source' => $normalized['evidence_source'],
                'registered_by' => $normalized['registered_by'],
                'registered_on' => $normalized['registered_on'],
                'change_reason' => $normalized['change_reason'],
            ]);

            $targetFood->update([
                'name' => $normalized['name'],
                'category' => $normalized['category'],
                'source' => $normalized['source'],
                'source_code' => $normalized['source_code'],
            ]);

            $targetFood->foodNutrients()->delete();
            $this->syncNutrients($targetFood, $version, $normalized['nutrients']);

            return $this->loadAggregate($registration);
        });
    }

    public function updateStatus(Food $food, bool $isActive): ManualFoodRegistration
    {
        return DB::transaction(function () use ($food, $isActive): ManualFoodRegistration {
            $this->useRepeatableReadIsolation();

            $targetFood = Food::query()->lockForUpdate()->findOrFail($food->getKey());
            $registration = $this->requireManualRegistration($targetFood, lockForUpdate: true);

            $registration->update([
                'is_active' => $isActive,
            ]);

            return $this->loadAggregate($registration);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     name: string,
     *     category: ?string,
     *     source: string,
     *     source_code: string,
     *     reference_unit: string,
     *     evidence_reference: string,
     *     evidence_source: string,
     *     registered_by: string,
     *     registered_on: string,
     *     change_reason: ?string,
     *     nutrients: list<array{nutrient_id: int, status: string, amount_value: ?string}>
     * }
     */
    private function normalizePayload(array $data, bool $requireChangeReason): array
    {
        $category = $data['category'] ?? null;
        $normalizedCategory = is_string($category) && trim($category) !== ''
            ? trim($category)
            : null;

        $changeReason = $data['change_reason'] ?? null;
        $normalizedChangeReason = is_string($changeReason) && trim($changeReason) !== ''
            ? trim($changeReason)
            : null;

        if ($requireChangeReason && $normalizedChangeReason === null) {
            throw ValidationException::withMessages([
                'change_reason' => ['O motivo da alteração é obrigatório ao versionar um alimento manual.'],
            ]);
        }

        $nutrients = [];
        foreach ($data['nutrients'] as $nutrient) {
            $status = (string) $nutrient['status'];
            $amountValue = $nutrient['amount_value'] ?? null;

            $nutrients[] = [
                'nutrient_id' => (int) $nutrient['nutrient_id'],
                'status' => $status,
                'amount_value' => $status === FoodNutrient::STATUS_EXACT
                    ? (is_string($amountValue) ? trim($amountValue) : null)
                    : null,
            ];
        }

        return [
            'name' => trim((string) $data['name']),
            'category' => $normalizedCategory,
            'source' => trim((string) $data['source']),
            'source_code' => trim((string) $data['source_code']),
            'reference_unit' => trim((string) ($data['reference_unit'] ?? 'g')),
            'evidence_reference' => trim((string) $data['evidence_reference']),
            'evidence_source' => trim((string) $data['evidence_source']),
            'registered_by' => trim((string) $data['registered_by']),
            'registered_on' => trim((string) $data['registered_on']),
            'change_reason' => $normalizedChangeReason,
            'nutrients' => $nutrients,
        ];
    }

    /**
     * @param  array{
     *     name: string,
     *     category: ?string,
     *     source: string,
     *     source_code: string,
     *     reference_unit: string,
     *     evidence_reference: string,
     *     evidence_source: string,
     *     registered_by: string,
     *     registered_on: string,
     *     change_reason: ?string,
     *     nutrients: list<array{nutrient_id: int, status: string, amount_value: ?string}>
     * }  $normalized
     */
    private function assertBusinessInvariants(array $normalized, ?int $ignoreFoodId = null): void
    {
        if (strtoupper($normalized['source']) === 'TACO') {
            throw ValidationException::withMessages([
                'source' => ['A origem TACO é reservada à base oficial e não pode ser utilizada no cadastro manual.'],
            ]);
        }

        if ($normalized['reference_unit'] !== 'g') {
            throw ValidationException::withMessages([
                'reference_unit' => ['A unidade de referência suportada para alimentos manuais é apenas g.'],
            ]);
        }

        $this->assertNoNormalizedDuplicates(
            $normalized['source'],
            $normalized['source_code'],
            $normalized['name'],
            $ignoreFoodId,
        );
    }

    private function assertNoNormalizedDuplicates(
        string $source,
        string $sourceCode,
        string $name,
        ?int $ignoreFoodId = null,
    ): void {
        $errors = [];

        $sourceCodeQuery = Food::query()
            ->whereNotNull('source_code')
            ->whereRaw('UPPER(BTRIM(source)) = UPPER(BTRIM(?))', [$source])
            ->whereRaw('UPPER(BTRIM(source_code)) = UPPER(BTRIM(?))', [$sourceCode]);

        if ($ignoreFoodId !== null) {
            $sourceCodeQuery->whereKeyNot($ignoreFoodId);
        }

        if ($sourceCodeQuery->exists()) {
            $errors['source_code'] = ['Já existe um alimento cadastrado com esta fonte e código na fonte.'];
        }

        $nameQuery = Food::query()
            ->whereRaw('UPPER(BTRIM(source)) = UPPER(BTRIM(?))', [$source])
            ->whereRaw('unaccent(LOWER(BTRIM(name))) = unaccent(LOWER(BTRIM(?)))', [$name]);

        if ($ignoreFoodId !== null) {
            $nameQuery->whereKeyNot($ignoreFoodId);
        }

        if ($nameQuery->exists()) {
            $errors['name'] = ['Já existe um alimento cadastrado com este nome normalizado nesta fonte.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function requireManualRegistration(Food $food, bool $lockForUpdate = false): ManualFoodRegistration
    {
        if (strtoupper(trim((string) $food->source)) === 'TACO') {
            throw (new ModelNotFoundException)->setModel(ManualFoodRegistration::class, [$food->getKey()]);
        }

        $query = $food->manualRegistration();

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $registration = $query->first();

        if (! $registration instanceof ManualFoodRegistration) {
            throw (new ModelNotFoundException)->setModel(ManualFoodRegistration::class, [$food->getKey()]);
        }

        return $registration;
    }

    /**
     * @param  list<array{nutrient_id: int, status: string, amount_value: ?string}>  $nutrients
     */
    private function syncNutrients(Food $food, ManualFoodVersion $version, array $nutrients): void
    {
        foreach ($nutrients as $nutrient) {
            FoodNutrient::query()->create([
                'food_id' => (int) $food->getKey(),
                'nutrient_id' => $nutrient['nutrient_id'],
                'amount_value' => $nutrient['amount_value'],
                'status' => $nutrient['status'],
            ]);

            $version->nutrients()->create([
                'nutrient_id' => $nutrient['nutrient_id'],
                'amount_value' => $nutrient['amount_value'],
                'status' => $nutrient['status'],
            ]);
        }
    }

    private function loadAggregate(ManualFoodRegistration $registration): ManualFoodRegistration
    {
        return $registration->load([
            'food',
            'currentVersion.nutrients' => fn ($query) => $query->orderBy('nutrient_id'),
            'currentVersion.nutrients.nutrient',
            'versions' => fn ($query) => $query->orderBy('version_number'),
            'versions.nutrients' => fn ($query) => $query->orderBy('nutrient_id'),
            'versions.nutrients.nutrient',
        ]);
    }

    private function useRepeatableReadIsolation(): void
    {
        if (DB::transactionLevel() === 1) {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }
    }
}
