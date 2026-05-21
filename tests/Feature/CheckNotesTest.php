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

class CheckNotesTest extends TestCase
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

    private function openCheck(): Check
    {
        $table = Table::factory()->for(Area::factory())->create();

        return Check::factory()->create([
            'table_id'       => $table->id,
            'waiter_user_id' => $this->waiter->id,
            'status'         => CheckStatus::Open,
            'opened_at'      => now(),
        ]);
    }

    #[Test]
    public function notes_can_be_saved_on_open_check(): void
    {
        $check = $this->openCheck();

        $this->actingAs($this->waiter)
            ->patch("/checks/{$check->id}/notes", ['notes' => 'Alergia al maní'])
            ->assertRedirect();

        $check->refresh();
        $this->assertSame('Alergia al maní', $check->notes);
    }

    #[Test]
    public function notes_can_be_cleared(): void
    {
        $check = $this->openCheck();
        $check->update(['notes' => 'Algo']);

        $this->actingAs($this->waiter)
            ->patch("/checks/{$check->id}/notes", ['notes' => null])
            ->assertRedirect();

        $check->refresh();
        $this->assertNull($check->notes);
    }

    #[Test]
    public function notes_max_500_characters(): void
    {
        $check = $this->openCheck();

        $this->actingAs($this->waiter)
            ->patch("/checks/{$check->id}/notes", ['notes' => str_repeat('a', 501)])
            ->assertSessionHasErrors('notes');
    }

    #[Test]
    public function notes_cannot_be_set_on_closed_check(): void
    {
        $table = Table::factory()->for(Area::factory())->create();
        $check = Check::factory()->create([
            'table_id'       => $table->id,
            'waiter_user_id' => $this->waiter->id,
            'status'         => CheckStatus::Closed,
            'opened_at'      => now()->subHour(),
            'closed_at'      => now(),
        ]);

        $this->actingAs($this->waiter)
            ->patch("/checks/{$check->id}/notes", ['notes' => 'Tarde'])
            ->assertSessionHasErrors('check');
    }

    #[Test]
    public function notes_are_returned_in_check_show(): void
    {
        $check = $this->openCheck();
        $check->update(['notes' => 'Mesa VIP']);

        $this->actingAs($this->waiter)
            ->get("/checks/{$check->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('check.notes', 'Mesa VIP'));
    }
}
