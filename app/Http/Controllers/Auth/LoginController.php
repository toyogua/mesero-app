<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Pantalla principal: lista de meseros activos para tap + PIN.
     */
    public function show(): Response
    {
        $waiters = User::query()
            ->where('active', true)
            ->whereIn('role', [UserRole::Waiter->value, UserRole::Admin->value, UserRole::Cashier->value])
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->role->value,
                'initials' => $this->initials($u->name),
            ]);

        return Inertia::render('Auth/Login', [
            'waiters' => $waiters,
        ]);
    }

    /**
     * Login con PIN para meseros/cocina/cajero.
     */
    public function attemptPin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'pin' => 'required|string|min:4|max:8',
        ]);

        $user = User::findOrFail($validated['user_id']);

        if (! $user->active || ! $user->pin || ! Hash::check($validated['pin'], $user->pin)) {
            throw ValidationException::withMessages([
                'pin' => 'PIN incorrecto.',
            ]);
        }

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        return redirect()->intended($this->landingFor($user));
    }

    /**
     * Pantalla de admin (email + password).
     */
    public function showAdmin(): Response
    {
        return Inertia::render('Auth/AdminLogin');
    }

    public function attemptAdmin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($validated, remember: $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Credenciales inválidas.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->landingFor($request->user()));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function landingFor(User $user): string
    {
        return match ($user->role) {
            UserRole::Kitchen => '/kitchen',
            UserRole::Admin, UserRole::Waiter, UserRole::Cashier => '/floor',
        };
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $second = mb_substr($parts[1] ?? '', 0, 1);

        return mb_strtoupper($first.$second);
    }
}
