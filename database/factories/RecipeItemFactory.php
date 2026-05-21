<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipeItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'menu_item_id' => MenuItem::factory(),
            'ingredient_id' => Ingredient::factory(),
            'quantity_used' => $this->faker->randomFloat(4, 0.1, 2),
        ];
    }
}
