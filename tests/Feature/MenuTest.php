<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\KitchenStation;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_menu_accessible_without_auth(): void
    {
        $this->get('/menu')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Menu/Index')
                ->has('categories')
                ->has('restaurant_name')
            );
    }

    #[Test]
    public function menu_shows_active_items_grouped_by_category(): void
    {
        $station = KitchenStation::factory()->create();

        MenuItem::factory()->create([
            'kitchen_station_id' => $station->id,
            'name'     => 'Tacos',
            'category' => 'Entradas',
            'active'   => true,
        ]);
        MenuItem::factory()->create([
            'kitchen_station_id' => $station->id,
            'name'     => 'Hamburguesa',
            'category' => 'Platos fuertes',
            'active'   => true,
        ]);
        MenuItem::factory()->create([
            'kitchen_station_id' => $station->id,
            'name'     => 'Inactivo',
            'category' => 'Entradas',
            'active'   => false,
        ]);

        $this->get('/menu')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('categories', 2)
                ->where('categories.0.category', 'Entradas')
                ->has('categories.0.items', 1)
            );
    }

    #[Test]
    public function admin_can_view_qr_menu_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);

        $this->actingAs($admin)
            ->get('/admin/qr-menu')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/QrMenu'));
    }

    #[Test]
    public function non_admin_cannot_view_qr_menu_admin_page(): void
    {
        $waiter = User::factory()->create(['role' => UserRole::Waiter->value, 'active' => true]);

        $this->actingAs($waiter)
            ->get('/admin/qr-menu')
            ->assertForbidden();
    }
}
