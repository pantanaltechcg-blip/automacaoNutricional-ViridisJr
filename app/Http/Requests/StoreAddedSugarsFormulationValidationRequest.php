<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\AddedSugarsFormulationValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAddedSugarsFormulationValidationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'decision_status' => ['required', 'string', Rule::in(AddedSugarsFormulationValidation::DECISION_STATUSES)],
            'normative_source' => ['required', 'string'],
            'normative_version' => ['required', 'string'],
            'evidence_type' => ['required', 'string', Rule::in(AddedSugarsFormulationValidation::EVIDENCE_TYPES)],
            'evidence_reference' => ['required', 'string'],
            'evidence_source' => ['required', 'string'],
            'evidence_version' => ['required', 'string'],
            'evidence_scope' => ['required', 'string'],
            'validated_by' => ['required', 'string'],
            'validated_on' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = $this->input('decision_status');

            if (
                in_array($status, [
                    AddedSugarsFormulationValidation::STATUS_REJECTED,
                    AddedSugarsFormulationValidation::STATUS_REVOKED,
                ], true)
                && trim((string) $this->input('notes', '')) === ''
            ) {
                $validator->errors()->add('notes', 'O campo notes é obrigatório para decisões REJECTED ou REVOKED.');
            }
        });
    }
}
