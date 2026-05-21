<?php

namespace Tests\Feature;

use App\Enums\CheckItemStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\Ingredient;
use App\Models\KitchenStation;
use App\Models\MenuItem;
use App\Models\RecipeItem;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InventoryDeductionTest extends TestCase
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
    public function serving_an_item_deducts_recipe_ingredients_from_stock(): void
    {
        $meat = Ingredient::factory()->create(['quantity_on_hand' => 10.0, 'unit' => 'kg']);
        $bun = Ingredient::factory()->create(['quantity_on_hand' => 20.0, 'unit' => 'unit']);

        $burger = MenuItem::factory()->create(['kitchen_station_id' => $this->station->id]);
        RecipeItem::factory()->create(['menu_item_id' => $burger->id, 'ingredient_id' => $meat->id, 'quantity_used' => 0.25]);
        RecipeItem::factory()->create(['menu_item_id' => $burger->id, 'ingredient_id' => $bun->id, 'quantity_used' => 1]);

        $item = CheckItem::factory()->create([
            'check_id' => $this->check->id,
            'menu_item_id' => $burger->id,
            'kitchen_station_id' => $this->station->id,
            'status' => CheckItemStatus::Ready->value,
            'quantity' => 2,
            'ready_at' => now(),
        ]);

        $this->actingAs($this->waiter)
            ->post("/check-items/{$item->id}/served")
            ->assertRedirect();

        // 2 burgers × 0.25 kg = 0.5 kg deducted → 9.5 remaining
        $this->assertEquals(9.5, (float) $meat->fresh()->quantity_on_hand);
        // 2 burgers × 1 bun = 2 buns deducted → 18 remaining
        $this->assertEquals(18.0, (float) $bun->fresh()->quantity_on_hand);
    }

    #[Test]
    public function serving_item_without_recipe_does_not_fail(): void
    {
        $menuItem = MenuItem::factory()->create(['kitchen_station_id' => $this->station->id]);

        $item = CheckItem::factory()->create([
            'check_id' => $this->check->id,
            'menu_item_id' => $menuItem->id,
            'kitchen_station_id' => $this->station->id,
            'status' => CheckItemStatus::Ready->value,
            'ready_at' => now(),
        ]);

        $this->actingAs($this->waiter)
            ->post("/check-items/{$item->id}/served")
            ->assertRedirect();

        $this->assertSame(CheckItemStatus::Served, $item->fresh()->status);
    }

    #[Test]
    public function cancelling_an_item_does_not_deduct_stock(): void
    {
        $ingredient = Ingredient::factory()->create(['quantity_on_hand' => 10.0]);
        $menuItem = MenuItem::factory()->create(['kitchen_station_id' => $this->station->id]);
        RecipeItem::factory()->create([
            'menu_item_id' => $menuItem->id,
            'ingredient_id' => $ingredient->id,
            'quantity_used' => 1.0,
        ]);

        $item = CheckItem::factory()->create([
            'check_id' => $this->check->id,
            'menu_item_id' => $menuItem->id,
            'kitchen_station_id' => $this->station->id,
            'status' => CheckItemStatus::Ordered->value,
            'sent_at' => now(),
        ]);

        $this->actingAs($this->waiter)
            ->delete("/check-items/{$item->id}")
            ->assertRedirect();

        $this->assertEquals(10.0, (float) $ingredient->fresh()->quantity_on_hand);
    }

    #[Test]
    public function deduction_is_proportional_to_quantity(): void
    {
        $sauce = Ingredient::factory()->create(['quantity_on_hand' => 5.0, 'unit' => 'L']);
        $pizza = MenuItem::factory()->create(['kitchen_station_id' => $this->station->id]);
        RecipeItem::factory()->create([
            'menu_item_id' => $pizza->id,
            'ingredient_id' => $sauce->id,
            'quantity_used' => 0.1,
        ]);

        $item = CheckItem::factory()->create([
            'check_id' => $this->check->id,
            'menu_item_id' => $pizza->id,
            'kitchen_station_id' => $this->station->id,
            'status' => CheckItemStatus::Ready->value,
            'quantity' => 3,
            'ready_at' => now(),
        ]);

        $this->actingAs($this->waiter)
            ->post("/check-items/{$item->id}/served")
            ->assertRedirect();

        // 3 pizzas × 0.1 L = 0.3 L deducted → 4.7 remaining
        $this->assertEqualsWithDelta(4.7, (float) $sauce->fresh()->quantity_on_hand, 0.0001);
    }

    #[Test]
    public function admin_can_manage_ingredients(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);

        // Create
        $this->actingAs($admin)
            ->post('/admin/ingredients', [
                'name' => 'Queso manchego',
                'unit' => 'kg',
                'quantity_on_hand' => '5.5',
                'minimum_stock' => '1.0',
            ])
            ->assertRedirect();

        $ingredient = Ingredient::where('name', 'Queso manchego')->firstOrFail();
        $this->assertEquals(5.5, (float) $ingredient->quantity_on_hand);

        // Update
        $this->actingAs($admin)
            ->patch("/admin/ingredients/{$ingredient->id}", [
                'name' => 'Queso manchego',
                'unit' => 'kg',
                'quantity_on_hand' => '3.0',
                'minimum_stock' => '1.0',
                'active' => true,
            ])
            ->assertRedirect();

        $this->assertEquals(3.0, (float) $ingredient->fresh()->quantity_on_hand);

        // Delete
        $this->actingAs($admin)
            ->delete("/admin/ingredients/{$ingredient->id}")
            ->assertRedirect();

        $this->assertNull(Ingredient::find($ingredient->id));
    }

    #[Test]
    public function non_admin_cannot_access_ingredients(): void
    {
        $this->actingAs($this->waiter)
            ->get('/admin/ingredients')
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_save_recipe_for_menu_item(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);
        $menuItem = MenuItem::factory()->create(['kitchen_station_id' => $this->station->id]);
        $ing1 = Ingredient::factory()->create();
        $ing2 = Ingredient::factory()->create();

        $this->actingAs($admin)
            ->put("/admin/menu-items/{$menuItem->id}/recipe", [
                'lines' => [
                    ['ingredient_id' => $ing1->id, 'quantity_used' => 0.5],
                    ['ingredient_id' => $ing2->id, 'quantity_used' => 2.0],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, $menuItem->fresh()->recipeItems()->count());
    }
}
