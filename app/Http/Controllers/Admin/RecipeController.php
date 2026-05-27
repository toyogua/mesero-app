<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\RecipeItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        $assignedGroupIds = $menuItem->modifierGroups()->pluck('modifier_groups.id')->all();
        $allGroups = ModifierGroup::orderBy('name')->get(['id', 'name', 'min_selections', 'max_selections']);

        return Inertia::render('Admin/Recipes/Show', [
            'menuItem' => [
                'id' => $menuItem->id,
                'name' => $menuItem->name,
                'category' => $menuItem->category,
            ],
            'recipe' => $recipe,
            'ingredients' => $ingredients,
            'modifier_groups' => $allGroups->map(fn ($g) => [
                'id'             => $g->id,
                'name'           => $g->name,
                'min_selections' => $g->min_selections,
                'max_selections' => $g->max_selections,
                'assigned'       => in_array($g->id, $assignedGroupIds, true),
            ]),
        ]);
    }

    public function syncModifiers(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $data = $request->validate([
            'group_ids'   => 'present|array',
            'group_ids.*' => 'string|exists:modifier_groups,id',
        ]);

        $sync = collect($data['group_ids'])->mapWithKeys(fn ($id, $i) => [$id => ['display_order' => $i]]);

        $menuItem->modifierGroups()->sync($sync);

        Cache::forget('menu_for_check');

        return redirect()->route('admin.recipes.show', $menuItem)->with('success', 'Modificadores asignados.');
    }

    public function upsert(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $data = $request->validate([
            'lines'                  => 'required|array',
            'lines.*.ingredient_id'  => 'required|string|exists:ingredients,id',
            'lines.*.quantity_used'  => [
                'required', 'numeric', 'min:0.0001',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    preg_match('/lines\.(\d+)\./', $attribute, $m);
                    $idx        = $m[1] ?? null;
                    $ingredient = $idx !== null
                        ? Ingredient::find($request->input("lines.{$idx}.ingredient_id"))
                        : null;

                    if (! $ingredient) return;

                    $thresholds = ['lb' => 20, 'oz' => 64, 'kg' => 10, 'L' => 10, 'g' => 3000, 'mL' => 3000, 'unit' => 50, 'portion' => 20];
                    $max        = $thresholds[$ingredient->unit] ?? null;

                    if ($max && $value > $max) {
                        $fail("La cantidad {$value} {$ingredient->unit} para \"{$ingredient->name}\" excede el máximo razonable por porción ({$max} {$ingredient->unit}). Verificá que no confundiste unidades.");
                    }

                    if ($ingredient->unit === 'unit' && floor($value) != $value) {
                        $fail("El ingrediente \"{$ingredient->name}\" usa unidades enteras. Usá 1, 2, 3… no decimales.");
                    }
                },
            ],
        ]);

        $menuItem->recipeItems()->delete();

        foreach ($data['lines'] as $line) {
            RecipeItem::create([
                'menu_item_id' => $menuItem->id,
                'ingredient_id' => $line['ingredient_id'],
                'quantity_used' => $line['quantity_used'],
            ]);
        }

        return redirect()->route('admin.recipes.show', $menuItem)->with('success', 'Receta guardada.');
    }
}
