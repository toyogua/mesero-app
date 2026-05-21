<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'role'   => ['nullable', Rule::in(array_column(UserRole::cases(), 'value'))],
        ]);

        $users = User::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->orderBy('role')->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($u) => [
                'id'      => $u->id,
                'name'    => $u->name,
                'email'   => $u->email,
                'role'    => $u->role->value,
                'active'  => $u->active,
                'has_pin' => $u->getAttributes()['pin'] !== null,
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users'   => $users,
            'roles'   => array_column(UserRole::cases(), 'value'),
            'filters' => [
                'search' => $request->search,
                'role'   => $request->role,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'role'     => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
            'pin'      => 'required_if:role,waiter,kitchen,cashier|nullable|digits:4',
            'email'    => 'required_if:role,admin|nullable|email|unique:users,email',
            'password' => 'required_if:role,admin|nullable|min:8',
        ]);

        if ($request->filled('pin')) {
            $this->assertPinUnique($request->pin);
        }

        $data = [
            'name'   => $request->name,
            'role'   => $request->role,
            'active' => true,
        ];

        if ($request->filled('pin')) {
            $data['pin'] = $request->pin;
        }

        if ($request->filled('email')) {
            $data['email']    = $request->email;
            $data['password'] = $request->password;
        } else {
            // PIN-only users still need an email (column is NOT NULL).
            $slug = preg_replace('/[^a-z0-9]+/', '.', strtolower($request->name));
            $data['email']    = trim($slug, '.').'.'.substr(uniqid(), -4).'@local';
            $data['password'] = bin2hex(random_bytes(16));
        }

        User::create($data);

        return back()->with('success', 'Usuario creado.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'name'   => 'required|string|max:100',
            'active' => 'boolean',
            'pin'    => 'nullable|digits:4',
        ]);

        $data = $request->only('name', 'active');

        if ($request->filled('pin')) {
            $this->assertPinUnique($request->pin, $user->id);
            $data['pin'] = $request->pin;
        }

        if ($request->filled('password')) {
            $request->validate(['password' => 'min:8']);
            $data['password'] = $request->password;
        }

        $user->update($data);

        return back()->with('success', 'Usuario actualizado.');
    }

    private function assertPinUnique(string $pin, ?string $ignoreId = null): void
    {
        $users = User::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->whereNotNull('pin')
            ->get(['id', 'pin']);

        foreach ($users as $u) {
            if (Hash::check($pin, $u->getAttributes()['pin'])) {
                throw ValidationException::withMessages(['pin' => 'Ese PIN ya está en uso.']);
            }
        }
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages(['user' => 'No podés desactivar tu propio usuario.']);
        }

        $user->update(['active' => false]);

        return back()->with('success', 'Usuario desactivado.');
    }
}
