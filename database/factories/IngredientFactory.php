<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class IngredientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'             => $this->faker->unique()->words(2, true),
            'unit'             => $this->faker->randomElement(['unit', 'kg', 'g', 'L', 'mL', 'portion']),
            'quantity_on_hand' => $this->faker->randomFloat(4, 5, 100),
            'minimum_stock'    => 2,
            'cost_price'       => $this->faker->randomFloat(4, 0, 50),
            'active'           => true,
        ];
    }

    public function lowStock(): static
    {
        return $this->state(['quantity_on_hand' => 0.5, 'minimum_stock' => 2]);
    }

    public function outOfStock(): static
    {
        return $this->state(['quantity_on_hand' => 0, 'minimum_stock' => 2]);
    }
}
