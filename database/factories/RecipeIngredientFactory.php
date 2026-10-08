<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeIngredient>
 */
class RecipeIngredientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'quantity' => fake()->randomElement(['0.125', '1', '12.5001']),
            'unit' => 'g',
            'position' => 1,
        ];
    }
}
