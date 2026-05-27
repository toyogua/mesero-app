<?php

namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Check;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $waiter;
    private Table $source;
    private Table $target;
    private Check $check;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->admin  = User::factory()->create(['role' => UserRole::Admin->value,  'active' => true]);
        $this->waiter = User::factory()->create(['role' => UserRole::Waiter->value, 'active' => true]);

        $area         = Area::factory()->create();
        $this->source = Table::factory()->create(['area_id' => $area->id]);
        $this->target = Table::factory()->create(['area_id' => $area->id]);

        $this->check = Check::factory()->create([
            'table_id'       => $this->source->id,
            'waiter_user_id' => $this->waiter->id,
            'status'         => CheckStatus::Open->value,
        ]);
    }

    #[Test]
    public function check_can_be_transferred_to_empty_table(): void
    {
        $this->actingAs($this->waiter)
            ->patch("/checks/{$this->check->id}/transfer", ['table_id' => $this->target->id])
            ->assertRedirect();

        $this->assertDatabaseHas('checks', [
            'id'       => $this->check->id,
            'table_id' => $this->target->id,
        ]);
    }

    #[Test]
    public function cannot_transfer_to_table_with_open_check(): void
    {
        Check::factory()->create([
            'table_id'       => $this->target->id,
            'waiter_user_id' => $this->waiter->id,
            'status'         => CheckStatus::Open->value,
        ]);

        $this->actingAs($this->waiter)
            ->patch("/checks/{$this->check->id}/transfer", ['table_id' => $this->target->id])
            ->assertSessionHasErrors('table_id');
    }

    #[Test]
    public function cannot_transfer_closed_check(): void
    {
        $this->check->forceFill(['status' => CheckStatus::Closed->value])->save();

        $this->actingAs($this->waiter)
            ->patch("/checks/{$this->check->id}/transfer", ['table_id' => $this->target->id])
            ->assertSessionHasErrors('check');
    }

    #[Test]
    public function show_page_includes_available_tables(): void
    {
        $this->actingAs($this->waiter)
            ->get("/checks/{$this->check->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('available_tables')
            );
    }
}
