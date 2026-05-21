<?php

namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Check;
use App\Models\KitchenStation;
use App\Models\MenuItem;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $waiter;
    private KitchenStation $station;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin   = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);
        $this->waiter  = User::factory()->create(['role' => UserRole::Waiter->value, 'active' => true]);
        $this->station = KitchenStation::factory()->create();
    }

    // ── MenuItems ────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_list_menu_items(): void
    {
        MenuItem::factory()->create(['kitchen_station_id' => $this->station->id]);

        $this->actingAs($this->admin)
            ->get('/admin/menu-items')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/MenuItems/Index')->has('items', 1));
    }

    #[Test]
    public function admin_can_create_menu_item(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/menu-items', [
                'name'               => 'Burger',
                'price'              => 50.00,
                'category'           => 'platos_fuertes',
                'kitchen_station_id' => $this->station->id,
                'active'             => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('menu_items', ['name' => 'Burger']);
    }

    #[Test]
    public function destroy_menu_item_only_deactivates(): void
    {
        $item = MenuItem::factory()->create(['kitchen_station_id' => $this->station->id, 'active' => true]);

        $this->actingAs($this->admin)->delete("/admin/menu-items/{$item->id}")->assertRedirect();

        $item->refresh();
        $this->assertFalse($item->active);
        $this->assertDatabaseHas('menu_items', ['id' => $item->id]);
    }

    // ── Areas ────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_create_area(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/areas', ['name' => 'Terraza', 'display_order' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('areas', ['name' => 'Terraza']);
    }

    #[Test]
    public function area_with_open_check_cannot_be_deactivated(): void
    {
        $area = Area::factory()->create();
        $table = Table::factory()->create(['area_id' => $area->id]);
        Check::factory()->create([
            'table_id' => $table->id,
            'waiter_user_id' => $this->waiter->id,
            'status' => CheckStatus::Open->value,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/areas/{$area->id}")
            ->assertSessionHasErrors('area');

        $area->refresh();
        $this->assertTrue($area->active);
    }

    // ── Tables ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_create_table(): void
    {
        $area = Area::factory()->create();

        $this->actingAs($this->admin)
            ->post('/admin/tables', ['area_id' => $area->id, 'name' => 'Mesa 1', 'capacity' => 4])
            ->assertRedirect();

        $this->assertDatabaseHas('tables', ['name' => 'Mesa 1', 'area_id' => $area->id]);
    }

    #[Test]
    public function occupied_table_cannot_be_deactivated(): void
    {
        $area = Area::factory()->create();
        $table = Table::factory()->create(['area_id' => $area->id]);
        Check::factory()->create([
            'table_id' => $table->id,
            'waiter_user_id' => $this->waiter->id,
            'status' => CheckStatus::Open->value,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/tables/{$table->id}")
            ->assertSessionHasErrors('table');
    }

    // ── Users ────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_create_waiter_with_pin(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/users', ['name' => 'Nuevo Mesero', 'role' => 'waiter', 'pin' => '7777'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['name' => 'Nuevo Mesero', 'role' => 'waiter']);
    }

    #[Test]
    public function pin_must_be_unique(): void
    {
        User::factory()->create(['pin' => '1234', 'role' => UserRole::Waiter->value]);

        $this->actingAs($this->admin)
            ->post('/admin/users', ['name' => 'Otro', 'role' => 'waiter', 'pin' => '1234'])
            ->assertSessionHasErrors('pin');
    }

    #[Test]
    public function admin_cannot_deactivate_own_user(): void
    {
        $this->actingAs($this->admin)
            ->delete("/admin/users/{$this->admin->id}")
            ->assertSessionHasErrors('user');

        $this->admin->refresh();
        $this->assertTrue($this->admin->active);
    }

    #[Test]
    public function non_admin_blocked_from_admin_crud(): void
    {
        $this->actingAs($this->waiter)->get('/admin/menu-items')->assertForbidden();
        $this->actingAs($this->waiter)->get('/admin/areas')->assertForbidden();
        $this->actingAs($this->waiter)->get('/admin/tables')->assertForbidden();
        $this->actingAs($this->waiter)->get('/admin/users')->assertForbidden();
    }
}
