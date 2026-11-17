<?php

namespace App\Http\Requests;

use App\Models\FoodNutrient;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualFoodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', $this->nonBlankStringRule()],
            'category' => ['nullable', 'string'],
            'source' => ['required', 'string', $this->nonBlankStringRule(), $this->nonTacoSourceRule()],
            'source_code' => ['required', 'string', $this->nonBlankStringRule()],
            'reference_unit' => ['required', 'string', Rule::in(['g'])],
            'evidence_reference' => ['required', 'string', $this->nonBlankStringRule()],
            'evidence_source' => ['required', 'string', $this->nonBlankStringRule()],
            'registered_by' => ['required', 'string', $this->nonBlankStringRule()],
            'registered_on' => ['required', 'string', 'date_format:Y-m-d'],
            'nutrients' => ['required', 'array', 'min:1'],
            'nutrients.*' => ['required', 'array'],
            'nutrients.*.nutrient_id' => ['required', 'integer', 'distinct', Rule::exists('nutrients', 'id')],
            'nutrients.*.status' => ['required', 'string', Rule::in($this->allowedStatuses())],
            'nutrients.*.amount_value' => Rule::forEach(function (mixed $value, string $attribute, array $data, mixed $context): array {
                $status = is_array($context) ? ($context['status'] ?? null) : null;

                if ($status === FoodNutrient::STATUS_EXACT) {
                    return [
                        'required',
                        'string',
                        'regex:/^\d{1,5}(?:\.\d{1,2})?$/D',
                    ];
                }

                return [
                    'nullable',
                    static function (string $nestedAttribute, mixed $nestedValue, Closure $fail): void {
                        if ($nestedValue !== null) {
                            $fail('O campo amount_value deve ser nulo quando o status nutricional não for EXACT.');
                        }
                    },
                ];
            }),
        ];
    }

    protected function nonBlankStringRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && trim($value) === '') {
                $fail("O campo {$attribute} não pode ser vazio.");
            }
        };
    }

    protected function nonTacoSourceRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && strtoupper(trim($value)) === 'TACO') {
                $fail('A origem TACO é reservada à base oficial e não pode ser utilizada no cadastro manual.');
            }
        };
    }

    /**
     * @return list<string>
     */
    protected function allowedStatuses(): array
    {
        return [
            FoodNutrient::STATUS_EXACT,
            FoodNutrient::STATUS_TRACE,
            FoodNutrient::STATUS_NA,
            FoodNutrient::STATUS_ABSENT,
            FoodNutrient::STATUS_REEVALUATION,
        ];
    }
}
