<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    public function index(): Response
    {
        $areas = Area::withCount('tables')
            ->orderBy('display_order')
            ->get()
            ->map(fn ($a) => [
                'id'            => $a->id,
                'name'          => $a->name,
                'display_order' => $a->display_order,
                'active'        => $a->active,
                'tables_count'  => $a->tables_count,
            ]);

        return Inertia::render('Admin/Areas/Index', ['areas' => $areas]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'          => 'required|string|max:80|unique:areas,name',
            'display_order' => 'integer|min:0',
        ]);

        Area::create([
            'name'          => $request->name,
            'display_order' => $request->input('display_order', 0),
        ]);

        return back()->with('success', 'Área creada.');
    }

    public function update(Request $request, Area $area): RedirectResponse
    {
        $request->validate([
            'name'          => 'required|string|max:80|unique:areas,name,'.$area->id,
            'display_order' => 'integer|min:0',
            'active'        => 'boolean',
        ]);

        $area->update($request->only('name', 'display_order', 'active'));

        return back()->with('success', 'Área actualizada.');
    }

    public function destroy(Area $area): RedirectResponse
    {
        if ($area->tables()->whereHas('openCheck')->exists()) {
            throw ValidationException::withMessages(['area' => 'El área tiene mesas con comandas abiertas.']);
        }

        $area->update(['active' => false]);

        return back()->with('success', 'Área desactivada.');
    }
}
