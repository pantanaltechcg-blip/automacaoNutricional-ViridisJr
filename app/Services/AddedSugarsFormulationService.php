<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AddedSugarsFormulation;
use App\Models\AddedSugarsFormulationEvidence;
use App\Models\AddedSugarsFormulationIngredient;
use App\Models\AddedSugarsFormulationValidation;
use App\Models\AddedSugarsFormulationVersion;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class AddedSugarsFormulationService
{
    /** @param array<string, mixed> $input */
    public function createVersion(Recipe $recipe, array $input): AddedSugarsFormulation
    {
        return DB::transaction(function () use ($recipe, $input): AddedSugarsFormulation {
            $this->useRepeatableReadIsolation();

            $formulation = AddedSugarsFormulation::query()
                ->where('recipe_id', $recipe->getKey())
                ->lockForUpdate()
                ->first();

            if ($formulation instanceof AddedSugarsFormulation) {
                $nextVersionNumber = $formulation->current_version_number + 1;
                $formulation->update(['current_version_number' => $nextVersionNumber]);
            } else {
                $nextVersionNumber = 1;
                $formulation = AddedSugarsFormulation::query()->create([
                    'recipe_id' => $recipe->getKey(),
                    'current_version_number' => 1,
                ]);
            }

            $version = AddedSugarsFormulationVersion::query()->create([
                'formulation_id' => $formulation->getKey(),
                'recipe_id' => $recipe->getKey(),
                'version_number' => $nextVersionNumber,
                'normative_source' => $input['normative_source'],
                'normative_version' => $input['normative_version'],
                'registered_by' => $input['registered_by'],
                'registered_on' => $input['registered_on'],
                'change_reason' => $input['change_reason'] ?? null,
            ]);

            foreach ($input['ingredients'] as $ingredientData) {
                $ingredient = AddedSugarsFormulationIngredient::query()->create([
                    'formulation_version_id' => $version->getKey(),
                    'food_id' => (int) $ingredientData['food_id'],
                    'position' => (int) $ingredientData['position'],
                    'ingredient_type' => $ingredientData['ingredient_type'],
                    'classification' => $ingredientData['classification'],
                    'amount_basis' => $ingredientData['amount_basis'] ?? null,
                    'amount_value' => $ingredientData['amount_value'] ?? null,
                    'amount_unit' => $ingredientData['amount_unit'] ?? null,
                    'exclusion_justification' => $ingredientData['exclusion_justification'] ?? null,
                    'conflict_notes' => $ingredientData['conflict_notes'] ?? null,
                ]);

                foreach ($ingredientData['evidences'] ?? [] as $evidenceData) {
                    AddedSugarsFormulationEvidence::query()->create([
                        'formulation_ingredient_id' => $ingredient->getKey(),
                        'source_type' => $evidenceData['source_type'],
                        'source_name' => $evidenceData['source_name'],
                        'document_reference' => $evidenceData['document_reference'],
                        'reported_amount_basis' => $evidenceData['reported_amount_basis'] ?? null,
                        'reported_amount_value' => $evidenceData['reported_amount_value'] ?? null,
                        'reported_amount_unit' => $evidenceData['reported_amount_unit'] ?? null,
                        'only_total_sugars_reported' => (bool) ($evidenceData['only_total_sugars_reported'] ?? false),
                        'recorded_by' => $evidenceData['recorded_by'],
                        'recorded_on' => $evidenceData['recorded_on'],
                    ]);
                }
            }

            return $this->loadFormulation($formulation);
        });
    }

    public function retrieveForRecipe(Recipe $recipe): AddedSugarsFormulation
    {
        $formulation = AddedSugarsFormulation::query()
            ->where('recipe_id', $recipe->getKey())
            ->first();

        if (! $formulation instanceof AddedSugarsFormulation) {
            throw (new ModelNotFoundException)->setModel(AddedSugarsFormulation::class);
        }

        return $this->loadFormulation($formulation);
    }

    /** @param array<string, mixed> $input */
    public function recordValidation(
        AddedSugarsFormulationVersion $version,
        array $input,
    ): AddedSugarsFormulationValidation {
        return DB::transaction(function () use ($version, $input): AddedSugarsFormulationValidation {
            $this->useRepeatableReadIsolation();

            return AddedSugarsFormulationValidation::query()->create([
                'formulation_version_id' => $version->getKey(),
                'decision_status' => $input['decision_status'],
                'normative_source' => $input['normative_source'],
                'normative_version' => $input['normative_version'],
                'evidence_type' => $input['evidence_type'],
                'evidence_reference' => $input['evidence_reference'],
                'evidence_source' => $input['evidence_source'],
                'evidence_version' => $input['evidence_version'],
                'evidence_scope' => $input['evidence_scope'],
                'validated_by' => $input['validated_by'],
                'validated_on' => $input['validated_on'],
                'notes' => $input['notes'] ?? null,
            ]);
        });
    }

    private function loadFormulation(AddedSugarsFormulation $formulation): AddedSugarsFormulation
    {
        return $formulation->load([
            'versions' => fn ($q) => $q->orderBy('version_number'),
            'versions.validations' => fn ($q) => $q->orderBy('id'),
            'versions.ingredients' => fn ($q) => $q->orderBy('position'),
            'versions.ingredients.evidences' => fn ($q) => $q->orderBy('id'),
        ]);
    }

    private function useRepeatableReadIsolation(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql' && DB::transactionLevel() === 1) {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }
    }
}
