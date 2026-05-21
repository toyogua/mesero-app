<?php

namespace Database\Factories;

use App\Models\ModifierGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class ModifierOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'modifier_group_id' => ModifierGroup::factory(),
            'name' => $this->faker->word(),
            'price_delta' => 0,
            'display_order' => 0,
            'active' => true,
        ];
    }

    public function priced(float $delta): static
    {
        return $this->state(['price_delta' => $delta]);
    }
}
