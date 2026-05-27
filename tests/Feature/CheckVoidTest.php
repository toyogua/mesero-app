<?php

namespace Tests\Feature;

use App\Enums\CheckItemStatus;
use App\Enums\CheckStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\KitchenStation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckVoidTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $waiter;
    private Check $check;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->admin  = User::factory()->create(['role' => UserRole::Admin->value,  'active' => true]);
        $this->waiter = User::factory()->create(['role' => UserRole::Waiter->value, 'active' => true]);

        $area    = Area::factory()->create();
        $table   = Table::factory()->create(['area_id' => $area->id]);
        $station = KitchenStation::factory()->create();

        $this->check = Check::factory()->create([
            'table_id'       => $table->id,
            'waiter_user_id' => $this->waiter->id,
            'status'         => CheckStatus::Open->value,
        ]);

        CheckItem::factory()->create([
            'check_id'          => $this->check->id,
            'kitchen_station_id'=> $station->id,
            'status'            => CheckItemStatus::Ordered->value,
        ]);
    }

    #[Test]
    public function admin_can_void_open_check(): void
    {
        $this->actingAs($this->admin)
            ->post("/checks/{$this->check->id}/void")
            ->assertRedirect('/floor');

        $this->check->refresh();
        $this->assertSame(CheckStatus::Void, $this->check->status);
    }

    #[Test]
    public function void_cancels_all_non_final_items(): void
    {
        $station = KitchenStation::factory()->create();

        CheckItem::factory()->create([
            'check_id'          => $this->check->id,
            'kitchen_station_id'=> $station->id,
            'status'            => CheckItemStatus::Preparing->value,
        ]);
        CheckItem::factory()->create([
            'check_id'          => $this->check->id,
            'kitchen_station_id'=> $station->id,
            'status'            => CheckItemStatus::Served->value,
        ]);

        $this->actingAs($this->admin)
            ->post("/checks/{$this->check->id}/void")
            ->assertRedirect();

        $stillActive = $this->check->items()
            ->whereNotIn('status', [CheckItemStatus::Served->value, CheckItemStatus::Cancelled->value])
            ->count();

        $this->assertSame(0, $stillActive);
    }

    #[Test]
    public function waiter_cannot_void_check(): void
    {
        $this->actingAs($this->waiter)
            ->post("/checks/{$this->check->id}/void")
            ->assertForbidden();
    }

    #[Test]
    public function cannot_void_already_closed_check(): void
    {
        $this->check->forceFill(['status' => CheckStatus::Closed->value])->save();

        $this->actingAs($this->admin)
            ->post("/checks/{$this->check->id}/void")
            ->assertSessionHasErrors('check');
    }
}
