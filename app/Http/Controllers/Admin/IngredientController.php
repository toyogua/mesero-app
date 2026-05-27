<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\StockEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IngredientController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate(['search' => 'nullable|string|max:100']);

        $ingredients = Ingredient::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($i) => [
                'id'                => $i->id,
                'name'              => $i->name,
                'unit'              => $i->unit,
                'purchase_unit'     => $i->purchase_unit,
                'units_per_package' => $i->units_per_package !== null ? (float) $i->units_per_package : null,
                'quantity_on_hand'  => (float) $i->quantity_on_hand,
                'minimum_stock'     => (float) $i->minimum_stock,
                'low_stock'         => $i->isLowStock(),
                'active'            => $i->active,
            ]);

        $lowStockCount = Ingredient::where('active', true)
            ->whereColumn('quantity_on_hand', '<=', 'minimum_stock')
            ->count();

        return Inertia::render('Admin/Ingredients/Index', [
            'ingredients'    => $ingredients,
            'low_stock_count' => $lowStockCount,
            'filters'        => ['search' => $request->search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'              => 'required|string|max:120|unique:ingredients,name',
            'unit'              => 'required|in:lb,oz,kg,g,L,mL,unit,portion',
            'purchase_unit'     => 'nullable|string|max:60',
            'units_per_package' => 'nullable|numeric|min:0.0001',
            'quantity_on_hand'  => 'required|numeric|min:0',
            'minimum_stock'     => 'required|numeric|min:0',
        ]);

        Ingredient::create($data);

        return back()->with('success', 'Ingrediente creado.');
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $request->validate([
            'name'              => "required|string|max:120|unique:ingredients,name,{$ingredient->id}",
            'unit'              => 'required|in:lb,oz,kg,g,L,mL,unit,portion',
            'purchase_unit'     => 'nullable|string|max:60',
            'units_per_package' => 'nullable|numeric|min:0.0001',
            'quantity_on_hand'  => 'required|numeric|min:0',
            'minimum_stock'     => 'required|numeric|min:0',
            'active'            => 'boolean',
        ]);

        $ingredient->update($data);

        return back()->with('success', 'Ingrediente actualizado.');
    }

    public function restock(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $request->validate([
            'quantity'   => 'required|numeric|min:0.0001',
            'cost_price' => 'nullable|numeric|min:0',
            'notes'      => 'nullable|string|max:255',
        ]);

        StockEntry::create([
            'ingredient_id'   => $ingredient->id,
            'user_id'         => $request->user()->id,
            'quantity'        => $data['quantity'],
            'quantity_before' => $ingredient->quantity_on_hand,
            'cost_price'      => $data['cost_price'] ?? null,
            'notes'           => $data['notes'] ?? null,
        ]);

        $ingredient->increment('quantity_on_hand', $data['quantity']);

        if (! empty($data['cost_price'])) {
            $ingredient->update(['cost_price' => $data['cost_price']]);
        }

        return back()->with('success', "Stock de {$ingredient->name} actualizado.");
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        $ingredient->delete();

        return back()->with('success', 'Ingrediente eliminado.');
    }
}
