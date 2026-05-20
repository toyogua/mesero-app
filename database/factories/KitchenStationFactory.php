<?php

namespace Database\Factories;

use App\Models\KitchenStation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KitchenStation>
 */
class KitchenStationFactory extends Factory
{
    protected $model = KitchenStation::class;

    public function definition(): array
    {
        $name = fake()->randomElement([
            'Cocina caliente', 'Cocina fría', 'Bar', 'Postres', 'Panadería',
        ]);

        return [
            'name' => $name,
            'code' => Str::slug($name).'_'.fake()->unique()->numberBetween(1, 9999),
            'display_order' => fake()->numberBetween(0, 5),
            'active' => true,
        ];
    }
}
