<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IngredientController extends Controller
{
    public function index(): Response
    {
        $ingredients = Ingredient::orderBy('name')
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->unit,
                'quantity_on_hand' => (float) $i->quantity_on_hand,
                'minimum_stock' => (float) $i->minimum_stock,
                'low_stock' => $i->isLowStock(),
                'active' => $i->active,
            ]);

        return Inertia::render('Admin/Ingredients/Index', [
            'ingredients' => $ingredients,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120|unique:ingredients,name',
            'unit' => 'required|in:unit,kg,g,L,mL,portion',
            'quantity_on_hand' => 'required|numeric|min:0',
            'minimum_stock' => 'required|numeric|min:0',
        ]);

        Ingredient::create($data);

        return back()->with('success', 'Ingrediente creado.');
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $request->validate([
            'name' => "required|string|max:120|unique:ingredients,name,{$ingredient->id}",
            'unit' => 'required|in:unit,kg,g,L,mL,portion',
            'quantity_on_hand' => 'required|numeric|min:0',
            'minimum_stock' => 'required|numeric|min:0',
            'active' => 'boolean',
        ]);

        $ingredient->update($data);

        return back()->with('success', 'Ingrediente actualizado.');
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        $ingredient->delete();

        return back()->with('success', 'Ingrediente eliminado.');
    }
}
