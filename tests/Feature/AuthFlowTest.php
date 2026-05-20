<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_page_lists_active_users_with_pins(): void
    {
        $waiter = User::factory()->create([
            'name' => 'María López',
            'role' => UserRole::Waiter->value,
            'active' => true,
        ]);
        User::factory()->create([
            'name' => 'Inactivo',
            'role' => UserRole::Waiter->value,
            'active' => false,
        ]);

        $this->get('/')->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Auth/Login')
                    ->has('waiters', 1)
                    ->where('waiters.0.name', $waiter->name)
            );
    }

    #[Test]
    public function pin_login_succeeds_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Waiter->value,
            'pin' => Hash::make('1234'),
            'active' => true,
        ]);

        $this->post('/login', ['user_id' => $user->id, 'pin' => '1234'])
            ->assertRedirect('/floor');

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function pin_login_fails_with_wrong_pin(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Waiter->value,
            'pin' => Hash::make('1234'),
            'active' => true,
        ]);

        $this->post('/login', ['user_id' => $user->id, 'pin' => '9999'])
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    #[Test]
    public function inactive_users_cannot_login(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Waiter->value,
            'pin' => Hash::make('1234'),
            'active' => false,
        ]);

        $this->post('/login', ['user_id' => $user->id, 'pin' => '1234'])
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    #[Test]
    public function kitchen_user_lands_on_kitchen_after_login(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Kitchen->value,
            'pin' => Hash::make('5555'),
            'active' => true,
        ]);

        $this->post('/login', ['user_id' => $user->id, 'pin' => '5555'])
            ->assertRedirect('/kitchen');
    }

    #[Test]
    public function admin_login_with_email_and_password(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('secret'),
            'role' => UserRole::Admin->value,
            'active' => true,
        ]);

        $this->post('/admin/login', [
            'email' => 'admin@example.test',
            'password' => 'secret',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($admin);
    }

    #[Test]
    public function protected_routes_redirect_guests(): void
    {
        $this->get('/floor')->assertRedirect('/');
        $this->get('/kitchen')->assertRedirect('/');
    }

    #[Test]
    public function logout_invalidates_session(): void
    {
        $user = User::factory()->create(['active' => true]);
        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
