<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ModifierGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'selection_type' => $this->faker->randomElement(['single', 'multi']),
            'required' => false,
            'display_order' => 0,
        ];
    }

    public function required(): static
    {
        return $this->state(['required' => true, 'selection_type' => 'single']);
    }
}
