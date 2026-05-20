<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Table>
 */
class TableFactory extends Factory
{
    protected $model = Table::class;

    public function definition(): array
    {
        return [
            'area_id' => Area::factory(),
            'name' => 'Mesa '.fake()->unique()->numberBetween(1, 99),
            'capacity' => fake()->numberBetween(2, 8),
            'active' => true,
        ];
    }
}
