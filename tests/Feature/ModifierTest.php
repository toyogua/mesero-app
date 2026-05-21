<?php

namespace Tests\Feature;

use App\Enums\CheckItemStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\KitchenStation;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModifierTest extends TestCase
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
        ]);
    }

    #[Test]
    public function adding_item_with_optional_modifier_stores_snapshot(): void
    {
        $group = ModifierGroup::factory()->create(['selection_type' => 'multi', 'required' => false]);
        $option = ModifierOption::factory()->create([
            'modifier_group_id' => $group->id,
            'name' => 'Extra queso',
            'price_delta' => 5.00,
        ]);

        $menuItem = MenuItem::factory()->create(['kitchen_station_id' => $this->station->id, 'price' => 60.00]);
        $menuItem->modifierGroups()->attach($group->id);

        $this->actingAs($this->waiter)
            ->post("/checks/{$this->check->id}/items", [
                'items' => [
                    ['menu_item_id' => $menuItem->id, 'quantity' => 1, 'modifiers' => [$option->id]],
                ],
            ])
            ->assertRedirect();

        $item = CheckItem::where('check_id', $this->check->id)->firstOrFail();
        $this->assertSame(1, $item->modifiers()->count());
        $this->assertEquals('Extra queso', $item->modifiers->first()->name_snapshot);
        $this->assertEquals(5.00, (float) $item->modifiers->first()->price_snapshot);
    }

    #[Test]
    public function modifier_price_rolls_into_check_totals(): void
    {
        $group = ModifierGroup::factory()->create(['selection_type' => 'multi', 'required' => false]);
        $option = ModifierOption::factory()->create([
            'modifier_group_id' => $group->id,
            'price_delta' => 10.00,
        ]);

        $menuItem = MenuItem::factory()->create(['kitchen_station_id' => $this->station->id, 'price' => 50.00]);
        $menuItem->modifierGroups()->attach($group->id);

        $this->actingAs($this->waiter)
            ->post("/checks/{$this->check->id}/items", [
                'items' => [
                    ['menu_item_id' => $menuItem->id, 'quantity' => 2, 'modifiers' => [$option->id]],
                ],
            ]);

        $this->check->refresh();
        // (50 + 10) × 2 = 120; IVA 12% = 14.4; total = 134.4
        $this->assertEquals(120.00, (float) $this->check->subtotal);
        $this->assertEquals(14.40, (float) $this->check->tax);
        $this->assertEquals(134.40, (float) $this->check->total);
    }

    #[Test]
    public function required_group_without_selection_is_rejected(): void
    {
        $group = ModifierGroup::factory()->required()->create(['name' => 'Término']);
        ModifierOption::factory()->create(['modifier_group_id' => $group->id, 'name' => 'Medio']);

        $menuItem = MenuItem::factory()->create(['kitchen_station_id' => $this->station->id]);
        $menuItem->modifierGroups()->attach($group->id);

        $this->actingAs($this->waiter)
            ->post("/checks/{$this->check->id}/items", [
                'items' => [
                    ['menu_item_id' => $menuItem->id, 'modifiers' => []],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, CheckItem::where('check_id', $this->check->id)->count());
    }

    #[Test]
    public function single_select_group_rejects_multiple_options(): void
    {
        $group = ModifierGroup::factory()->create(['selection_type' => 'single', 'required' => false]);
        $opt1 = ModifierOption::factory()->create(['modifier_group_id' => $group->id]);
        $opt2 = ModifierOption::factory()->create(['modifier_group_id' => $group->id]);

        $menuItem = MenuItem::factory()->create(['kitchen_station_id' => $this->station->id]);
        $menuItem->modifierGroups()->attach($group->id);

        $this->actingAs($this->waiter)
            ->post("/checks/{$this->check->id}/items", [
                'items' => [
                    ['menu_item_id' => $menuItem->id, 'modifiers' => [$opt1->id, $opt2->id]],
                ],
            ])
            ->assertSessionHasErrors('items');
    }

    #[Test]
    public function item_without_modifier_groups_can_be_added_without_modifiers(): void
    {
        $menuItem = MenuItem::factory()->create(['kitchen_station_id' => $this->station->id]);

        $this->actingAs($this->waiter)
            ->post("/checks/{$this->check->id}/items", [
                'items' => [['menu_item_id' => $menuItem->id]],
            ])
            ->assertRedirect();

        $this->assertSame(1, CheckItem::where('check_id', $this->check->id)->count());
    }

    #[Test]
    public function admin_can_manage_modifier_groups_and_options(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);

        // Create group
        $this->actingAs($admin)
            ->post('/admin/modifier-groups', [
                'name' => 'Término',
                'selection_type' => 'single',
                'required' => true,
            ])
            ->assertRedirect();

        $group = ModifierGroup::where('name', 'Término')->firstOrFail();
        $this->assertTrue($group->required);

        // Add option
        $this->actingAs($admin)
            ->post("/admin/modifier-groups/{$group->id}/options", [
                'name' => 'Término medio',
                'price_delta' => 0,
            ])
            ->assertRedirect();

        $this->assertSame(1, $group->fresh()->options()->count());

        // Assign to menu item
        $menuItem = MenuItem::factory()->create(['kitchen_station_id' => $this->station->id]);
        $this->actingAs($admin)
            ->put("/admin/menu-items/{$menuItem->id}/modifiers", [
                'group_ids' => [$group->id],
            ])
            ->assertRedirect();

        $this->assertSame(1, $menuItem->fresh()->modifierGroups()->count());
    }

    #[Test]
    public function print_ticket_route_returns_blade_view(): void
    {
        $this->actingAs($this->waiter)
            ->get("/checks/{$this->check->id}/ticket")
            ->assertOk()
            ->assertViewIs('tickets.check');
    }
}
