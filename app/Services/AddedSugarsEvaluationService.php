<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AddedSugarsEvaluation;
use App\Models\AddedSugarsEvaluationCause;
use App\Models\AddedSugarsEvaluationItem;
use App\Models\AddedSugarsFormulationIngredient;
use App\Models\AddedSugarsFormulationVersion;
use App\Models\NutritionCalculation;
use Brick\Math\BigRational;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddedSugarsEvaluationService
{
    public function __construct(
        private NutritionCalculationArithmetic $arithmetic,
        private AddedSugarsRegulatoryGateService $gates,
    ) {}

    public function create(
        NutritionCalculation $calculation,
        int $formulationVersionId,
    ): AddedSugarsEvaluation {
        return DB::transaction(function () use ($calculation, $formulationVersionId): AddedSugarsEvaluation {
            $this->useRepeatableReadIsolation();

            $source = NutritionCalculation::query()
                ->with(['ingredients' => fn ($q) => $q->orderBy('position')])
                ->findOrFail($calculation->getKey());

            $version = AddedSugarsFormulationVersion::query()
                ->with([
                    'ingredients' => fn ($q) => $q->orderBy('position'),
                    'ingredients.evidences' => fn ($q) => $q->orderBy('id'),
                    'validations' => fn ($q) => $q->orderBy('id'),
                ])
                ->findOrFail($formulationVersionId);

            if ((int) $version->recipe_id !== (int) $source->recipe_id) {
                throw ValidationException::withMessages([
                    'formulation_version_id' => 'A versão da formulação informada não pertence à mesma receita da execução nutricional.',
                ]);
            }

            $validationEval = $this->gates->evaluateVersionValidation($version);
            $formulationIngredientsByPosition = $version->ingredients->keyBy('position');

            $itemsData = [];
            $itemCausesByIndex = [];
            $allItemsComplete = $source->ingredients->isNotEmpty();
            $runningTotal = BigRational::zero();

            foreach ($source->ingredients as $idx => $calcIngredient) {
                /** @var ?AddedSugarsFormulationIngredient $formIngredient */
                $formIngredient = $formulationIngredientsByPosition->get((int) $calcIngredient->position);
                $quantity = (string) $calcIngredient->getRawOriginal('quantity');
                $itemCauses = [];

                if (
                    ! $formIngredient instanceof AddedSugarsFormulationIngredient
                    || (int) $formIngredient->food_id !== (int) $calcIngredient->food_id
                ) {
                    $allItemsComplete = false;
                    $itemCauses[] = [
                        'cause_type' => 'INGREDIENT_MISMATCH',
                        'cause_code' => $formIngredient instanceof AddedSugarsFormulationIngredient
                            ? 'INGREDIENT_FOOD_MISMATCH'
                            : 'INGREDIENT_NOT_COVERED',
                        'detail' => "Ingrediente na posição {$calcIngredient->position} não está coberto de forma compatível na versão v{$version->version_number}.",
                    ];

                    $itemsData[] = [
                        'nutrition_calculation_ingredient_id' => $calcIngredient->getKey(),
                        'formulation_ingredient_id' => null,
                        'position' => (int) $calcIngredient->position,
                        'food_id' => (int) $calcIngredient->food_id,
                        'food_source' => $calcIngredient->food_source,
                        'ingredient_quantity' => $quantity,
                        'ingredient_unit' => $calcIngredient->unit,
                        'ingredient_type' => null,
                        'classification' => 'UNCOVERED',
                        'amount_basis' => null,
                        'amount_value' => null,
                        'contribution_numerator' => null,
                        'contribution_denominator' => null,
                        'is_excluded_normative' => false,
                        'is_complete' => false,
                        'evidence_count' => 0,
                    ];
                    $itemCausesByIndex[$idx] = $itemCauses;

                    continue;
                }

                $evidenceCount = $formIngredient->evidences->count();
                $onlyTotalSugars = $evidenceCount > 0
                    && $formIngredient->evidences->every(fn ($ev): bool => (bool) $ev->only_total_sugars_reported);
                $contribution = null;
                $itemComplete = false;
                $isExcluded = false;

                if ($formIngredient->classification === AddedSugarsFormulationIngredient::CLASSIFICATION_EXCLUDED_NORMATIVE) {
                    $contribution = BigRational::zero();
                    $itemComplete = true;
                    $isExcluded = true;
                } elseif ($formIngredient->classification === AddedSugarsFormulationIngredient::CLASSIFICATION_INCLUDED) {
                    $amountValue = (string) $formIngredient->getRawOriginal('amount_value');

                    if ($formIngredient->amount_basis === AddedSugarsFormulationIngredient::BASIS_PER_100G) {
                        $contribution = $this->arithmetic->multiplyAndDivide($quantity, $amountValue, '100');
                        $itemComplete = true;
                    } elseif ($formIngredient->amount_basis === AddedSugarsFormulationIngredient::BASIS_DIRECT_BATCH_GRAMS) {
                        $direct = $this->arithmetic->fromDecimal($amountValue);
                        $maxQuantity = $this->arithmetic->fromDecimal($quantity);

                        if ($direct->isGreaterThan($maxQuantity)) {
                            $allItemsComplete = false;
                            $itemCauses[] = [
                                'cause_type' => 'PHYSICAL_INCONSISTENCY',
                                'cause_code' => 'DIRECT_AMOUNT_EXCEEDS_INGREDIENT_QUANTITY',
                                'detail' => "Quantidade direta de açúcares adicionados ({$amountValue} g) excede a massa do ingrediente ({$quantity} g).",
                            ];
                        } else {
                            $contribution = $direct;
                            $itemComplete = true;
                        }
                    }
                } elseif ($formIngredient->classification === AddedSugarsFormulationIngredient::CLASSIFICATION_MISSING) {
                    $allItemsComplete = false;
                    $itemCauses[] = [
                        'cause_type' => 'MISSING_DATA',
                        'cause_code' => $onlyTotalSugars ? 'ONLY_TOTAL_SUGARS_REPORTED' : 'INGREDIENT_ADDED_SUGARS_MISSING',
                        'detail' => $onlyTotalSugars
                            ? 'A evidência do ingrediente informa apenas açúcares totais, sem discriminar açúcares adicionados.'
                            : 'Dado de açúcares adicionados ausente para o ingrediente.',
                    ];
                } elseif ($formIngredient->classification === AddedSugarsFormulationIngredient::CLASSIFICATION_CONFLICTING) {
                    $allItemsComplete = false;
                    $itemCauses[] = [
                        'cause_type' => 'CONFLICTING_EVIDENCE',
                        'cause_code' => 'CONFLICTING_EVIDENCES',
                        'detail' => $formIngredient->conflict_notes,
                    ];
                }

                if ($itemComplete && $contribution instanceof BigRational) {
                    $runningTotal = $runningTotal->plus($contribution);
                } else {
                    $allItemsComplete = false;
                }

                $ratio = $contribution instanceof BigRational ? $this->arithmetic->ratio($contribution) : null;

                $itemsData[] = [
                    'nutrition_calculation_ingredient_id' => $calcIngredient->getKey(),
                    'formulation_ingredient_id' => $formIngredient->getKey(),
                    'position' => (int) $calcIngredient->position,
                    'food_id' => (int) $calcIngredient->food_id,
                    'food_source' => $calcIngredient->food_source,
                    'ingredient_quantity' => $quantity,
                    'ingredient_unit' => $calcIngredient->unit,
                    'ingredient_type' => $formIngredient->ingredient_type,
                    'classification' => $formIngredient->classification,
                    'amount_basis' => $formIngredient->amount_basis,
                    'amount_value' => $formIngredient->amount_value !== null
                        ? (string) $formIngredient->getRawOriginal('amount_value')
                        : null,
                    'contribution_numerator' => $ratio['numerator'] ?? null,
                    'contribution_denominator' => $ratio['denominator'] ?? null,
                    'is_excluded_normative' => $isExcluded,
                    'is_complete' => $itemComplete,
                    'evidence_count' => $evidenceCount,
                ];
                $itemCausesByIndex[$idx] = $itemCauses;
            }

            $isRegulatoryEligible = $allItemsComplete && $validationEval['authorized'];
            $totalRatio = null;
            $referenceRatio = null;
            $isConfirmedZero = false;

            if ($isRegulatoryEligible) {
                $yieldValue = (string) $source->getRawOriginal('yield_value');
                $totalRatio = $this->arithmetic->ratio($runningTotal);
                $referenceRational = $this->arithmetic->normalizeRatio(
                    $totalRatio['numerator'],
                    $totalRatio['denominator'],
                    $yieldValue,
                );
                $referenceRatio = $this->arithmetic->ratio($referenceRational);
                $isConfirmedZero = $totalRatio['numerator'] === '0';
            }

            $validation = $validationEval['validation'];

            $evaluation = AddedSugarsEvaluation::query()->create([
                'nutrition_calculation_id' => $source->getKey(),
                'formulation_id' => $version->formulation_id,
                'formulation_version_id' => $version->getKey(),
                'validation_id' => $validation?->getKey(),
                'source_recipe_id' => $source->recipe_id,
                'source_rules_version' => $source->rules_version,
                'source_yield_value' => (string) $source->getRawOriginal('yield_value'),
                'source_yield_unit' => $source->yield_unit,
                'formulation_version_number' => $version->version_number,
                'normative_source' => $version->normative_source,
                'normative_version' => $version->normative_version,
                'validation_status' => $validationEval['status'],
                'validation_evidence_type' => $validation?->evidence_type,
                'validation_evidence_reference' => $validation?->evidence_reference,
                'validation_evidence_source' => $validation?->evidence_source,
                'validation_evidence_version' => $validation?->evidence_version,
                'validation_evidence_scope' => $validation?->evidence_scope,
                'validated_by' => $validation?->validated_by,
                'validated_on' => $validation?->validated_on?->format('Y-m-d'),
                'total_numerator' => $totalRatio['numerator'] ?? null,
                'total_denominator' => $totalRatio['denominator'] ?? null,
                'total_unit' => $totalRatio !== null ? 'g' : null,
                'reference_basis' => '100',
                'reference_unit' => $source->yield_unit,
                'reference_numerator' => $referenceRatio['numerator'] ?? null,
                'reference_denominator' => $referenceRatio['denominator'] ?? null,
                'is_complete' => $allItemsComplete,
                'is_regulatory_eligible' => $isRegulatoryEligible,
                'is_confirmed_zero' => $isConfirmedZero,
                'rules_version' => AddedSugarsEvaluation::RULES_VERSION,
            ]);

            foreach ($validationEval['causes'] as $cause) {
                AddedSugarsEvaluationCause::query()->create([
                    'evaluation_id' => $evaluation->getKey(),
                    'evaluation_item_id' => null,
                    'cause_type' => $cause['cause_type'],
                    'cause_code' => $cause['cause_code'],
                    'detail' => $cause['detail'],
                ]);
            }

            foreach ($itemsData as $idx => $itemRow) {
                $item = AddedSugarsEvaluationItem::query()->create([
                    ...$itemRow,
                    'evaluation_id' => $evaluation->getKey(),
                ]);

                foreach ($itemCausesByIndex[$idx] ?? [] as $cause) {
                    AddedSugarsEvaluationCause::query()->create([
                        'evaluation_id' => $evaluation->getKey(),
                        'evaluation_item_id' => $item->getKey(),
                        'cause_type' => $cause['cause_type'],
                        'cause_code' => $cause['cause_code'],
                        'detail' => $cause['detail'],
                    ]);
                }
            }

            return $this->retrieve($evaluation);
        });
    }

    public function retrieve(AddedSugarsEvaluation $evaluation): AddedSugarsEvaluation
    {
        return AddedSugarsEvaluation::query()
            ->with([
                'items' => fn ($q) => $q->orderBy('position'),
                'causes' => fn ($q) => $q->orderBy('id'),
            ])
            ->findOrFail($evaluation->getKey());
    }

    private function useRepeatableReadIsolation(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql' && DB::transactionLevel() === 1) {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }
    }
}
