<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLogTest extends TestCase
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
    public function admin_can_view_audit_log_page(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/audit-logs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/AuditLogs/Index')
                ->has('logs')
                ->has('users')
                ->has('entities')
                ->has('filters')
            );
    }

    #[Test]
    public function ingredient_create_generates_audit_entry(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/ingredients', [
                'name'             => 'Zanahoria',
                'unit'             => 'kg',
                'quantity_on_hand' => 10,
                'minimum_stock'    => 2,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action'          => 'created',
            'auditable_type'  => \App\Models\Ingredient::class,
            'auditable_label' => 'Zanahoria',
        ]);
    }

    #[Test]
    public function ingredient_update_logs_changed_fields(): void
    {
        $ingredient = Ingredient::factory()->create(['name' => 'Yuca', 'quantity_on_hand' => 5]);

        $this->actingAs($this->admin)
            ->patch("/admin/ingredients/{$ingredient->id}", [
                'name'             => 'Yuca blanca',
                'unit'             => $ingredient->unit,
                'quantity_on_hand' => 10,
                'minimum_stock'    => $ingredient->minimum_stock,
            ])
            ->assertRedirect();

        $log = AuditLog::where('action', 'updated')
            ->where('auditable_id', $ingredient->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayHasKey('name', $log->new_values);
        $this->assertSame('Yuca blanca', $log->new_values['name']);
    }

    #[Test]
    public function password_and_pin_are_never_logged(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/users', [
                'name' => 'Test Mesero',
                'role' => 'waiter',
                'pin'  => '9988',
            ])
            ->assertRedirect();

        $log = AuditLog::where('action', 'created')
            ->where('auditable_type', User::class)
            ->where('auditable_label', 'Test Mesero')
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
        $this->assertArrayNotHasKey('pin', $log->new_values ?? []);
    }

    #[Test]
    public function filter_by_entity_type(): void
    {
        // One ingredient event, one user event
        Ingredient::factory()->create();
        User::factory()->create();

        $this->actingAs($this->admin)
            ->get('/admin/audit-logs?entity=Ingredient&from='.now()->subDay()->toDateString().'&to='.now()->addDay()->toDateString())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('logs.data.0.entity', 'Ingredient')
            );
    }

    #[Test]
    public function non_admin_is_forbidden(): void
    {
        $this->actingAs($this->waiter)
            ->get('/admin/audit-logs')
            ->assertForbidden();
    }
}
