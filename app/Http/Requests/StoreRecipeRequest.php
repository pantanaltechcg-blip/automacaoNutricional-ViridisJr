<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecipeRequest extends FormRequest
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
            'name' => ['required', 'string'],
            'yield_value' => ['required', 'string', 'regex:/^(?=.*[1-9])[0-9]+(?:\.[0-9]+)?$/'],
            'yield_unit' => ['required', 'string', Rule::in(['g', 'ml'])],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.food_id' => ['required', 'integer', Rule::exists('foods', 'id')],
            'ingredients.*.quantity' => ['required', 'string', 'regex:/^(?=.*[1-9])[0-9]+(?:\.[0-9]+)?$/'],
            'ingredients.*.unit' => ['required', 'string', Rule::in(['g'])],
            'ingredients.*.position' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }
}
