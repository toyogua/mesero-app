<?php

namespace Database\Factories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    protected $model = Area::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Salón principal', 'Terraza', 'VIP', 'Barra']),
            'display_order' => fake()->numberBetween(0, 10),
            'active' => true,
        ];
    }
}
