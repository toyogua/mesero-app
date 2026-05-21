<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\RecipeItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    public function show(MenuItem $menuItem): Response
    {
        $recipe = $menuItem->recipeItems()
            ->with('ingredient')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'ingredient_id' => $r->ingredient_id,
                'ingredient_name' => $r->ingredient->name,
                'unit' => $r->ingredient->unit,
                'quantity_used' => (float) $r->quantity_used,
            ]);

        $ingredients = Ingredient::active()->orderBy('name')
            ->get(['id', 'name', 'unit', 'quantity_on_hand']);

        return Inertia::render('Admin/Recipes/Show', [
            'menuItem' => [
                'id' => $menuItem->id,
                'name' => $menuItem->name,
                'category' => $menuItem->category,
            ],
            'recipe' => $recipe,
            'ingredients' => $ingredients,
        ]);
    }

    public function upsert(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $data = $request->validate([
            'lines' => 'required|array',
            'lines.*.ingredient_id' => 'required|string|exists:ingredients,id',
            'lines.*.quantity_used' => 'required|numeric|min:0.0001',
        ]);

        $menuItem->recipeItems()->delete();

        foreach ($data['lines'] as $line) {
            RecipeItem::create([
                'menu_item_id' => $menuItem->id,
                'ingredient_id' => $line['ingredient_id'],
                'quantity_used' => $line['quantity_used'],
            ]);
        }

        return back()->with('success', 'Receta guardada.');
    }
}
