<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Ingredient;
use App\Models\StockEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockEntryHistoryTest extends TestCase
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
    public function admin_can_view_stock_entry_history(): void
    {
        $ingredient = Ingredient::factory()->create();
        StockEntry::create([
            'ingredient_id' => $ingredient->id,
            'user_id'       => $this->admin->id,
            'quantity'      => 5,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/stock-entries')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/StockEntries/Index')
                ->has('entries.data', 1)
                ->has('ingredients')
                ->has('filters')
            );
    }

    #[Test]
    public function filter_by_ingredient_id(): void
    {
        $a = Ingredient::factory()->create();
        $b = Ingredient::factory()->create();

        StockEntry::create(['ingredient_id' => $a->id, 'user_id' => $this->admin->id, 'quantity' => 1]);
        StockEntry::create(['ingredient_id' => $b->id, 'user_id' => $this->admin->id, 'quantity' => 2]);

        $this->actingAs($this->admin)
            ->get('/admin/stock-entries?ingredient_id='.$a->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('entries.data', 1));
    }

    #[Test]
    public function entries_outside_date_range_excluded(): void
    {
        $ingredient = Ingredient::factory()->create();

        $entry = StockEntry::create([
            'ingredient_id' => $ingredient->id,
            'user_id'       => $this->admin->id,
            'quantity'      => 3,
        ]);
        \Illuminate\Support\Facades\DB::table('stock_entries')
            ->where('id', $entry->id)
            ->update(['created_at' => now()->subDays(60), 'updated_at' => now()->subDays(60)]);

        $this->actingAs($this->admin)
            ->get('/admin/stock-entries?from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('entries.data', 0));
    }

    #[Test]
    public function non_admin_is_forbidden(): void
    {
        $this->actingAs($this->waiter)
            ->get('/admin/stock-entries')
            ->assertForbidden();
    }
}
