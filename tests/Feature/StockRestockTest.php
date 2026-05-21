<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Ingredient;
use App\Models\StockEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockRestockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);
    }

    #[Test]
    public function restock_increments_quantity_on_hand(): void
    {
        $ingredient = Ingredient::factory()->create(['quantity_on_hand' => 5.0]);

        $this->actingAs($this->admin)
            ->post("/admin/ingredients/{$ingredient->id}/restock", ['quantity' => 3.5])
            ->assertRedirect();

        $ingredient->refresh();
        $this->assertEqualsWithDelta(8.5, (float) $ingredient->quantity_on_hand, 0.0001);
    }

    #[Test]
    public function restock_creates_stock_entry_record(): void
    {
        $ingredient = Ingredient::factory()->create();

        $this->actingAs($this->admin)
            ->post("/admin/ingredients/{$ingredient->id}/restock", [
                'quantity' => 10,
                'notes'    => 'Proveedor XYZ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('stock_entries', [
            'ingredient_id' => $ingredient->id,
            'user_id'       => $this->admin->id,
            'notes'         => 'Proveedor XYZ',
        ]);
    }

    #[Test]
    public function restock_updates_cost_price_when_provided(): void
    {
        $ingredient = Ingredient::factory()->create(['cost_price' => 10.00]);

        $this->actingAs($this->admin)
            ->post("/admin/ingredients/{$ingredient->id}/restock", [
                'quantity'   => 5,
                'cost_price' => 12.50,
            ])
            ->assertRedirect();

        $ingredient->refresh();
        $this->assertEqualsWithDelta(12.50, (float) $ingredient->cost_price, 0.0001);
    }

    #[Test]
    public function restock_keeps_cost_price_when_not_provided(): void
    {
        $ingredient = Ingredient::factory()->create(['cost_price' => 10.00]);

        $this->actingAs($this->admin)
            ->post("/admin/ingredients/{$ingredient->id}/restock", ['quantity' => 5])
            ->assertRedirect();

        $ingredient->refresh();
        $this->assertEqualsWithDelta(10.00, (float) $ingredient->cost_price, 0.0001);
    }

    #[Test]
    public function restock_requires_positive_quantity(): void
    {
        $ingredient = Ingredient::factory()->create();

        $this->actingAs($this->admin)
            ->post("/admin/ingredients/{$ingredient->id}/restock", ['quantity' => 0])
            ->assertSessionHasErrors('quantity');
    }

    #[Test]
    public function non_admin_cannot_restock(): void
    {
        $waiter     = User::factory()->create(['role' => UserRole::Waiter->value, 'active' => true]);
        $ingredient = Ingredient::factory()->create();

        $this->actingAs($waiter)
            ->post("/admin/ingredients/{$ingredient->id}/restock", ['quantity' => 5])
            ->assertForbidden();
    }
}
