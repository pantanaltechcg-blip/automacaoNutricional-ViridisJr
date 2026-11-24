<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AddedSugarsEvaluation;
use App\Models\AddedSugarsEvaluationCause;
use App\Models\AddedSugarsEvaluationItem;
use App\Models\AddedSugarsFormulation;
use App\Models\AddedSugarsFormulationEvidence;
use App\Models\AddedSugarsFormulationIngredient;
use App\Models\AddedSugarsFormulationValidation;
use App\Models\AddedSugarsFormulationVersion;
use App\Models\Food;
use App\Models\NutritionCalculation;
use App\Models\NutritionCalculationIngredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AddedSugarsFormulationPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_all_planned_postgresql_tables_and_additive_columns_exist(): void
    {
        $tables = [
            'added_sugars_formulations',
            'added_sugars_formulation_versions',
            'added_sugars_formulation_ingredients',
            'added_sugars_formulation_evidences',
            'added_sugars_formulation_validations',
            'added_sugars_evaluations',
            'added_sugars_evaluation_items',
            'added_sugars_evaluation_causes',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('serving_daily_value_calculations', [
            'added_sugars_evaluation_id',
            'added_sugars_formulation_version_number',
            'added_sugars_validation_status',
            'added_sugars_is_regulatory_eligible',
            'added_sugars_is_confirmed_zero',
            'added_sugars_reference_numerator',
            'added_sugars_reference_denominator',
            'added_sugars_reference_unit',
        ]));

        $this->assertTrue(Schema::hasColumns('serving_daily_value_regulatory_results', [
            'value_source_type',
            'source_reference_numerator',
            'source_reference_denominator',
        ]));
    }

    public function test_check_constraints_reject_invalid_ingredient_and_evaluation_rows(): void
    {
        [, $food, $recipe] = $this->seedRecipeGraph();

        $formulation = AddedSugarsFormulation::query()->create([
            'recipe_id' => $recipe->getKey(),
            'current_version_number' => 1,
        ]);

        $version = AddedSugarsFormulationVersion::query()->create([
            'formulation_id' => $formulation->getKey(),
            'recipe_id' => $recipe->getKey(),
            'version_number' => 1,
            'normative_source' => 'RDC nº 429/2020',
            'normative_version' => '2020-ret-2022',
            'registered_by' => 'Nutricionista',
            'registered_on' => '2026-11-23',
        ]);

        // PER_100G > 100 rejected
        $this->assertQueryRejected(fn () => AddedSugarsFormulationIngredient::query()->create([
            'formulation_version_id' => $version->getKey(),
            'food_id' => $food->getKey(),
            'position' => 1,
            'ingredient_type' => 'SIMPLE',
            'classification' => 'INCLUDED',
            'amount_basis' => 'PER_100G',
            'amount_value' => '100.01',
            'amount_unit' => 'g',
        ]));

        // Negative amount rejected
        $this->assertQueryRejected(fn () => AddedSugarsFormulationIngredient::query()->create([
            'formulation_version_id' => $version->getKey(),
            'food_id' => $food->getKey(),
            'position' => 1,
            'ingredient_type' => 'SIMPLE',
            'classification' => 'INCLUDED',
            'amount_basis' => 'PER_100G',
            'amount_value' => '-1',
            'amount_unit' => 'g',
        ]));

        // EXCLUDED_NORMATIVE without justification rejected
        $this->assertQueryRejected(fn () => AddedSugarsFormulationIngredient::query()->create([
            'formulation_version_id' => $version->getKey(),
            'food_id' => $food->getKey(),
            'position' => 1,
            'ingredient_type' => 'SIMPLE',
            'classification' => 'EXCLUDED_NORMATIVE',
        ]));

        // CONFLICTING without conflict_notes rejected
        $this->assertQueryRejected(fn () => AddedSugarsFormulationIngredient::query()->create([
            'formulation_version_id' => $version->getKey(),
            'food_id' => $food->getKey(),
            'position' => 1,
            'ingredient_type' => 'COMPOUND_INDUSTRIALIZED',
            'classification' => 'CONFLICTING',
        ]));

        // Non-eligible evaluation with non-null total rejected
        [$calc] = $this->seedRecipeGraph();
        $this->assertQueryRejected(fn () => AddedSugarsEvaluation::query()->create([
            'nutrition_calculation_id' => $calc->getKey(),
            'formulation_id' => $formulation->getKey(),
            'formulation_version_id' => $version->getKey(),
            'source_recipe_id' => $recipe->getKey(),
            'source_rules_version' => 'nutrition-calculation-v1',
            'source_yield_value' => '500',
            'source_yield_unit' => 'g',
            'formulation_version_number' => 1,
            'normative_source' => 'RDC nº 429/2020',
            'normative_version' => '2020-ret-2022',
            'validation_status' => 'PENDING',
            'total_numerator' => '10',
            'total_denominator' => '1',
            'total_unit' => 'g',
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => '2',
            'reference_denominator' => '1',
            'is_complete' => false,
            'is_regulatory_eligible' => false,
            'is_confirmed_zero' => false,
        ]));
    }

    public function test_immutability_triggers_block_update_and_delete_on_all_seven_history_tables(): void
    {
        [$calc, $food, $recipe, $calcIngredient] = $this->seedRecipeGraph();

        $formulation = AddedSugarsFormulation::query()->create([
            'recipe_id' => $recipe->getKey(),
            'current_version_number' => 1,
        ]);

        $version = AddedSugarsFormulationVersion::query()->create([
            'formulation_id' => $formulation->getKey(),
            'recipe_id' => $recipe->getKey(),
            'version_number' => 1,
            'normative_source' => 'RDC nº 429/2020',
            'normative_version' => '2020-ret-2022',
            'registered_by' => 'Nutricionista',
            'registered_on' => '2026-11-23',
        ]);

        $ingredient = AddedSugarsFormulationIngredient::query()->create([
            'formulation_version_id' => $version->getKey(),
            'food_id' => $food->getKey(),
            'position' => 1,
            'ingredient_type' => 'SIMPLE',
            'classification' => 'INCLUDED',
            'amount_basis' => 'PER_100G',
            'amount_value' => '50',
            'amount_unit' => 'g',
        ]);

        $evidence = AddedSugarsFormulationEvidence::query()->create([
            'formulation_ingredient_id' => $ingredient->getKey(),
            'source_type' => 'MANUFACTURER_SPEC',
            'source_name' => 'Fabricante X',
            'document_reference' => 'FT-01',
            'reported_amount_basis' => 'PER_100G',
            'reported_amount_value' => '50',
            'reported_amount_unit' => 'g',
            'only_total_sugars_reported' => false,
            'recorded_by' => 'Nutricionista',
            'recorded_on' => '2026-11-23',
        ]);

        $validation = AddedSugarsFormulationValidation::query()->create([
            'formulation_version_id' => $version->getKey(),
            'decision_status' => 'AUTHORIZED',
            'normative_source' => 'RDC nº 429/2020',
            'normative_version' => '2020-ret-2022',
            'evidence_type' => 'TECHNICAL_REVIEW',
            'evidence_reference' => 'PARECER-1',
            'evidence_source' => 'Viridis Jr.',
            'evidence_version' => '1.0',
            'evidence_scope' => "recipe:{$recipe->getKey()}/added-sugars-formulation:v1",
            'validated_by' => 'CRN-1234',
            'validated_on' => '2026-11-23',
        ]);

        $evaluation = AddedSugarsEvaluation::query()->create([
            'nutrition_calculation_id' => $calc->getKey(),
            'formulation_id' => $formulation->getKey(),
            'formulation_version_id' => $version->getKey(),
            'validation_id' => $validation->getKey(),
            'source_recipe_id' => $recipe->getKey(),
            'source_rules_version' => 'nutrition-calculation-v1',
            'source_yield_value' => '500',
            'source_yield_unit' => 'g',
            'formulation_version_number' => 1,
            'normative_source' => 'RDC nº 429/2020',
            'normative_version' => '2020-ret-2022',
            'validation_status' => 'AUTHORIZED',
            'total_numerator' => '50',
            'total_denominator' => '1',
            'total_unit' => 'g',
            'reference_basis' => '100',
            'reference_unit' => 'g',
            'reference_numerator' => '10',
            'reference_denominator' => '1',
            'is_complete' => true,
            'is_regulatory_eligible' => true,
            'is_confirmed_zero' => false,
        ]);

        $item = AddedSugarsEvaluationItem::query()->create([
            'evaluation_id' => $evaluation->getKey(),
            'nutrition_calculation_ingredient_id' => $calcIngredient->getKey(),
            'formulation_ingredient_id' => $ingredient->getKey(),
            'position' => 1,
            'food_id' => $food->getKey(),
            'food_source' => 'TACO',
            'ingredient_quantity' => '100',
            'ingredient_unit' => 'g',
            'ingredient_type' => 'SIMPLE',
            'classification' => 'INCLUDED',
            'amount_basis' => 'PER_100G',
            'amount_value' => '50',
            'contribution_numerator' => '50',
            'contribution_denominator' => '1',
            'is_excluded_normative' => false,
            'is_complete' => true,
            'evidence_count' => 1,
        ]);

        $cause = AddedSugarsEvaluationCause::query()->create([
            'evaluation_id' => $evaluation->getKey(),
            'evaluation_item_id' => $item->getKey(),
            'cause_type' => 'INFO',
            'cause_code' => 'AUDIT_NOTE',
            'detail' => 'Teste imutabilidade',
        ]);

        $targets = [
            ['added_sugars_formulation_versions', $version->getKey(), ['registered_by' => 'Alterado']],
            ['added_sugars_formulation_ingredients', $ingredient->getKey(), ['amount_value' => '99']],
            ['added_sugars_formulation_evidences', $evidence->getKey(), ['source_name' => 'Outro']],
            ['added_sugars_formulation_validations', $validation->getKey(), ['decision_status' => 'REVOKED']],
            ['added_sugars_evaluations', $evaluation->getKey(), ['is_confirmed_zero' => true]],
            ['added_sugars_evaluation_items', $item->getKey(), ['is_complete' => false]],
            ['added_sugars_evaluation_causes', $cause->getKey(), ['detail' => 'Alterado']],
        ];

        foreach ($targets as [$table, $id, $update]) {
            $this->assertQueryRejected(fn () => DB::table($table)->where('id', $id)->update($update));
            $this->assertQueryRejected(fn () => DB::table($table)->where('id', $id)->delete());
        }
    }

    /** @return array{0: NutritionCalculation, 1: Food, 2: Recipe, 3: NutritionCalculationIngredient} */
    private function seedRecipeGraph(): array
    {
        $food = Food::query()->create([
            'name' => 'Açúcar cristal teste '.uniqid(),
            'category' => 'Açúcares',
            'source' => 'TACO',
            'source_code' => 'TACO-AA-'.uniqid(),
        ]);

        $recipe = Recipe::query()->create([
            'name' => 'Geleia teste '.uniqid(),
            'yield_value' => '500',
            'yield_unit' => 'g',
        ]);

        $recipeIngredient = RecipeIngredient::query()->create([
            'recipe_id' => $recipe->getKey(),
            'food_id' => $food->getKey(),
            'quantity' => '100',
            'unit' => 'g',
            'position' => 1,
        ]);

        $calc = NutritionCalculation::query()->create([
            'recipe_id' => $recipe->getKey(),
            'rules_version' => 'nutrition-calculation-v1',
            'yield_value' => '500',
            'yield_unit' => 'g',
        ]);

        $calcIngredient = NutritionCalculationIngredient::query()->create([
            'nutrition_calculation_id' => $calc->getKey(),
            'recipe_ingredient_id' => $recipeIngredient->getKey(),
            'food_id' => $food->getKey(),
            'food_source' => $food->source,
            'food_source_code' => $food->source_code,
            'quantity' => '100',
            'unit' => 'g',
            'position' => 1,
        ]);

        return [$calc, $food, $recipe, $calcIngredient];
    }

    private function assertQueryRejected(callable $callback): void
    {
        DB::beginTransaction();

        try {
            $callback();
            DB::rollBack();
            $this->fail('Expected QueryException was not thrown.');
        } catch (QueryException) {
            DB::rollBack();
            $this->assertTrue(true);
        }
    }
}
