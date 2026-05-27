<?php

namespace App\Http\Controllers;

use App\Enums\CheckItemStatus;
use App\Events\CheckUpdated;
use App\Events\KitchenQueueChanged;
use App\Events\StockAlert;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\CheckItemModifier;
use App\Models\MenuItem;
use App\Models\ModifierOption;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckItemController extends Controller
{
    /**
     * Agregar uno o varios items en estado draft a la comanda.
     */
    public function store(Request $request, Check $check): RedirectResponse
    {
        $this->assertMutable($check);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|string|exists:menu_items,id',
            'items.*.quantity' => 'nullable|integer|min:1|max:50',
            'items.*.notes' => 'nullable|string|max:255',
            'items.*.modifiers' => 'nullable|array',
            'items.*.modifiers.*' => 'string|exists:modifier_options,id',
        ]);

        DB::transaction(function () use ($check, $validated) {
            foreach ($validated['items'] as $row) {
                $menuItem = MenuItem::with('modifierGroups.options')->findOrFail($row['menu_item_id']);
                $selectedIds = $row['modifiers'] ?? [];

                $this->assertModifiersValid($menuItem, $selectedIds);

                $item = CheckItem::create([
                    'check_id' => $check->id,
                    'menu_item_id' => $menuItem->id,
                    'kitchen_station_id' => $menuItem->kitchen_station_id,
                    'name_snapshot' => $menuItem->name,
                    'price_snapshot' => $menuItem->price,
                    'quantity' => $row['quantity'] ?? 1,
                    'notes' => $row['notes'] ?? null,
                    'status' => CheckItemStatus::Draft->value,
                ]);

                foreach ($selectedIds as $optionId) {
                    $option = ModifierOption::find($optionId);
                    CheckItemModifier::create([
                        'check_item_id' => $item->id,
                        'modifier_option_id' => $option->id,
                        'name_snapshot' => $option->name,
                        'price_snapshot' => $option->price_delta,
                    ]);
                }
            }
            $check->recalculate();
        });

        broadcast(new CheckUpdated($check, 'item_added'))->toOthers();

        return back()->with('success', count($validated['items']).' item(s) agregados');
    }

    /**
     * Actualizar cantidad/notas de un item en draft.
     */
    public function update(Request $request, CheckItem $item): RedirectResponse
    {
        $this->assertMutable($item->check);

        if ($item->status !== CheckItemStatus::Draft) {
            throw ValidationException::withMessages([
                'item' => 'Solo se pueden editar items en borrador.',
            ]);
        }

        $validated = $request->validate([
            'quantity' => 'nullable|integer|min:1|max:50',
            'notes' => 'nullable|string|max:255',
        ]);

        $item->update(array_filter($validated, fn ($v) => $v !== null));
        $item->check->recalculate();

        broadcast(new CheckUpdated($item->check, 'item_updated'))->toOthers();

        return back();
    }

    /**
     * Eliminar item en draft, o cancelar item en ordered.
     */
    public function destroy(CheckItem $item): RedirectResponse
    {
        $this->assertMutable($item->check);

        if ($item->status === CheckItemStatus::Draft) {
            $check = $item->check;
            $item->delete();
            $check->recalculate();
            broadcast(new CheckUpdated($check, 'item_removed'))->toOthers();

            return back()->with('success', 'Item eliminado');
        }

        if ($item->status === CheckItemStatus::Ordered) {
            $stationCode = $item->kitchenStation?->code;
            $item->transitionTo(CheckItemStatus::Cancelled);
            $item->check->recalculate();

            if ($stationCode) {
                broadcast(new KitchenQueueChanged([$stationCode], 'item_cancelled'))->toOthers();
            }
            broadcast(new CheckUpdated($item->check, 'item_cancelled'))->toOthers();

            return back()->with('success', 'Item cancelado');
        }

        throw ValidationException::withMessages([
            'item' => 'No se puede cancelar un item que ya está en preparación.',
        ]);
    }

    /**
     * Cocina: tomar item (ordered → preparing).
     */
    public function take(CheckItem $item): RedirectResponse
    {
        return $this->transition($item, CheckItemStatus::Preparing, 'tomado');
    }

    /**
     * Cocina: marcar listo (preparing → ready).
     */
    public function ready(CheckItem $item): RedirectResponse
    {
        return $this->transition($item, CheckItemStatus::Ready, 'listo');
    }

    /**
     * Mesero: marcar servido (ready → served).
     */
    public function served(CheckItem $item, InventoryService $inventory): RedirectResponse
    {
        return $this->transition($item, CheckItemStatus::Served, 'servido', $inventory);
    }

    /**
     * Bulk: transicionar todos los ítems elegibles de una comanda de una sola vez.
     * action: take (ordered→preparing) | ready (preparing→ready) | served (ready→served)
     * item_ids?: opcional, limita a un subconjunto de ítems (ej. por estación)
     */
    public function bulkTransition(Request $request, Check $check, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'action'   => 'required|in:take,ready,served',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'string',
        ]);

        $map = [
            'take'   => [CheckItemStatus::Ordered,   CheckItemStatus::Preparing, 'tomados'],
            'ready'  => [CheckItemStatus::Preparing, CheckItemStatus::Ready,     'listos'],
            'served' => [CheckItemStatus::Ready,      CheckItemStatus::Served,    'servidos'],
        ];

        [$from, $to, $verb] = $map[$data['action']];

        $query = $check->items()->where('status', $from->value);
        if (!empty($data['item_ids'])) {
            $query->whereIn('id', $data['item_ids']);
        }
        $items = $query->get();

        if ($items->isEmpty()) {
            return back()->with('success', 'No hay ítems para actualizar.');
        }

        $stationCodes = collect();
        $allLowStock  = [];
        DB::transaction(function () use ($items, $to, $inventory, $stationCodes, &$allLowStock) {
            foreach ($items as $item) {
                try {
                    $item->transitionTo($to);
                } catch (\RuntimeException) {
                    continue;
                }
                if ($to === CheckItemStatus::Served) {
                    $lowStock = $inventory->deductForItem($item);
                    foreach ($lowStock as $entry) {
                        $allLowStock[$entry['name']] = $entry;
                    }
                }
                if ($item->kitchenStation?->code) {
                    $stationCodes->push($item->kitchenStation->code);
                }
            }
        });

        if (!empty($allLowStock)) {
            broadcast(new StockAlert(array_values($allLowStock)));
        }

        $unique = $stationCodes->unique()->values()->all();
        if ($unique) {
            broadcast(new KitchenQueueChanged($unique, $to->value))->toOthers();
        }
        broadcast(new CheckUpdated($check, "item_{$to->value}", (string) $items->count()))->toOthers();

        return back()->with('success', "Todos los ítems marcados como {$verb}.");
    }

    private function transition(
        CheckItem $item,
        CheckItemStatus $target,
        string $verb,
        ?InventoryService $inventory = null,
    ): RedirectResponse {
        try {
            $item->transitionTo($target);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['item' => $e->getMessage()]);
        }

        if ($target === CheckItemStatus::Served && $inventory) {
            $lowStock = $inventory->deductForItem($item);
            if (!empty($lowStock)) {
                broadcast(new StockAlert($lowStock));
            }
        }

        $item->refresh();
        $stationCode = $item->kitchenStation?->code;

        if ($stationCode) {
            broadcast(new KitchenQueueChanged([$stationCode], $target->value))->toOthers();
        }
        broadcast(new CheckUpdated($item->check, "item_{$target->value}", $item->name_snapshot))->toOthers();

        return back()->with('success', "Item marcado como {$verb}");
    }

    private function assertModifiersValid(MenuItem $menuItem, array $selectedIds): void
    {
        $groupedOptions = [];
        foreach ($menuItem->modifierGroups as $group) {
            $groupedOptions[$group->id] = [
                'group'     => $group,
                'valid_ids' => $group->options->where('active', true)->pluck('id')->all(),
            ];
        }

        $allValidIds = collect($groupedOptions)->flatMap(fn ($g) => $g['valid_ids'])->all();
        foreach ($selectedIds as $id) {
            if (! in_array($id, $allValidIds, true)) {
                throw ValidationException::withMessages([
                    'items' => "El modificador seleccionado no pertenece a este plato.",
                ]);
            }
        }

        foreach ($groupedOptions as $data) {
            $group = $data['group'];
            $count = count(array_intersect($selectedIds, $data['valid_ids']));
            $min   = $group->min_selections;
            $max   = $group->max_selections;

            if ($count < $min) {
                throw ValidationException::withMessages([
                    'items' => "El grupo \"{$group->name}\" requiere al menos {$min} opción(es).",
                ]);
            }

            if ($max !== null && $count > $max) {
                throw ValidationException::withMessages([
                    'items' => "El grupo \"{$group->name}\" permite máximo {$max} opción(es).",
                ]);
            }
        }
    }

    private function assertMutable(Check $check): void
    {
        if (! $check->status->isMutable()) {
            throw ValidationException::withMessages([
                'check' => 'La comanda ya no es modificable.',
            ]);
        }
    }
}
