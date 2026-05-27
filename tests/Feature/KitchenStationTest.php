<?php

namespace Tests\Feature;

use App\Enums\CheckItemStatus;
use App\Enums\UserRole;
use App\Models\CheckItem;
use App\Models\KitchenStation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KitchenStationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $waiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin  = User::factory()->create(['role' => UserRole::Admin->value,  'active' => true]);
        $this->waiter = User::factory()->create(['role' => UserRole::Waiter->value, 'active' => true]);
    }

    #[Test]
    public function admin_can_view_stations(): void
    {
        KitchenStation::factory()->create(['name' => 'Grill']);

        $this->actingAs($this->admin)
            ->get('/admin/kitchen-stations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/KitchenStations/Index')
                ->has('stations', 1)
            );
    }

    #[Test]
    public function admin_can_create_station(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/kitchen-stations', [
                'name'          => 'Parrilla',
                'display_order' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kitchen_stations', ['name' => 'Parrilla', 'code' => 'parrilla']);
    }

    #[Test]
    public function code_auto_generated_when_not_provided(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/kitchen-stations', ['name' => 'Cocina Fría'])
            ->assertRedirect();

        $this->assertDatabaseHas('kitchen_stations', ['name' => 'Cocina Fría', 'code' => 'cocina-fria']);
    }

    #[Test]
    public function duplicate_code_rejected(): void
    {
        KitchenStation::factory()->create(['code' => 'bar']);

        $this->actingAs($this->admin)
            ->post('/admin/kitchen-stations', ['name' => 'Bar 2', 'code' => 'bar'])
            ->assertSessionHasErrors('code');
    }

    #[Test]
    public function admin_can_update_station(): void
    {
        $station = KitchenStation::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->admin)
            ->patch("/admin/kitchen-stations/{$station->id}", [
                'name'          => 'New Name',
                'code'          => $station->code,
                'display_order' => 2,
                'active'        => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kitchen_stations', ['id' => $station->id, 'name' => 'New Name']);
    }

    #[Test]
    public function admin_can_deactivate_station_without_active_items(): void
    {
        $station = KitchenStation::factory()->create();

        $this->actingAs($this->admin)
            ->delete("/admin/kitchen-stations/{$station->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('kitchen_stations', ['id' => $station->id, 'active' => false]);
    }

    #[Test]
    public function cannot_deactivate_station_with_items_in_progress(): void
    {
        $station = KitchenStation::factory()->create();
        CheckItem::factory()->create([
            'kitchen_station_id' => $station->id,
            'status'             => CheckItemStatus::Preparing->value,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/kitchen-stations/{$station->id}")
            ->assertSessionHasErrors('station');
    }

    #[Test]
    public function non_admin_is_forbidden(): void
    {
        $this->actingAs($this->waiter)
            ->get('/admin/kitchen-stations')
            ->assertForbidden();
    }
}
