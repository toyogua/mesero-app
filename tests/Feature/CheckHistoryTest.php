<?php

namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Check;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckHistoryTest extends TestCase
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
        $area  = Area::factory()->create();
        $table = Table::factory()->create(['area_id' => $area->id]);

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
    public function admin_can_view_closed_checks(): void
    {
        $this->makeClosedCheck();

        $this->actingAs($this->admin)
            ->get('/admin/checks')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Checks/Index')
                ->has('checks.data', 1)
                ->has('waiters')
                ->has('filters')
            );
    }

    #[Test]
    public function open_checks_are_excluded(): void
    {
        $area  = Area::factory()->create();
        $table = Table::factory()->create(['area_id' => $area->id]);
        Check::factory()->create([
            'table_id'       => $table->id,
            'waiter_user_id' => $this->waiter->id,
            'status'         => CheckStatus::Open,
            'opened_at'      => now(),
            'closed_at'      => null,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/checks')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('checks.data', 0));
    }

    #[Test]
    public function filter_by_waiter_id(): void
    {
        $otherWaiter = User::factory()->create(['role' => UserRole::Waiter->value, 'active' => true]);

        $this->makeClosedCheck(['waiter_user_id' => $this->waiter->id]);
        $this->makeClosedCheck(['waiter_user_id' => $otherWaiter->id]);

        $this->actingAs($this->admin)
            ->get('/admin/checks?waiter_id='.$this->waiter->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('checks.data', 1));
    }

    #[Test]
    public function filter_by_date_range_excludes_older_checks(): void
    {
        $this->makeClosedCheck(['closed_at' => now()->subDays(10)]);
        $this->makeClosedCheck(['closed_at' => now()]);

        $this->actingAs($this->admin)
            ->get('/admin/checks?from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('checks.data', 1));
    }

    #[Test]
    public function search_by_check_number(): void
    {
        $check = $this->makeClosedCheck();
        $this->makeClosedCheck();

        $this->actingAs($this->admin)
            ->get('/admin/checks?search='.$check->number)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('checks.data', 1));
    }

    #[Test]
    public function non_admin_is_forbidden(): void
    {
        $this->actingAs($this->waiter)
            ->get('/admin/checks')
            ->assertForbidden();
    }

    #[Test]
    public function response_includes_check_detail_fields(): void
    {
        $this->makeClosedCheck();

        $this->actingAs($this->admin)
            ->get('/admin/checks')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('checks.data.0.number')
                ->has('checks.data.0.table')
                ->has('checks.data.0.waiter')
                ->has('checks.data.0.total')
                ->has('checks.data.0.items_count')
                ->has('checks.data.0.closed_at')
                ->has('checks.data.0.fel_status')
            );
    }
}
