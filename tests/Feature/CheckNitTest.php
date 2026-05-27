<?php

namespace Tests\Feature;

use App\Enums\CheckItemStatus;
use App\Enums\CheckStatus;
use App\Enums\FelStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\FelInvoice;
use App\Models\KitchenStation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckNitTest extends TestCase
{
    use RefreshDatabase;

    private User $waiter;
    private Check $check;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

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
            'check_id'           => $this->check->id,
            'kitchen_station_id' => $station->id,
            'status'             => CheckItemStatus::Served->value,
            'served_at'          => now(),
        ]);
    }

    #[Test]
    public function closing_with_nit_creates_invoice_with_that_nit(): void
    {
        Queue::fake();
        config(['restaurant.fel.enabled' => true, 'restaurant.fel.adapter' => 'null']);

        $this->actingAs($this->waiter)
            ->post("/checks/{$this->check->id}/close", [
                'receptor_nit'  => '1234567-8',
                'receptor_name' => 'Empresa S.A.',
            ])
            ->assertRedirect('/floor');

        $invoice = FelInvoice::where('check_id', $this->check->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('1234567-8', $invoice->receptor_nit);
        $this->assertSame('Empresa S.A.', $invoice->receptor_name);
    }

    #[Test]
    public function closing_without_nit_defaults_to_cf(): void
    {
        Queue::fake();
        config(['restaurant.fel.enabled' => true, 'restaurant.fel.adapter' => 'null']);

        $this->actingAs($this->waiter)
            ->post("/checks/{$this->check->id}/close", [])
            ->assertRedirect('/floor');

        $invoice = FelInvoice::where('check_id', $this->check->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('CF', $invoice->receptor_nit);
        $this->assertSame('CONSUMIDOR FINAL', $invoice->receptor_name);
    }

    #[Test]
    public function closing_with_empty_nit_defaults_to_cf(): void
    {
        Queue::fake();
        config(['restaurant.fel.enabled' => true, 'restaurant.fel.adapter' => 'null']);

        $this->actingAs($this->waiter)
            ->post("/checks/{$this->check->id}/close", [
                'receptor_nit'  => '',
                'receptor_name' => '',
            ])
            ->assertRedirect('/floor');

        $invoice = FelInvoice::where('check_id', $this->check->id)->first();
        $this->assertSame('CF', $invoice->receptor_nit);
    }

    #[Test]
    public function nit_is_uppercased_on_save(): void
    {
        Queue::fake();
        config(['restaurant.fel.enabled' => true, 'restaurant.fel.adapter' => 'null']);

        $this->actingAs($this->waiter)
            ->post("/checks/{$this->check->id}/close", [
                'receptor_nit' => 'cf',
            ])
            ->assertRedirect('/floor');

        $invoice = FelInvoice::where('check_id', $this->check->id)->first();
        $this->assertSame('CF', $invoice->receptor_nit);
    }

    #[Test]
    public function show_page_passes_fel_enabled_flag(): void
    {
        config(['restaurant.fel.enabled' => true]);

        $this->actingAs($this->waiter)
            ->get("/checks/{$this->check->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('fel_enabled', true)
            );
    }
}
