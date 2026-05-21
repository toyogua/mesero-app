<?php

namespace Database\Factories;

use App\Models\Check;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentSplitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'check_id' => Check::factory(),
            'label'    => 'Parte '.$this->faker->numberBetween(1, 5),
            'amount'   => $this->faker->randomFloat(2, 10, 500),
            'method'   => null,
            'paid_at'  => null,
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'method'  => $this->faker->randomElement(['cash', 'card']),
            'paid_at' => now(),
        ]);
    }
}
