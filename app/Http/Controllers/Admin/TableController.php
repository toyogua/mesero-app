<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Table;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TableController extends Controller
{
    public function index(): Response
    {
        $areas = Area::with(['tables' => fn ($q) => $q->orderBy('name')])
            ->orderBy('display_order')
            ->get()
            ->map(fn ($a) => [
                'id'     => $a->id,
                'name'   => $a->name,
                'active' => $a->active,
                'tables' => $a->tables->map(fn ($t) => [
                    'id'       => $t->id,
                    'name'     => $t->name,
                    'capacity' => $t->capacity,
                    'active'   => $t->active,
                    'occupied' => $t->isOccupied(),
                ]),
            ]);

        return Inertia::render('Admin/Tables/Index', [
            'areas' => $areas,
            'area_list' => Area::active()->orderBy('display_order')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'area_id'  => 'required|exists:areas,id',
            'name'     => 'required|string|max:60',
            'capacity' => 'integer|min:1|max:50',
        ]);

        Table::create([
            'area_id'  => $request->area_id,
            'name'     => $request->name,
            'capacity' => $request->input('capacity', 4),
        ]);

        return back()->with('success', 'Mesa creada.');
    }

    public function update(Request $request, Table $table): RedirectResponse
    {
        $request->validate([
            'name'     => 'required|string|max:60',
            'capacity' => 'integer|min:1|max:50',
            'active'   => 'boolean',
        ]);

        $table->update($request->only('name', 'capacity', 'active'));

        return back()->with('success', 'Mesa actualizada.');
    }

    public function destroy(Table $table): RedirectResponse
    {
        if ($table->isOccupied()) {
            throw ValidationException::withMessages(['table' => 'La mesa tiene una comanda abierta.']);
        }

        $table->update(['active' => false]);

        return back()->with('success', 'Mesa desactivada.');
    }
}
