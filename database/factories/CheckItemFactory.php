<?php

namespace Database\Factories;

use App\Enums\CheckItemStatus;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckItem>
 */
class CheckItemFactory extends Factory
{
    protected $model = CheckItem::class;

    public function definition(): array
    {
        $menuItem = MenuItem::factory()->create();

        return [
            'check_id' => Check::factory(),
            'menu_item_id' => $menuItem->id,
            'kitchen_station_id' => $menuItem->kitchen_station_id,
            'name_snapshot' => $menuItem->name,
            'price_snapshot' => $menuItem->price,
            'quantity' => fake()->numberBetween(1, 3),
            'status' => CheckItemStatus::Draft,
        ];
    }

    public function ordered(): self
    {
        return $this->state(['status' => CheckItemStatus::Ordered, 'sent_at' => now()]);
    }

    public function served(): self
    {
        return $this->state([
            'status' => CheckItemStatus::Served,
            'sent_at' => now()->subMinutes(20),
            'ready_at' => now()->subMinutes(5),
            'served_at' => now(),
        ]);
    }
}
