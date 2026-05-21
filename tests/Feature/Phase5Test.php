<?php

namespace Tests\Feature;

use App\Enums\CheckItemStatus;
use App\Enums\CheckStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\Ingredient;
use App\Models\KitchenStation;
use App\Models\MenuItem;
use App\Models\PaymentSplit;
use App\Models\RecipeItem;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Phase5Test extends TestCase
{
    use RefreshDatabase;

    private User $waiter;
    private Check $check;
    private KitchenStation $station;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->waiter = User::factory()->create(['role' => UserRole::Waiter->value, 'active' => true]);
        $area = Area::factory()->create();
        $table = Table::factory()->create(['area_id' => $area->id]);
        $this->station = KitchenStation::factory()->create();
        $this->check = Check::factory()->create([
            'table_id' => $table->id,
            'waiter_user_id' => $this->waiter->id,
            'status' => CheckStatus::Open->value,
            'subtotal' => 100.00,
            'tax' => 12.00,
            'tip' => 0,
            'total' => 112.00,
        ]);
    }

    // ── Tip ──────────────────────────────────────────────────────────────────

    #[Test]
    public function tip_can_be_set_on_open_check(): void
    {
        $this->actingAs($this->waiter)
            ->patch("/checks/{$this->check->id}/tip", ['amount' => 15.00])
            ->assertRedirect();

        $this->check->refresh();
        $this->assertSame('15.00', $this->check->tip);
        // total = recomputed subtotal + tax + 15.00
        $expected = round((float)$this->check->subtotal + (float)$this->check->tax + 15.00, 2);
        $this->assertEqualsWithDelta($expected, (float)$this->check->total, 0.01);
    }

    #[Test]
    public function tip_is_included_in_total(): void
    {
        CheckItem::factory()->create([
            'check_id'          => $this->check->id,
            'kitchen_station_id'=> $this->station->id,
            'price_snapshot'    => 100.00,
            'quantity'          => 1,
            'status'            => CheckItemStatus::Served->value,
            'served_at'         => now(),
        ]);
        $this->check->recalculate();

        $this->actingAs($this->waiter)
            ->patch("/checks/{$this->check->id}/tip", ['amount' => 20.00])
            ->assertRedirect();

        $this->check->refresh();
        // subtotal 100, tax 12 (12%), tip 20, total = 132
        $this->assertEqualsWithDelta(132.00, (float)$this->check->total, 0.01);
    }

    #[Test]
    public function tip_requires_non_negative_amount(): void
    {
        $this->actingAs($this->waiter)
            ->patch("/checks/{$this->check->id}/tip", ['amount' => -5.00])
            ->assertSessionHasErrors('amount');
    }

    // ── Splits ───────────────────────────────────────────────────────────────

    #[Test]
    public function check_can_be_split_equally(): void
    {
        $this->actingAs($this->waiter)
            ->post("/checks/{$this->check->id}/splits", ['parts' => 3])
            ->assertRedirect();

        $this->assertSame(3, PaymentSplit::where('check_id', $this->check->id)->count());

        $splits = PaymentSplit::where('check_id', $this->check->id)->get();
        $totalSplit = $splits->sum('amount');
        $this->assertEqualsWithDelta((float) $this->check->total, $totalSplit, 0.01);
    }

    #[Test]
    public function split_can_be_marked_paid(): void
    {
        $split = PaymentSplit::create([
            'check_id' => $this->check->id,
            'label' => 'Parte 1',
            'amount' => 56.00,
        ]);

        $this->actingAs($this->waiter)
            ->patch("/check-splits/{$split->id}/pay", ['method' => 'cash'])
            ->assertRedirect();

        $split->refresh();
        $this->assertNotNull($split->paid_at);
        $this->assertSame('cash', $split->method);
    }

    #[Test]
    public function splits_can_be_reset(): void
    {
        PaymentSplit::create(['check_id' => $this->check->id, 'label' => 'P1', 'amount' => 50]);
        PaymentSplit::create(['check_id' => $this->check->id, 'label' => 'P2', 'amount' => 62]);

        $this->actingAs($this->waiter)
            ->delete("/checks/{$this->check->id}/splits")
            ->assertRedirect();

        $this->assertSame(0, PaymentSplit::where('check_id', $this->check->id)->count());
    }

    // ── Reports ──────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_view_reports(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Reports/Index'));
    }

    #[Test]
    public function report_summary_counts_closed_checks_in_range(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);

        Check::factory()->create([
            'status' => CheckStatus::Closed->value,
            'subtotal' => 200.00,
            'tax' => 24.00,
            'tip' => 10.00,
            'total' => 234.00,
            'closed_at' => now(),
            'opened_at' => now()->subHour(),
            'waiter_user_id' => $this->waiter->id,
            'table_id' => $this->check->table_id,
        ]);

        $this->actingAs($admin)
            ->get('/admin/reports?from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Reports/Index')
                ->where('summary.total_checks', 1)
                ->where('summary.total_revenue', 234)
            );
    }

    #[Test]
    public function food_cost_is_calculated_from_recipe_and_cost_price(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);

        $ingredient = Ingredient::factory()->create(['cost_price' => 5.0, 'quantity_on_hand' => 100, 'minimum_stock' => 10]);
        $menu = MenuItem::factory()->create(['kitchen_station_id' => $this->station->id, 'price' => 50.00]);
        RecipeItem::factory()->create(['menu_item_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity_used' => 2]);

        $closedCheck = Check::factory()->create([
            'status' => CheckStatus::Closed->value,
            'subtotal' => 50.00,
            'tax' => 6.00,
            'total' => 56.00,
            'closed_at' => now(),
            'opened_at' => now()->subHour(),
            'waiter_user_id' => $this->waiter->id,
            'table_id' => $this->check->table_id,
        ]);
        CheckItem::factory()->create([
            'check_id' => $closedCheck->id,
            'menu_item_id' => $menu->id,
            'kitchen_station_id' => $this->station->id,
            'price_snapshot' => 50.00,
            'quantity' => 1,
            'status' => CheckItemStatus::Served->value,
            'served_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/reports?from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('food_cost', 10)  // 2 units * 1 qty * Q5.00 cost
            );
    }

    #[Test]
    public function non_admin_cannot_access_reports(): void
    {
        $this->actingAs($this->waiter)
            ->get('/admin/reports')
            ->assertForbidden();
    }
}
