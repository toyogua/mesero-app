<?php

namespace Database\Factories;

use App\Models\KitchenStation;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'kitchen_station_id' => KitchenStation::factory(),
            'name' => fake()->randomElement([
                'Pepián de pollo', 'Hilachas', 'Kak-ik',
                'Tamales colorados', 'Chiles rellenos', 'Pan con chile',
                'Limonada', 'Café americano', 'Atol de elote',
            ]),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->randomFloat(2, 15, 120),
            'category' => fake()->randomElement(['platos_fuertes', 'bebidas', 'postres', 'entradas']),
            'sku' => fake()->optional()->bothify('SKU-####'),
            'active' => true,
        ];
    }
}
