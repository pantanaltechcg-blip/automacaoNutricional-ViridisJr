<?php

namespace App\Http\Requests;

use App\Models\ManualFoodRegistration;
use Closure;
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
            'ingredients.*.food_id' => ['required', 'integer', Rule::exists('foods', 'id'), $this->inactiveManualFoodRule()],
            'ingredients.*.quantity' => ['required', 'string', 'regex:/^(?=.*[1-9])[0-9]+(?:\.[0-9]+)?$/'],
            'ingredients.*.unit' => ['required', 'string', Rule::in(['g'])],
            'ingredients.*.position' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    protected function inactiveManualFoodRule(): Closure
    {
        $allowedInactiveFoodIds = $this->allowedInactiveFoodIds();

        return static function (string $attribute, mixed $value, Closure $fail) use ($allowedInactiveFoodIds): void {
            if (is_bool($value)) {
                return;
            }

            $foodId = filter_var($value, FILTER_VALIDATE_INT);

            if ($foodId === false || in_array($foodId, $allowedInactiveFoodIds, true)) {
                return;
            }

            $isInactiveManualFood = ManualFoodRegistration::query()
                ->where('food_id', $foodId)
                ->where('is_active', false)
                ->exists();

            if ($isInactiveManualFood) {
                $fail('O alimento selecionado está inativo e não pode ser utilizado nesta receita.');
            }
        };
    }

    /**
     * @return list<int>
     */
    protected function allowedInactiveFoodIds(): array
    {
        return [];
    }
}
