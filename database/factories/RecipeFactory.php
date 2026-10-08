<?php

namespace Database\Factories;

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'yield_value' => fake()->randomElement(['1', '10.25', '100.0001']),
            'yield_unit' => fake()->randomElement(['g', 'ml']),
        ];
    }
}
