<?php

namespace Database\Factories;

use App\Enums\CheckStatus;
use App\Models\Check;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Check>
 */
class CheckFactory extends Factory
{
    protected $model = Check::class;

    public function definition(): array
    {
        return [
            'number' => 'C-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'table_id' => Table::factory(),
            'waiter_user_id' => User::factory(),
            'status' => CheckStatus::Open,
            'covers' => fake()->numberBetween(1, 6),
            'subtotal' => 0,
            'tax' => 0,
            'tip' => 0,
            'total' => 0,
            'opened_at' => now(),
        ];
    }

    public function closed(): self
    {
        return $this->state(['status' => CheckStatus::Closed, 'closed_at' => now()]);
    }
}
