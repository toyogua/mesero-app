<?php

namespace Tests\Feature;

use App\Enums\CheckItemStatus;
use App\Enums\CheckStatus;
use App\Enums\UserRole;
use App\Events\CheckUpdated;
use App\Events\FloorChanged;
use App\Events\KitchenQueueChanged;
use App\Models\Area;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\KitchenStation;
use App\Models\MenuItem;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $waiter;

    private Area $area;

    private Table $table;

    private KitchenStation $station;

    protected function setUp(): void
    {
        parent::setUp();

        $this->waiter = User::factory()->create(['role' => UserRole::Waiter->value, 'active' => true]);
        $this->area = Area::factory()->create();
        $this->table = Table::factory()->create(['area_id' => $this->area->id]);
        $this->station = KitchenStation::factory()->create();
    }

    #[Test]
    public function full_flow_open_send_take_ready_served_close(): void
    {
        Event::fake([CheckUpdated::class, KitchenQueueChanged::class, FloorChanged::class]);

        // 1. Abrir comanda
        $this->actingAs($this->waiter)
            ->post("/floor/tables/{$this->table->id}/open", ['covers' => 3])
            ->assertRedirect();

        $check = Check::firstOrFail();
        $this->assertSame(CheckStatus::Open, $check->status);
        $this->assertSame(3, $check->covers);
        Event::assertDispatched(FloorChanged::class);
        Event::assertDispatched(CheckUpdated::class);

        // 2. Agregar items
        $menuItem = MenuItem::factory()->create([
            'kitchen_station_id' => $this->station->id,
            'price' => 50.00,
        ]);

        $this->post("/checks/{$check->id}/items", [
            'items' => [
                ['menu_item_id' => $menuItem->id, 'quantity' => 2],
            ],
        ])->assertRedirect();

        $check->refresh();
        $this->assertSame(1, $check->items->count());
        $this->assertEquals(100.00, $check->subtotal);
        $this->assertEquals(12.00, $check->tax);
        $this->assertEquals(112.00, $check->total);

        // 3. Enviar a cocina
        $this->post("/checks/{$check->id}/send")->assertRedirect();
        $item = $check->fresh()->items->first();
        $this->assertSame(CheckItemStatus::Ordered, $item->status);
        $this->assertNotNull($item->sent_at);
        Event::assertDispatched(KitchenQueueChanged::class);

        // 4. Cocina toma
        $this->post("/check-items/{$item->id}/take")->assertRedirect();
        $this->assertSame(CheckItemStatus::Preparing, $item->fresh()->status);

        // 5. Cocina marca listo
        $this->post("/check-items/{$item->id}/ready")->assertRedirect();
        $item->refresh();
        $this->assertSame(CheckItemStatus::Ready, $item->status);
        $this->assertNotNull($item->ready_at);

        // 6. Mesero sirve
        $this->post("/check-items/{$item->id}/served")->assertRedirect();
        $item->refresh();
        $this->assertSame(CheckItemStatus::Served, $item->status);
        $this->assertNotNull($item->served_at);

        // 7. Cerrar comanda
        $check->refresh();
        $this->assertTrue($check->isReadyToClose());
        $this->post("/checks/{$check->id}/close")->assertRedirect('/floor');
        $check->refresh();
        $this->assertSame(CheckStatus::Closed, $check->status);
        $this->assertNotNull($check->closed_at);
    }

    #[Test]
    public function cannot_close_check_with_pending_items(): void
    {
        $check = Check::factory()->create(['table_id' => $this->table->id, 'waiter_user_id' => $this->waiter->id]);
        CheckItem::factory()->ordered()->create([
            'check_id' => $check->id,
            'kitchen_station_id' => $this->station->id,
        ]);

        $this->actingAs($this->waiter)
            ->post("/checks/{$check->id}/close")
            ->assertSessionHasErrors('check');

        $this->assertSame(CheckStatus::Open, $check->fresh()->status);
    }

    #[Test]
    public function reopening_same_table_redirects_to_existing_check(): void
    {
        $existing = Check::factory()->create([
            'table_id' => $this->table->id,
            'waiter_user_id' => $this->waiter->id,
        ]);

        $response = $this->actingAs($this->waiter)
            ->post("/floor/tables/{$this->table->id}/open", ['covers' => 1]);

        $response->assertRedirect("/checks/{$existing->id}");
        $this->assertSame(1, Check::count());
    }

    #[Test]
    public function check_numbers_are_sequential_and_unique(): void
    {
        Event::fake();
        $this->actingAs($this->waiter);

        $t1 = $this->table;
        $t2 = Table::factory()->create(['area_id' => $this->area->id]);
        $t3 = Table::factory()->create(['area_id' => $this->area->id]);

        $this->post("/floor/tables/{$t1->id}/open");
        $this->post("/floor/tables/{$t2->id}/open");
        $this->post("/floor/tables/{$t3->id}/open");

        $numbers = Check::pluck('number')->sort()->values()->all();
        $this->assertSame(['C-000001', 'C-000002', 'C-000003'], $numbers);
    }

    #[Test]
    public function cannot_skip_state_transitions(): void
    {
        $check = Check::factory()->create(['table_id' => $this->table->id, 'waiter_user_id' => $this->waiter->id]);
        $item = CheckItem::factory()->create([
            'check_id' => $check->id,
            'kitchen_station_id' => $this->station->id,
            'status' => CheckItemStatus::Ordered->value,
            'sent_at' => now(),
        ]);

        // Intentar marcar servido sin pasar por preparing+ready
        $this->actingAs($this->waiter)
            ->post("/check-items/{$item->id}/served")
            ->assertSessionHasErrors('item');

        $this->assertSame(CheckItemStatus::Ordered, $item->fresh()->status);
    }

    #[Test]
    public function items_recalculate_check_totals_with_iva(): void
    {
        $check = Check::factory()->create(['table_id' => $this->table->id, 'waiter_user_id' => $this->waiter->id]);
        $item = MenuItem::factory()->create(['kitchen_station_id' => $this->station->id, 'price' => 75.00]);

        $this->actingAs($this->waiter)
            ->post("/checks/{$check->id}/items", [
                'items' => [
                    ['menu_item_id' => $item->id, 'quantity' => 4],
                ],
            ]);

        $check->refresh();
        // 75 × 4 = 300 + IVA 12% = 36 → total 336
        $this->assertEquals(300.00, $check->subtotal);
        $this->assertEquals(36.00, $check->tax);
        $this->assertEquals(336.00, $check->total);
    }

    #[Test]
    public function cancelled_items_dont_count_toward_totals(): void
    {
        $check = Check::factory()->create(['table_id' => $this->table->id, 'waiter_user_id' => $this->waiter->id]);
        $menu = MenuItem::factory()->create(['kitchen_station_id' => $this->station->id, 'price' => 100.00]);

        CheckItem::factory()->create([
            'check_id' => $check->id,
            'menu_item_id' => $menu->id,
            'kitchen_station_id' => $this->station->id,
            'name_snapshot' => $menu->name,
            'price_snapshot' => 100.00,
            'quantity' => 1,
            'status' => CheckItemStatus::Ordered->value,
        ]);
        CheckItem::factory()->create([
            'check_id' => $check->id,
            'menu_item_id' => $menu->id,
            'kitchen_station_id' => $this->station->id,
            'name_snapshot' => $menu->name,
            'price_snapshot' => 100.00,
            'quantity' => 1,
            'status' => CheckItemStatus::Cancelled->value,
        ]);

        $check->recalculate();

        $this->assertEquals(100.00, $check->subtotal);
        $this->assertEquals(12.00, $check->tax);
    }
}
