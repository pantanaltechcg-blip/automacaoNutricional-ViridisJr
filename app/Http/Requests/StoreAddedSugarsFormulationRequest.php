<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\AddedSugarsFormulation;
use App\Models\AddedSugarsFormulationEvidence;
use App\Models\AddedSugarsFormulationIngredient;
use App\Models\Recipe;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAddedSugarsFormulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'normative_source' => ['required', 'string'],
            'normative_version' => ['required', 'string'],
            'registered_by' => ['required', 'string'],
            'registered_on' => ['required', 'date_format:Y-m-d'],
            'change_reason' => ['nullable', 'string'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.position' => ['required', 'integer', 'min:1', 'distinct'],
            'ingredients.*.food_id' => ['required', 'integer', 'exists:foods,id'],
            'ingredients.*.ingredient_type' => ['required', 'string', Rule::in(AddedSugarsFormulationIngredient::TYPES)],
            'ingredients.*.classification' => ['required', 'string', Rule::in(AddedSugarsFormulationIngredient::CLASSIFICATIONS)],
            'ingredients.*.amount_basis' => ['nullable', 'string', Rule::in(AddedSugarsFormulationIngredient::AMOUNT_BASES)],
            'ingredients.*.amount_value' => ['nullable', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
            'ingredients.*.amount_unit' => ['nullable', 'string', 'in:g'],
            'ingredients.*.exclusion_justification' => ['nullable', 'string'],
            'ingredients.*.conflict_notes' => ['nullable', 'string'],
            'ingredients.*.evidences' => ['present', 'array'],
            'ingredients.*.evidences.*.source_type' => ['required', 'string', Rule::in(AddedSugarsFormulationEvidence::SOURCE_TYPES)],
            'ingredients.*.evidences.*.source_name' => ['required', 'string'],
            'ingredients.*.evidences.*.document_reference' => ['required', 'string'],
            'ingredients.*.evidences.*.reported_amount_basis' => ['nullable', 'string', Rule::in(AddedSugarsFormulationIngredient::AMOUNT_BASES)],
            'ingredients.*.evidences.*.reported_amount_value' => ['nullable', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
            'ingredients.*.evidences.*.reported_amount_unit' => ['nullable', 'string', 'in:g'],
            'ingredients.*.evidences.*.only_total_sugars_reported' => ['required', 'boolean'],
            'ingredients.*.evidences.*.recorded_by' => ['required', 'string'],
            'ingredients.*.evidences.*.recorded_on' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Recipe|null $recipe */
            $recipe = $this->route('recipe');

            if ($recipe instanceof Recipe) {
                $exists = AddedSugarsFormulation::query()
                    ->where('recipe_id', $recipe->getKey())
                    ->exists();

                if ($exists && trim((string) $this->input('change_reason', '')) === '') {
                    $validator->errors()->add('change_reason', 'O campo change_reason é obrigatório ao criar uma nova versão da formulação.');
                }
            }

            $ingredients = $this->input('ingredients', []);

            if (! is_array($ingredients)) {
                return;
            }

            foreach ($ingredients as $index => $ingredient) {
                if (! is_array($ingredient)) {
                    continue;
                }

                $classification = $ingredient['classification'] ?? null;
                $basis = $ingredient['amount_basis'] ?? null;
                $value = $ingredient['amount_value'] ?? null;
                $unit = $ingredient['amount_unit'] ?? null;
                $evidences = is_array($ingredient['evidences'] ?? null) ? $ingredient['evidences'] : [];

                if ($classification === AddedSugarsFormulationIngredient::CLASSIFICATION_INCLUDED) {
                    if ($basis === null || $value === null || $unit !== 'g') {
                        $validator->errors()->add(
                            "ingredients.{$index}.amount_value",
                            'Ingredientes classificados como INCLUDED exigem amount_basis, amount_value e amount_unit = g.',
                        );
                    }

                    if ($basis === AddedSugarsFormulationIngredient::BASIS_PER_100G && is_string($value) && preg_match('/^\d+(?:\.\d{1,6})?$/', $value) === 1) {
                        if (BigDecimal::of($value)->isGreaterThan(BigDecimal::of('100'))) {
                            $validator->errors()->add(
                                "ingredients.{$index}.amount_value",
                                'O teor por 100 g não pode exceder 100 g.',
                            );
                        }
                    }

                    if ($evidences === []) {
                        $validator->errors()->add(
                            "ingredients.{$index}.evidences",
                            'Ingredientes INCLUDED exigem ao menos uma evidência documental.',
                        );
                    } elseif (collect($evidences)->every(fn ($ev): bool => is_array($ev) && (bool) ($ev['only_total_sugars_reported'] ?? false) === true)) {
                        $validator->errors()->add(
                            "ingredients.{$index}.evidences",
                            'Evidências que informam apenas açúcares totais não podem classificar o ingrediente como INCLUDED.',
                        );
                    }
                } else {
                    if ($basis !== null || $value !== null || $unit !== null) {
                        $validator->errors()->add(
                            "ingredients.{$index}.amount_value",
                            'Apenas ingredientes INCLUDED podem informar amount_basis, amount_value e amount_unit.',
                        );
                    }
                }

                if ($classification === AddedSugarsFormulationIngredient::CLASSIFICATION_EXCLUDED_NORMATIVE) {
                    if (trim((string) ($ingredient['exclusion_justification'] ?? '')) === '') {
                        $validator->errors()->add(
                            "ingredients.{$index}.exclusion_justification",
                            'Ingredientes EXCLUDED_NORMATIVE exigem exclusion_justification.',
                        );
                    }

                    if ($evidences === []) {
                        $validator->errors()->add(
                            "ingredients.{$index}.evidences",
                            'Ingredientes EXCLUDED_NORMATIVE exigem ao menos uma evidência normativa ou documental.',
                        );
                    }
                }

                if ($classification === AddedSugarsFormulationIngredient::CLASSIFICATION_CONFLICTING) {
                    if (trim((string) ($ingredient['conflict_notes'] ?? '')) === '') {
                        $validator->errors()->add(
                            "ingredients.{$index}.conflict_notes",
                            'Ingredientes CONFLICTING exigem conflict_notes.',
                        );
                    }

                    if ($evidences === []) {
                        $validator->errors()->add(
                            "ingredients.{$index}.evidences",
                            'Ingredientes CONFLICTING exigem ao menos uma evidência registrada.',
                        );
                    }
                }
            }
        });
    }
}
