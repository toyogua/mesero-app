<?php

namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Enums\FelStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Check;
use App\Models\FelInvoice;
use App\Models\Ingredient;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
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
    public function admin_can_view_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard')
                ->has('today_stats')
                ->has('open_checks')
                ->has('low_stock')
                ->has('fel_failures')
                ->has('week_revenue')
            );
    }

    #[Test]
    public function non_admin_is_forbidden(): void
    {
        $this->actingAs($this->waiter)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    #[Test]
    public function today_stats_counts_only_closed_checks_from_today(): void
    {
        $area  = Area::factory()->create();
        $table = Table::factory()->create(['area_id' => $area->id]);

        $check = Check::factory()->create([
            'table_id'        => $table->id,
            'waiter_user_id'  => $this->waiter->id,
            'status'          => CheckStatus::Closed->value,
            'total'           => 150,
            'covers'          => 3,
        ]);
        DB::table('checks')->where('id', $check->id)->update(['closed_at' => now()]);

        // Yesterday's check — must be excluded
        $old = Check::factory()->create([
            'table_id'       => $table->id,
            'waiter_user_id' => $this->waiter->id,
            'status'         => CheckStatus::Closed->value,
            'total'          => 500,
        ]);
        DB::table('checks')->where('id', $old->id)->update(['closed_at' => now()->subDay()]);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('today_stats.checks_count', 1)
                ->where('today_stats.covers', 3)
            );
    }

    #[Test]
    public function open_checks_listed(): void
    {
        $area  = Area::factory()->create();
        $table = Table::factory()->create(['area_id' => $area->id]);

        Check::factory()->create([
            'table_id'       => $table->id,
            'waiter_user_id' => $this->waiter->id,
            'status'         => CheckStatus::Open->value,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('open_checks', 1));
    }

    #[Test]
    public function low_stock_ingredients_listed(): void
    {
        Ingredient::factory()->create([
            'name'             => 'Tomate',
            'quantity_on_hand' => 1,
            'minimum_stock'    => 5,
            'active'           => true,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('low_stock', 1));
    }

    #[Test]
    public function fel_failures_listed(): void
    {
        $area  = Area::factory()->create();
        $table = Table::factory()->create(['area_id' => $area->id]);
        $check = Check::factory()->create([
            'table_id'       => $table->id,
            'waiter_user_id' => $this->waiter->id,
            'status'         => CheckStatus::Closed->value,
        ]);

        FelInvoice::factory()->failed()->create(['check_id' => $check->id]);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('fel_failures', 1));
    }
}
