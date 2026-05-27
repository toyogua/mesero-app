<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\ModifierOptionIngredient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ModifierGroupController extends Controller
{
    public function index(): Response
    {
        $groups = ModifierGroup::with('options.ingredientLines.ingredient')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($g) => [
                'id'             => $g->id,
                'name'           => $g->name,
                'min_selections' => $g->min_selections,
                'max_selections' => $g->max_selections,
                'display_order'  => $g->display_order,
                'options'        => $g->options->map(fn ($o) => [
                    'id'               => $o->id,
                    'name'             => $o->name,
                    'price_delta'      => (float) $o->price_delta,
                    'display_order'    => $o->display_order,
                    'active'           => $o->active,
                    'ingredient_lines' => $o->ingredientLines->map(fn ($l) => [
                        'id'              => $l->id,
                        'ingredient_id'   => $l->ingredient_id,
                        'ingredient_name' => $l->ingredient->name,
                        'unit'            => $l->ingredient->unit,
                        'quantity_used'   => (float) $l->quantity_used,
                    ]),
                ]),
            ]);

        $ingredients = Ingredient::active()->orderBy('name')->get(['id', 'name', 'unit']);

        return Inertia::render('Admin/ModifierGroups/Index', [
            'groups'      => $groups,
            'ingredients' => $ingredients,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:80|unique:modifier_groups,name',
            'min_selections' => 'required|integer|min:0',
            'max_selections' => 'nullable|integer|min:1',
            'display_order'  => 'integer|min:0',
        ]);

        ModifierGroup::create($data);

        Cache::forget('menu_for_check');
        return back()->with('success', 'Grupo creado.');
    }

    public function update(Request $request, ModifierGroup $modifierGroup): RedirectResponse
    {
        $data = $request->validate([
            'name'           => "required|string|max:80|unique:modifier_groups,name,{$modifierGroup->id}",
            'min_selections' => 'required|integer|min:0',
            'max_selections' => 'nullable|integer|min:1',
            'display_order'  => 'integer|min:0',
        ]);

        $modifierGroup->update($data);

        Cache::forget('menu_for_check');
        return back()->with('success', 'Grupo actualizado.');
    }

    public function destroy(ModifierGroup $modifierGroup): RedirectResponse
    {
        $modifierGroup->delete();

        Cache::forget('menu_for_check');
        return back()->with('success', 'Grupo eliminado.');
    }

    // ── Options ──────────────────────────────────────────────────────────────

    public function storeOption(Request $request, ModifierGroup $modifierGroup): RedirectResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:80',
            'price_delta'   => 'required|numeric',
            'display_order' => 'integer|min:0',
        ]);

        $modifierGroup->options()->create($data);

        Cache::forget('menu_for_check');
        return back()->with('success', 'Opción creada.');
    }

    public function updateOption(Request $request, ModifierGroup $modifierGroup, ModifierOption $option): RedirectResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:80',
            'price_delta'   => 'required|numeric',
            'display_order' => 'integer|min:0',
            'active'        => 'boolean',
        ]);

        $option->update($data);

        Cache::forget('menu_for_check');
        return back()->with('success', 'Opción actualizada.');
    }

    public function destroyOption(ModifierGroup $modifierGroup, ModifierOption $option): RedirectResponse
    {
        $option->delete();

        Cache::forget('menu_for_check');
        return back()->with('success', 'Opción eliminada.');
    }

    // ── Option ingredient lines ───────────────────────────────────────────────

    public function storeOptionIngredient(
        Request $request,
        ModifierGroup $modifierGroup,
        ModifierOption $option,
    ): RedirectResponse {
        $data = $request->validate([
            'ingredient_id' => 'required|string|exists:ingredients,id',
            'quantity_used' => 'required|numeric|min:0.0001',
        ]);

        $option->ingredientLines()->updateOrCreate(
            ['ingredient_id' => $data['ingredient_id']],
            ['quantity_used' => $data['quantity_used']],
        );

        Cache::forget('menu_for_check');
        return back()->with('success', 'Ingrediente agregado a la opción.');
    }

    public function destroyOptionIngredient(
        ModifierGroup $modifierGroup,
        ModifierOption $option,
        ModifierOptionIngredient $line,
    ): RedirectResponse {
        $line->delete();

        Cache::forget('menu_for_check');
        return back()->with('success', 'Ingrediente eliminado de la opción.');
    }
}
