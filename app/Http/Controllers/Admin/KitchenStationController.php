<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KitchenStation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KitchenStationController extends Controller
{
    public function index(): Response
    {
        $stations = KitchenStation::withCount('menuItems')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => [
                'id'              => $s->id,
                'name'            => $s->name,
                'code'            => $s->code,
                'display_order'   => $s->display_order,
                'active'          => $s->active,
                'menu_items_count' => $s->menu_items_count,
            ]);

        return Inertia::render('Admin/KitchenStations/Index', ['stations' => $stations]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:80',
            'code'          => 'nullable|string|max:60|alpha_dash|unique:kitchen_stations,code',
            'display_order' => 'integer|min:0',
        ]);

        $code = $data['code'] ?? $this->uniqueCode($request->name);

        KitchenStation::create([
            'name'          => $data['name'],
            'code'          => $code,
            'display_order' => $data['display_order'] ?? 0,
        ]);

        return back()->with('success', 'Estación creada.');
    }

    public function update(Request $request, KitchenStation $kitchenStation): RedirectResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:80',
            'code'          => 'nullable|string|max:60|alpha_dash|unique:kitchen_stations,code,'.$kitchenStation->id,
            'display_order' => 'integer|min:0',
            'active'        => 'boolean',
        ]);

        $kitchenStation->update([
            'name'          => $data['name'],
            'code'          => $data['code'] ?? $kitchenStation->code,
            'display_order' => $data['display_order'] ?? $kitchenStation->display_order,
            'active'        => $data['active'] ?? $kitchenStation->active,
        ]);

        return back()->with('success', 'Estación actualizada.');
    }

    public function destroy(KitchenStation $kitchenStation): RedirectResponse
    {
        $inProgress = $kitchenStation->checkItems()
            ->whereNotIn('status', ['served', 'cancelled'])
            ->exists();

        if ($inProgress) {
            throw ValidationException::withMessages([
                'station' => 'La estación tiene ítems en preparación activos.',
            ]);
        }

        $kitchenStation->update(['active' => false]);

        return back()->with('success', 'Estación desactivada.');
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name);
        $code = $base;
        $i    = 2;

        while (KitchenStation::where('code', $code)->exists()) {
            $code = "{$base}_{$i}";
            $i++;
        }

        return $code;
    }
}
