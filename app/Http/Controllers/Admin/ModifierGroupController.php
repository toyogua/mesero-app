<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModifierGroupController extends Controller
{
    public function index(): Response
    {
        $groups = ModifierGroup::with('options')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'selection_type' => $g->selection_type,
                'required' => $g->required,
                'display_order' => $g->display_order,
                'options' => $g->options->map(fn ($o) => [
                    'id' => $o->id,
                    'name' => $o->name,
                    'price_delta' => (float) $o->price_delta,
                    'display_order' => $o->display_order,
                    'active' => $o->active,
                ]),
            ]);

        return Inertia::render('Admin/ModifierGroups/Index', [
            'groups' => $groups,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:80|unique:modifier_groups,name',
            'selection_type' => 'required|in:single,multi',
            'required' => 'boolean',
            'display_order' => 'integer|min:0',
        ]);

        ModifierGroup::create($data);

        return back()->with('success', 'Grupo creado.');
    }

    public function update(Request $request, ModifierGroup $modifierGroup): RedirectResponse
    {
        $data = $request->validate([
            'name' => "required|string|max:80|unique:modifier_groups,name,{$modifierGroup->id}",
            'selection_type' => 'required|in:single,multi',
            'required' => 'boolean',
            'display_order' => 'integer|min:0',
        ]);

        $modifierGroup->update($data);

        return back()->with('success', 'Grupo actualizado.');
    }

    public function destroy(ModifierGroup $modifierGroup): RedirectResponse
    {
        $modifierGroup->delete();

        return back()->with('success', 'Grupo eliminado.');
    }

    // ── Options ──────────────────────────────────────────────────────────────

    public function storeOption(Request $request, ModifierGroup $modifierGroup): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'price_delta' => 'required|numeric',
            'display_order' => 'integer|min:0',
        ]);

        $modifierGroup->options()->create($data);

        return back()->with('success', 'Opción creada.');
    }

    public function updateOption(Request $request, ModifierGroup $modifierGroup, ModifierOption $option): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'price_delta' => 'required|numeric',
            'display_order' => 'integer|min:0',
            'active' => 'boolean',
        ]);

        $option->update($data);

        return back()->with('success', 'Opción actualizada.');
    }

    public function destroyOption(ModifierGroup $modifierGroup, ModifierOption $option): RedirectResponse
    {
        $option->delete();

        return back()->with('success', 'Opción eliminada.');
    }
}
