<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreServingDailyValueCalculationRequest extends FormRequest
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
            'portion' => ['required', 'array:value,unit,household_measure'],
            'portion.value' => ['required', 'string', 'regex:/^(?=.*[1-9])\d+(?:\.\d+)?$/'],
            'portion.unit' => ['required', 'string', 'in:g,ml'],
            'portion.household_measure' => ['nullable', 'string'],
            'portion_authorization_id' => ['required', 'integer', 'exists:portion_authorizations,id'],
            'regulatory_profile_id' => ['required', 'integer', 'exists:regulatory_profiles,id'],
        ];
    }
}
