<?php

namespace Database\Factories;

use App\Enums\FelStatus;
use App\Models\Check;
use Illuminate\Database\Eloquent\Factories\Factory;

class FelInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'check_id'      => Check::factory(),
            'status'        => FelStatus::Pending,
            'receptor_nit'  => 'CF',
            'receptor_name' => 'CONSUMIDOR FINAL',
        ];
    }

    public function issued(): static
    {
        return $this->state([
            'status'  => FelStatus::Issued,
            'uuid'    => $this->faker->uuid(),
            'serie'   => 'A',
            'numero'  => (string) $this->faker->numberBetween(1, 99999),
            'issued_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status'        => FelStatus::Failed,
            'error_message' => 'Connection timeout',
            'retries'       => 1,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status'        => FelStatus::Cancelled,
            'uuid'          => $this->faker->uuid(),
            'serie'         => 'A',
            'numero'        => (string) $this->faker->numberBetween(1, 99999),
            'issued_at'     => now()->subHour(),
            'cancel_reason' => 'Error en datos del cliente',
            'cancelled_at'  => now(),
        ]);
    }
}
