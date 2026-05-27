<?php

namespace Tests\Feature;

use App\Enums\CheckItemStatus;
use App\Enums\CheckStatus;
use App\Enums\FelStatus;
use App\Enums\UserRole;
use App\Jobs\IssueFelInvoice;
use App\Models\Area;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\FelInvoice;
use App\Models\KitchenStation;
use App\Models\MenuItem;
use App\Models\Table;
use App\Models\User;
use App\Services\Fel\FelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FelTest extends TestCase
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
            'status' => CheckStatus::Open->value,
        ]);
    }

    #[Test]
    public function fel_service_issues_invoice_with_null_adapter(): void
    {
        config(['restaurant.fel.enabled' => true, 'restaurant.fel.adapter' => 'null']);

        $invoice = FelInvoice::create([
            'check_id'      => $this->check->id,
            'status'        => FelStatus::Pending,
            'receptor_nit'  => 'CF',
            'receptor_name' => 'CONSUMIDOR FINAL',
        ]);

        app(FelService::class)->issue($invoice);

        $invoice->refresh();
        $this->assertSame(FelStatus::Issued, $invoice->status);
        $this->assertNotNull($invoice->uuid);
        $this->assertNotNull($invoice->issued_at);
    }

    #[Test]
    public function closing_check_with_fel_enabled_dispatches_job(): void
    {
        Queue::fake();
        config(['restaurant.fel.enabled' => true, 'restaurant.fel.adapter' => 'null']);

        $item = CheckItem::factory()->create([
            'check_id' => $this->check->id,
            'kitchen_station_id' => $this->station->id,
            'status' => CheckItemStatus::Served->value,
            'served_at' => now(),
        ]);

        $this->actingAs($this->waiter)
            ->post("/checks/{$this->check->id}/close")
            ->assertRedirect('/floor');

        Queue::assertPushed(IssueFelInvoice::class);
        $this->assertSame(1, FelInvoice::count());
    }

    #[Test]
    public function closing_check_with_fel_disabled_does_not_create_invoice(): void
    {
        config(['restaurant.fel.enabled' => false]);

        CheckItem::factory()->create([
            'check_id' => $this->check->id,
            'kitchen_station_id' => $this->station->id,
            'status' => CheckItemStatus::Served->value,
            'served_at' => now(),
        ]);

        $this->actingAs($this->waiter)
            ->post("/checks/{$this->check->id}/close")
            ->assertRedirect('/floor');

        $this->assertSame(0, FelInvoice::count());
    }

    #[Test]
    public function dte_xml_contains_required_fields(): void
    {
        config(['restaurant.fel.emisor_nit' => '1234567-8', 'restaurant.fel.emisor_name' => 'Restaurante Test S.A.', 'restaurant.fel.emisor_commercial_name' => 'El Rincón', 'restaurant.fel.emisor_address' => 'Zona 10', 'restaurant.fel.establishment_code' => '1']);

        $menu = MenuItem::factory()->create(['kitchen_station_id' => $this->station->id, 'price' => 50.00]);
        $item = CheckItem::factory()->create([
            'check_id' => $this->check->id,
            'menu_item_id' => $menu->id,
            'kitchen_station_id' => $this->station->id,
            'name_snapshot' => 'Burger',
            'price_snapshot' => 50.00,
            'quantity' => 2,
            'status' => CheckItemStatus::Served->value,
        ]);
        $this->check->recalculate();

        $invoice = FelInvoice::make([
            'receptor_nit' => 'CF',
            'receptor_name' => 'CONSUMIDOR FINAL',
        ]);
        $invoice->check_id = $this->check->id;

        $this->check->load(['items.modifiers']);
        $builder = app(\App\Services\Fel\DteXmlBuilder::class);
        $xml = $builder->build($this->check, $invoice);

        $this->assertStringContainsString('GTDocumento', $xml);
        $this->assertStringContainsString('1234567-8', $xml);
        $this->assertStringContainsString('Burger', $xml);
        $this->assertStringContainsString('<dte:Cantidad>2</dte:Cantidad>', $xml);
        $this->assertStringContainsString('GranTotal', $xml);
        $this->assertStringContainsString('CONSUMIDOR FINAL', $xml);
    }

    #[Test]
    public function failed_invoice_can_be_retried_via_admin(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);
        $invoice = FelInvoice::factory()->failed()->create(['check_id' => $this->check->id]);

        $this->actingAs($admin)
            ->post("/admin/fel-invoices/{$invoice->id}/retry")
            ->assertRedirect();

        Queue::assertPushed(IssueFelInvoice::class);
    }

    #[Test]
    public function issued_invoice_cannot_be_retried(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);
        $invoice = FelInvoice::factory()->issued()->create(['check_id' => $this->check->id]);

        $this->actingAs($admin)
            ->post("/admin/fel-invoices/{$invoice->id}/retry")
            ->assertSessionHasErrors('invoice');
    }

    #[Test]
    public function admin_can_view_fel_invoices_list(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);
        FelInvoice::factory()->issued()->create(['check_id' => $this->check->id]);

        $this->actingAs($admin)
            ->get('/admin/fel-invoices')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/FelInvoices/Index')
                ->has('invoices.data', 1)
            );
    }

    #[Test]
    public function issued_invoice_can_be_cancelled_with_reason(): void
    {
        config(['restaurant.fel.enabled' => true, 'restaurant.fel.adapter' => 'null']);

        $admin   = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);
        $invoice = FelInvoice::factory()->issued()->create(['check_id' => $this->check->id]);

        $this->actingAs($admin)
            ->post("/admin/fel-invoices/{$invoice->id}/cancel", [
                'reason' => 'Error en NIT del receptor',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(FelStatus::Cancelled, $invoice->status);
        $this->assertSame('Error en NIT del receptor', $invoice->cancel_reason);
        $this->assertNotNull($invoice->cancelled_at);
    }

    #[Test]
    public function cancel_requires_reason(): void
    {
        $admin   = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);
        $invoice = FelInvoice::factory()->issued()->create(['check_id' => $this->check->id]);

        $this->actingAs($admin)
            ->post("/admin/fel-invoices/{$invoice->id}/cancel", ['reason' => ''])
            ->assertSessionHasErrors('reason');
    }

    #[Test]
    public function pending_invoice_cannot_be_cancelled(): void
    {
        $admin   = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);
        $invoice = FelInvoice::factory()->create(['check_id' => $this->check->id]);

        $this->actingAs($admin)
            ->post("/admin/fel-invoices/{$invoice->id}/cancel", [
                'reason' => 'No debería funcionar',
            ])
            ->assertSessionHasErrors('invoice');
    }

    #[Test]
    public function already_cancelled_invoice_cannot_be_cancelled_again(): void
    {
        $admin   = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true]);
        $invoice = FelInvoice::factory()->cancelled()->create(['check_id' => $this->check->id]);

        $this->actingAs($admin)
            ->post("/admin/fel-invoices/{$invoice->id}/cancel", [
                'reason' => 'Segundo intento',
            ])
            ->assertSessionHasErrors('invoice');
    }
}
