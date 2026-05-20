<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Check;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FloorViewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function floor_shows_areas_and_tables_with_occupancy(): void
    {
        $area = Area::factory()->create(['name' => 'Salón', 'display_order' => 1]);
        $occupiedTable = Table::factory()->create(['area_id' => $area->id, 'name' => 'Mesa 1']);
        $freeTable = Table::factory()->create(['area_id' => $area->id, 'name' => 'Mesa 2']);

        $waiter = User::factory()->create(['role' => UserRole::Waiter->value, 'active' => true]);
        Check::factory()->create([
            'table_id' => $occupiedTable->id,
            'waiter_user_id' => $waiter->id,
        ]);

        $this->actingAs($waiter)->get('/floor')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Floor/Index')
                    ->has('areas', 1)
                    ->where('areas.0.tables.0.name', 'Mesa 1')
                    ->where('areas.0.tables.0.occupied', true)
                    ->where('areas.0.tables.1.name', 'Mesa 2')
                    ->where('areas.0.tables.1.occupied', false)
            );
    }

    #[Test]
    public function inactive_areas_are_hidden(): void
    {
        Area::factory()->create(['active' => false]);
        $waiter = User::factory()->create(['active' => true]);

        $this->actingAs($waiter)->get('/floor')
            ->assertInertia(fn ($page) => $page->has('areas', 0));
    }
}
