<?php

namespace App\Http\Controllers;

use App\Enums\CheckItemStatus;
use App\Events\CheckUpdated;
use App\Events\KitchenQueueChanged;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\MenuItem;
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
        ]);

        DB::transaction(function () use ($check, $validated) {
            foreach ($validated['items'] as $row) {
                $menuItem = MenuItem::findOrFail($row['menu_item_id']);
                CheckItem::create([
                    'check_id' => $check->id,
                    'menu_item_id' => $menuItem->id,
                    'kitchen_station_id' => $menuItem->kitchen_station_id,
                    'name_snapshot' => $menuItem->name,
                    'price_snapshot' => $menuItem->price,
                    'quantity' => $row['quantity'] ?? 1,
                    'notes' => $row['notes'] ?? null,
                    'status' => CheckItemStatus::Draft->value,
                ]);
            }
            $check->recalculate();
        });

        CheckUpdated::dispatch($check, 'item_added');

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

        CheckUpdated::dispatch($item->check, 'item_updated');

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
            CheckUpdated::dispatch($check, 'item_removed');

            return back()->with('success', 'Item eliminado');
        }

        if ($item->status === CheckItemStatus::Ordered) {
            $stationCode = $item->kitchenStation?->code;
            $item->transitionTo(CheckItemStatus::Cancelled);
            $item->check->recalculate();

            if ($stationCode) {
                KitchenQueueChanged::dispatch([$stationCode], 'item_cancelled');
            }
            CheckUpdated::dispatch($item->check, 'item_cancelled');

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
            $inventory->deductForItem($item);
        }

        $item->refresh();
        $stationCode = $item->kitchenStation?->code;

        if ($stationCode) {
            KitchenQueueChanged::dispatch([$stationCode], $target->value);
        }
        CheckUpdated::dispatch($item->check, "item_{$target->value}");

        return back()->with('success', "Item marcado como {$verb}");
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
