<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Food;
use App\Models\NutritionCalculation;
use App\Models\NutritionCalculationIngredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AddedSugarsFormulationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registers_simple_and_compound_ingredients_keeps_pending_evaluation_null_and_calculates_exact_ratios_when_authorized(): void
    {
        [$calc, $fruit, $syrup, $recipe] = $this->seedTwoIngredientCalculation('500', 'g', '300', '200');

        $store = $this->postJson("/api/recipes/{$recipe->getKey()}/added-sugars-formulations", [
            'normative_source' => 'RDC nº 429/2020 e IN nº 75/2020',
            'normative_version' => '2020-ret-2022',
            'registered_by' => 'Nutricionista Viridis',
            'registered_on' => '2026-11-23',
            'ingredients' => [
                [
                    'position' => 1,
                    'food_id' => $fruit->getKey(),
                    'ingredient_type' => 'SIMPLE',
                    'classification' => 'EXCLUDED_NORMATIVE',
                    'exclusion_justification' => 'Fruta in natura íntegra excluída pela RDC nº 429/2020, art. 3º, I.',
                    'evidences' => [[
                        'source_type' => 'NORMATIVE_REFERENCE',
                        'source_name' => 'RDC nº 429/2020',
                        'document_reference' => 'Art. 3º, I',
                        'only_total_sugars_reported' => false,
                        'recorded_by' => 'Nutricionista Viridis',
                        'recorded_on' => '2026-11-23',
                    ]],
                ],
                [
                    'position' => 2,
                    'food_id' => $syrup->getKey(),
                    'ingredient_type' => 'COMPOUND_INDUSTRIALIZED',
                    'classification' => 'INCLUDED',
                    'amount_basis' => 'PER_100G',
                    'amount_value' => '45.5',
                    'amount_unit' => 'g',
                    'evidences' => [[
                        'source_type' => 'MANUFACTURER_SPEC',
                        'source_name' => 'Fabricante Exemplo',
                        'document_reference' => 'FT-2026-01',
                        'reported_amount_basis' => 'PER_100G',
                        'reported_amount_value' => '45.5',
                        'reported_amount_unit' => 'g',
                        'only_total_sugars_reported' => false,
                        'recorded_by' => 'Nutricionista Viridis',
                        'recorded_on' => '2026-11-23',
                    ]],
                ],
            ],
        ]);

        $store->assertCreated()
            ->assertJsonPath('data.current_version_number', 1)
            ->assertJsonPath('data.current_version.validation_status', 'PENDING');

        $versionId = $store->json('data.current_version.id');

        // Evaluate BEFORE validation -> deny-by-default keeps total & reference null
        $pendingEval = $this->postJson("/api/nutrition-calculations/{$calc->getKey()}/added-sugars-evaluations", [
            'formulation_version_id' => $versionId,
        ]);

        $pendingEval->assertCreated()
            ->assertJsonPath('data.validation.status', 'PENDING')
            ->assertJsonPath('data.is_complete', true)
            ->assertJsonPath('data.is_regulatory_eligible', false)
            ->assertJsonPath('data.is_confirmed_zero', false)
            ->assertJsonPath('data.total.value', null)
            ->assertJsonPath('data.reference.value', null)
            ->assertJsonPath('data.items.0.contribution.numerator', '0')
            ->assertJsonPath('data.items.0.is_excluded_normative', true)
            ->assertJsonPath('data.items.1.contribution.numerator', '91')
            ->assertJsonPath('data.items.1.contribution.denominator', '1')
            ->assertJsonPath('data.causes.0.cause_code', 'FORMULATION_VALIDATION_PENDING');

        // Record technical validation AUTHORIZED
        $this->postJson("/api/added-sugars-formulation-versions/{$versionId}/validations", [
            'decision_status' => 'AUTHORIZED',
            'normative_source' => 'RDC nº 429/2020',
            'normative_version' => '2020-ret-2022',
            'evidence_type' => 'TECHNICAL_REVIEW',
            'evidence_reference' => 'PARECER-AA-01',
            'evidence_source' => 'RT Viridis',
            'evidence_version' => '1.0',
            'evidence_scope' => "recipe:{$recipe->getKey()}/added-sugars-formulation:v1",
            'validated_by' => 'CRN-1234',
            'validated_on' => '2026-11-23',
        ])->assertCreated();

        // Evaluate AFTER authorization -> total = 91 g, reference per 100g = 91 * 100 / 500 = 91/5 g
        $authorizedEval = $this->postJson("/api/nutrition-calculations/{$calc->getKey()}/added-sugars-evaluations", [
            'formulation_version_id' => $versionId,
        ]);

        $authorizedEval->assertCreated()
            ->assertJsonPath('data.validation.status', 'AUTHORIZED')
            ->assertJsonPath('data.is_complete', true)
            ->assertJsonPath('data.is_regulatory_eligible', true)
            ->assertJsonPath('data.is_confirmed_zero', false)
            ->assertJsonPath('data.total.value.numerator', '91')
            ->assertJsonPath('data.total.value.denominator', '1')
            ->assertJsonPath('data.total.unit', 'g')
            ->assertJsonPath('data.reference.basis', '100')
            ->assertJsonPath('data.reference.unit', 'g')
            ->assertJsonPath('data.reference.value.numerator', '91')
            ->assertJsonPath('data.reference.value.denominator', '5');

        // Historical pending evaluation remains unchanged
        $this->getJson('/api/added-sugars-evaluations/'.$pendingEval->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.is_regulatory_eligible', false)
            ->assertJsonPath('data.total.value', null);
    }

    public function test_distinguishes_confirmed_zero_from_missing_only_total_sugars_and_conflicting_evidences(): void
    {
        [$calc, $fruit, $syrup, $recipe] = $this->seedTwoIngredientCalculation('400', 'g', '250', '150');

        // 1. Confirmed zero when all ingredients are EXCLUDED_NORMATIVE or 0 and authorized
        $v1 = $this->postJson("/api/recipes/{$recipe->getKey()}/added-sugars-formulations", [
            'normative_source' => 'RDC nº 429/2020',
            'normative_version' => '2020-ret-2022',
            'registered_by' => 'Nutricionista',
            'registered_on' => '2026-11-23',
            'ingredients' => [
                [
                    'position' => 1,
                    'food_id' => $fruit->getKey(),
                    'ingredient_type' => 'SIMPLE',
                    'classification' => 'EXCLUDED_NORMATIVE',
                    'exclusion_justification' => 'Fruta íntegra',
                    'evidences' => [[
                        'source_type' => 'NORMATIVE_REFERENCE',
                        'source_name' => 'RDC nº 429/2020',
                        'document_reference' => 'Art. 3º, I',
                        'only_total_sugars_reported' => false,
                        'recorded_by' => 'Nutricionista',
                        'recorded_on' => '2026-11-23',
                    ]],
                ],
                [
                    'position' => 2,
                    'food_id' => $syrup->getKey(),
                    'ingredient_type' => 'COMPOUND_INDUSTRIALIZED',
                    'classification' => 'INCLUDED',
                    'amount_basis' => 'DIRECT_BATCH_GRAMS',
                    'amount_value' => '0',
                    'amount_unit' => 'g',
                    'evidences' => [[
                        'source_type' => 'MANUFACTURER_SPEC',
                        'source_name' => 'Fabricante Zero',
                        'document_reference' => 'FT-ZERO',
                        'reported_amount_basis' => 'DIRECT_BATCH_GRAMS',
                        'reported_amount_value' => '0',
                        'reported_amount_unit' => 'g',
                        'only_total_sugars_reported' => false,
                        'recorded_by' => 'Nutricionista',
                        'recorded_on' => '2026-11-23',
                    ]],
                ],
            ],
        ])->assertCreated();

        $v1Id = $v1->json('data.current_version.id');
        $this->postJson("/api/added-sugars-formulation-versions/{$v1Id}/validations", [
            'decision_status' => 'AUTHORIZED',
            'normative_source' => 'RDC nº 429/2020',
            'normative_version' => '2020-ret-2022',
            'evidence_type' => 'TEST_FIXTURE',
            'evidence_reference' => 'FIXTURE-ZERO',
            'evidence_source' => 'Suite',
            'evidence_version' => '1.0',
            'evidence_scope' => "recipe:{$recipe->getKey()}/added-sugars-formulation:v1",
            'validated_by' => 'Tester',
            'validated_on' => '2026-11-23',
        ])->assertCreated();

        $this->postJson("/api/nutrition-calculations/{$calc->getKey()}/added-sugars-evaluations", [
            'formulation_version_id' => $v1Id,
        ])->assertCreated()
            ->assertJsonPath('data.is_regulatory_eligible', true)
            ->assertJsonPath('data.is_confirmed_zero', true)
            ->assertJsonPath('data.total.value.numerator', '0')
            ->assertJsonPath('data.reference.value.numerator', '0');

        // 2. Missing (only_total_sugars_reported) + Conflicting -> total & reference strictly null even if authorized
        $v2 = $this->postJson("/api/recipes/{$recipe->getKey()}/added-sugars-formulations", [
            'normative_source' => 'RDC nº 429/2020',
            'normative_version' => '2020-ret-2022',
            'registered_by' => 'Nutricionista',
            'registered_on' => '2026-11-23',
            'change_reason' => 'Troca de fornecedor com dados incompletos e conflitantes',
            'ingredients' => [
                [
                    'position' => 1,
                    'food_id' => $fruit->getKey(),
                    'ingredient_type' => 'COMPOUND_INDUSTRIALIZED',
                    'classification' => 'MISSING',
                    'evidences' => [[
                        'source_type' => 'MANUFACTURER_SPEC',
                        'source_name' => 'Fabricante Incompleto',
                        'document_reference' => 'FT-INC',
                        'only_total_sugars_reported' => true,
                        'recorded_by' => 'Nutricionista',
                        'recorded_on' => '2026-11-23',
                    ]],
                ],
                [
                    'position' => 2,
                    'food_id' => $syrup->getKey(),
                    'ingredient_type' => 'COMPOUND_INDUSTRIALIZED',
                    'classification' => 'CONFLICTING',
                    'conflict_notes' => 'Formulário indica 10g e ficha técnica indica 25g',
                    'evidences' => [[
                        'source_type' => 'OPERATIONAL_FORM',
                        'source_name' => 'Google Forms Viridis',
                        'document_reference' => 'FORM-2026',
                        'only_total_sugars_reported' => false,
                        'recorded_by' => 'Cliente',
                        'recorded_on' => '2026-11-23',
                    ]],
                ],
            ],
        ])->assertCreated();

        $v2Id = $v2->json('data.current_version.id');
        $eval2 = $this->postJson("/api/nutrition-calculations/{$calc->getKey()}/added-sugars-evaluations", [
            'formulation_version_id' => $v2Id,
        ])->assertCreated();

        $eval2->assertJsonPath('data.is_complete', false)
            ->assertJsonPath('data.is_regulatory_eligible', false)
            ->assertJsonPath('data.is_confirmed_zero', false)
            ->assertJsonPath('data.total.value', null)
            ->assertJsonPath('data.reference.value', null);

        $causeCodes = collect($eval2->json('data.causes'))->pluck('cause_code')->all();
        $this->assertContains('ONLY_TOTAL_SUGARS_REPORTED', $causeCodes);
        $this->assertContains('CONFLICTING_EVIDENCES', $causeCodes);
    }

    public function test_enforces_validation_gates_for_scope_mismatch_rejected_revoked_and_test_fixture_outside_testing(): void
    {
        [$calc, $fruit, $syrup, $recipe] = $this->seedTwoIngredientCalculation('500', 'g', '300', '200');

        $v1 = $this->postJson("/api/recipes/{$recipe->getKey()}/added-sugars-formulations", [
            'normative_source' => 'RDC nº 429/2020',
            'normative_version' => '2020-ret-2022',
            'registered_by' => 'Nutricionista',
            'registered_on' => '2026-11-23',
            'ingredients' => [
                [
                    'position' => 1,
                    'food_id' => $fruit->getKey(),
                    'ingredient_type' => 'SIMPLE',
                    'classification' => 'EXCLUDED_NORMATIVE',
                    'exclusion_justification' => 'Fruta íntegra',
                    'evidences' => [[
                        'source_type' => 'NORMATIVE_REFERENCE',
                        'source_name' => 'RDC nº 429/2020',
                        'document_reference' => 'Art. 3º, I',
                        'only_total_sugars_reported' => false,
                        'recorded_by' => 'Nutricionista',
                        'recorded_on' => '2026-11-23',
                    ]],
                ],
                [
                    'position' => 2,
                    'food_id' => $syrup->getKey(),
                    'ingredient_type' => 'SIMPLE',
                    'classification' => 'INCLUDED',
                    'amount_basis' => 'DIRECT_BATCH_GRAMS',
                    'amount_value' => '50',
                    'amount_unit' => 'g',
                    'evidences' => [[
                        'source_type' => 'OPERATIONAL_FORM',
                        'source_name' => 'Google Forms',
                        'document_reference' => 'FORM-1',
                        'only_total_sugars_reported' => false,
                        'recorded_by' => 'Cliente',
                        'recorded_on' => '2026-11-23',
                    ]],
                ],
            ],
        ])->assertCreated();

        $v1Id = $v1->json('data.current_version.id');

        // Wrong scope -> blocked
        $this->postJson("/api/added-sugars-formulation-versions/{$v1Id}/validations", [
            'decision_status' => 'AUTHORIZED',
            'normative_source' => 'RDC nº 429/2020',
            'normative_version' => '2020-ret-2022',
            'evidence_type' => 'TECHNICAL_REVIEW',
            'evidence_reference' => 'P-1',
            'evidence_source' => 'RT',
            'evidence_version' => '1.0',
            'evidence_scope' => 'recipe:999999/added-sugars-formulation:v1',
            'validated_by' => 'CRN',
            'validated_on' => '2026-11-23',
        ])->assertCreated();

        $scopeMismatchEval = $this->postJson("/api/nutrition-calculations/{$calc->getKey()}/added-sugars-evaluations", [
            'formulation_version_id' => $v1Id,
        ])->assertCreated();
        $scopeMismatchEval->assertJsonPath('data.is_regulatory_eligible', false)
            ->assertJsonPath('data.causes.0.cause_code', 'FORMULATION_VALIDATION_SCOPE_MISMATCH');

        // TEST_FIXTURE outside testing env -> blocked
        $this->postJson("/api/added-sugars-formulation-versions/{$v1Id}/validations", [
            'decision_status' => 'AUTHORIZED',
            'normative_source' => 'RDC nº 429/2020',
            'normative_version' => '2020-ret-2022',
            'evidence_type' => 'TEST_FIXTURE',
            'evidence_reference' => 'FIX-1',
            'evidence_source' => 'Test',
            'evidence_version' => '1.0',
            'evidence_scope' => "recipe:{$recipe->getKey()}/added-sugars-formulation:v1",
            'validated_by' => 'Tester',
            'validated_on' => '2026-11-23',
        ])->assertCreated();

        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';
        try {
            $prodEval = $this->postJson("/api/nutrition-calculations/{$calc->getKey()}/added-sugars-evaluations", [
                'formulation_version_id' => $v1Id,
            ])->assertCreated();
            $prodEval->assertJsonPath('data.is_regulatory_eligible', false)
                ->assertJsonPath('data.causes.0.cause_code', 'TEST_FIXTURE_NOT_ALLOWED');
        } finally {
            $this->app['env'] = $originalEnv;
        }

        // REVOKED requires notes -> 422 without notes, 201 with notes and blocks evaluation
        $this->postJson("/api/added-sugars-formulation-versions/{$v1Id}/validations", [
            'decision_status' => 'REVOKED',
            'normative_source' => 'RDC nº 429/2020',
            'normative_version' => '2020-ret-2022',
            'evidence_type' => 'TECHNICAL_REVIEW',
            'evidence_reference' => 'REV-1',
            'evidence_source' => 'RT',
            'evidence_version' => '1.0',
            'evidence_scope' => "recipe:{$recipe->getKey()}/added-sugars-formulation:v1",
            'validated_by' => 'CRN',
            'validated_on' => '2026-11-23',
        ])->assertUnprocessable();

        $this->postJson("/api/added-sugars-formulation-versions/{$v1Id}/validations", [
            'decision_status' => 'REVOKED',
            'normative_source' => 'RDC nº 429/2020',
            'normative_version' => '2020-ret-2022',
            'evidence_type' => 'TECHNICAL_REVIEW',
            'evidence_reference' => 'REV-1',
            'evidence_source' => 'RT',
            'evidence_version' => '1.0',
            'evidence_scope' => "recipe:{$recipe->getKey()}/added-sugars-formulation:v1",
            'validated_by' => 'CRN',
            'validated_on' => '2026-11-23',
            'notes' => 'Ficha técnica desatualizada.',
        ])->assertCreated();

        $revokedEval = $this->postJson("/api/nutrition-calculations/{$calc->getKey()}/added-sugars-evaluations", [
            'formulation_version_id' => $v1Id,
        ])->assertCreated();
        $revokedEval->assertJsonPath('data.validation.status', 'REVOKED')
            ->assertJsonPath('data.is_regulatory_eligible', false)
            ->assertJsonPath('data.causes.0.cause_code', 'FORMULATION_VALIDATION_REVOKED');
    }

    /** @return array{0: NutritionCalculation, 1: Food, 2: Food, 3: Recipe} */
    private function seedTwoIngredientCalculation(
        string $yieldValue,
        string $yieldUnit,
        string $qty1,
        string $qty2,
    ): array {
        $food1 = Food::query()->create([
            'name' => 'Amora in natura '.uniqid(),
            'category' => 'Frutas',
            'source' => 'TACO',
            'source_code' => 'TACO-F1-'.uniqid(),
        ]);
        $food2 = Food::query()->create([
            'name' => 'Xarope composto '.uniqid(),
            'category' => 'Açúcares',
            'source' => 'MANUAL',
            'source_code' => 'MAN-F2-'.uniqid(),
        ]);

        $recipe = Recipe::query()->create([
            'name' => 'Geleia teste '.uniqid(),
            'yield_value' => $yieldValue,
            'yield_unit' => $yieldUnit,
        ]);

        $ri1 = RecipeIngredient::query()->create([
            'recipe_id' => $recipe->getKey(),
            'food_id' => $food1->getKey(),
            'quantity' => $qty1,
            'unit' => 'g',
            'position' => 1,
        ]);
        $ri2 = RecipeIngredient::query()->create([
            'recipe_id' => $recipe->getKey(),
            'food_id' => $food2->getKey(),
            'quantity' => $qty2,
            'unit' => 'g',
            'position' => 2,
        ]);

        $calc = NutritionCalculation::query()->create([
            'recipe_id' => $recipe->getKey(),
            'rules_version' => 'nutrition-calculation-v1',
            'yield_value' => $yieldValue,
            'yield_unit' => $yieldUnit,
        ]);

        NutritionCalculationIngredient::query()->create([
            'nutrition_calculation_id' => $calc->getKey(),
            'recipe_ingredient_id' => $ri1->getKey(),
            'food_id' => $food1->getKey(),
            'food_source' => $food1->source,
            'food_source_code' => $food1->source_code,
            'quantity' => $qty1,
            'unit' => 'g',
            'position' => 1,
        ]);
        NutritionCalculationIngredient::query()->create([
            'nutrition_calculation_id' => $calc->getKey(),
            'recipe_ingredient_id' => $ri2->getKey(),
            'food_id' => $food2->getKey(),
            'food_source' => $food2->source,
            'food_source_code' => $food2->source_code,
            'quantity' => $qty2,
            'unit' => 'g',
            'position' => 2,
        ]);

        return [$calc, $food1, $food2, $recipe];
    }
}
