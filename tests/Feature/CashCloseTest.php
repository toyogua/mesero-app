<?php

namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\CashClose;
use App\Models\Check;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CashCloseTest extends TestCase
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

    private function makeClosedCheck(array $attrs = []): Check
    {
        $table = Table::factory()->for(Area::factory())->create();

        return Check::factory()->create(array_merge([
            'table_id'       => $table->id,
            'waiter_user_id' => $this->waiter->id,
            'status'         => CheckStatus::Closed,
            'subtotal'       => 100.00,
            'tax'            => 12.00,
            'tip'            => 0.00,
            'total'          => 112.00,
            'opened_at'      => now()->subHour(),
            'closed_at'      => now(),
        ], $attrs));
    }

    #[Test]
    public function admin_can_view_cash_close_page(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/cash-closes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/CashCloses/Index')
                ->has('closes')
                ->has('preview')
            );
    }

    #[Test]
    public function preview_aggregates_unclosed_checks(): void
    {
        $this->makeClosedCheck(['total' => 100.00]);
        $this->makeClosedCheck(['total' => 200.00]);

        $this->actingAs($this->admin)
            ->get('/admin/cash-closes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('preview.checks_count', 2)
                ->where('preview.total', 300)
            );
    }

    #[Test]
    public function store_creates_cash_close_record(): void
    {
        $this->makeClosedCheck();

        $this->actingAs($this->admin)
            ->post('/admin/cash-closes', [
                'cash_counted' => 112.00,
                'notes'        => 'Cuadre exacto',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_closes', [
            'user_id'  => $this->admin->id,
            'notes'    => 'Cuadre exacto',
        ]);
    }

    #[Test]
    public function second_close_only_counts_new_checks(): void
    {
        $cutoff = now()->subHour();

        // Simulate a first close that ended one hour ago
        CashClose::create([
            'user_id'      => $this->admin->id,
            'period_from'  => now()->subYears(10),
            'period_to'    => $cutoff,
            'checks_count' => 1,
            'subtotal'     => 100,
            'tax'          => 12,
            'tip'          => 0,
            'total'        => 112,
        ]);

        // New check closed AFTER the cutoff
        $this->makeClosedCheck(['closed_at' => now(), 'total' => 50.00]);

        $this->actingAs($this->admin)
            ->get('/admin/cash-closes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('preview.checks_count', 1)
                ->where('preview.total', 50)
            );
    }

    #[Test]
    public function close_fails_when_no_pending_checks(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/cash-closes')
            ->assertSessionHasErrors('close');
    }

    #[Test]
    public function non_admin_is_forbidden(): void
    {
        $this->actingAs($this->waiter)->get('/admin/cash-closes')->assertForbidden();
        $this->actingAs($this->waiter)->post('/admin/cash-closes')->assertForbidden();
    }
}
