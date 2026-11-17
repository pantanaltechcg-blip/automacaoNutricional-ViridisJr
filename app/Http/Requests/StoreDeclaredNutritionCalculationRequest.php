<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeclaredNutritionCalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'regulatory_declaration_profile_id' => [
                'required',
                'integer',
                'min:1',
                'exists:regulatory_declaration_profiles,id',
            ],
        ];
    }
}
