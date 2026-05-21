<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KitchenStation;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuItemController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search'   => 'nullable|string|max:100',
            'category' => 'nullable|in:entradas,platos_fuertes,bebidas,postres,otros',
        ]);

        $items = MenuItem::query()
            ->with('kitchenStation:id,name')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($i) => [
                'id'                 => $i->id,
                'name'               => $i->name,
                'description'        => $i->description,
                'price'              => (float) $i->price,
                'category'           => $i->category,
                'sku'                => $i->sku,
                'active'             => $i->active,
                'station_name'       => $i->kitchenStation?->name,
                'kitchen_station_id' => $i->kitchen_station_id,
            ]);

        return Inertia::render('Admin/MenuItems/Index', [
            'items'    => $items,
            'stations' => KitchenStation::orderBy('display_order')->get(['id', 'name']),
            'filters'  => [
                'search'   => $request->search,
                'category' => $request->category,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:120',
            'description'        => 'nullable|string|max:500',
            'price'              => 'required|numeric|min:0',
            'category'           => 'required|in:entradas,platos_fuertes,bebidas,postres,otros',
            'sku'                => 'nullable|string|max:60',
            'kitchen_station_id' => 'required|exists:kitchen_stations,id',
            'active'             => 'boolean',
        ]);

        MenuItem::create($data);

        return back()->with('success', 'Ítem creado.');
    }

    public function update(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:120',
            'description'        => 'nullable|string|max:500',
            'price'              => 'required|numeric|min:0',
            'category'           => 'required|in:entradas,platos_fuertes,bebidas,postres,otros',
            'sku'                => 'nullable|string|max:60',
            'kitchen_station_id' => 'required|exists:kitchen_stations,id',
            'active'             => 'boolean',
        ]);

        $menuItem->update($data);

        return back()->with('success', 'Ítem actualizado.');
    }

    public function destroy(MenuItem $menuItem): RedirectResponse
    {
        $menuItem->update(['active' => false]);

        return back()->with('success', 'Ítem desactivado.');
    }
}
