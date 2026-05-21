<?php

namespace App\Http\Controllers;

use App\Enums\CheckItemStatus;
use App\Enums\CheckStatus;
use App\Events\CheckUpdated;
use App\Events\FloorChanged;
use App\Events\KitchenQueueChanged;
use App\Models\Check;
use App\Models\MenuItem;
use App\Models\Table;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CheckController extends Controller
{
    /**
     * Abrir comanda en una mesa. Si ya hay una abierta → redirige a ella.
     */
    public function open(Request $request, Table $table): RedirectResponse
    {
        $existing = $table->openCheck;
        if ($existing) {
            return redirect()->route('checks.show', $existing);
        }

        $check = DB::transaction(function () use ($request, $table) {
            $covers = (int) $request->input('covers', 1);

            return Check::create([
                'number' => $this->nextNumber(),
                'table_id' => $table->id,
                'waiter_user_id' => $request->user()->id,
                'status' => CheckStatus::Open->value,
                'covers' => max(1, $covers),
                'opened_at' => now(),
            ]);
        });

        if ($table->area_id) {
            FloorChanged::dispatch($table->area_id, 'occupied');
        }
        CheckUpdated::dispatch($check, 'opened');

        return redirect()->route('checks.show', $check)
            ->with('success', "Comanda {$check->number} abierta");
    }

    public function show(Check $check): Response
    {
        $check->load([
            'table.area',
            'waiter',
            'items' => fn ($q) => $q->orderBy('created_at'),
            'items.menuItem',
            'items.kitchenStation:id,name,code',
            'items.modifiers',
        ]);

        $menu = MenuItem::query()
            ->active()
            ->with('kitchenStation:id,name,code', 'modifierGroups.options')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category')
            ->map(fn ($items, $category) => [
                'category' => $category,
                'items' => $items->map(fn ($i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'price' => (float) $i->price,
                    'kitchen_station_id' => $i->kitchen_station_id,
                    'kitchen_station_name' => $i->kitchenStation->name,
                    'modifier_groups' => $i->modifierGroups->map(fn ($g) => [
                        'id' => $g->id,
                        'name' => $g->name,
                        'selection_type' => $g->selection_type,
                        'required' => $g->required,
                        'options' => $g->options->where('active', true)->map(fn ($o) => [
                            'id' => $o->id,
                            'name' => $o->name,
                            'price_delta' => (float) $o->price_delta,
                        ])->values(),
                    ]),
                ]),
            ])
            ->values();

        return Inertia::render('Checks/Show', [
            'check' => $this->serializeCheck($check),
            'menu' => $menu,
        ]);
    }

    /**
     * Enviar a cocina: todos los items en draft → ordered.
     */
    public function send(Check $check): RedirectResponse
    {
        $this->assertMutable($check);

        $drafts = $check->items()
            ->where('status', CheckItemStatus::Draft->value)
            ->get();

        if ($drafts->isEmpty()) {
            return back()->with('error', 'No hay items nuevos para enviar.');
        }

        DB::transaction(function () use ($drafts) {
            foreach ($drafts as $item) {
                $item->transitionTo(CheckItemStatus::Ordered);
            }
        });

        $stationCodes = $drafts->load('kitchenStation:id,code')
            ->pluck('kitchenStation.code')
            ->filter()
            ->all();

        if ($stationCodes) {
            KitchenQueueChanged::dispatch($stationCodes, 'new_items');
        }
        CheckUpdated::dispatch($check, 'sent_to_kitchen');

        return back()->with('success', "{$drafts->count()} items enviados a cocina");
    }

    /**
     * Cerrar comanda. Solo permitido si todos los items están served o cancelled.
     */
    public function close(Check $check): RedirectResponse
    {
        $this->assertMutable($check);

        if (! $check->isReadyToClose()) {
            throw ValidationException::withMessages([
                'check' => 'Hay items pendientes (en cocina o por servir).',
            ]);
        }

        $check->forceFill([
            'status' => CheckStatus::Closed->value,
            'closed_at' => now(),
        ])->save();

        if ($check->table?->area_id) {
            FloorChanged::dispatch($check->table->area_id, 'freed');
        }
        CheckUpdated::dispatch($check, 'closed');

        return redirect()->route('floor.index')
            ->with('success', "Comanda {$check->number} cerrada");
    }

    private function assertMutable(Check $check): void
    {
        if (! $check->status->isMutable()) {
            throw ValidationException::withMessages([
                'check' => 'La comanda ya no es modificable.',
            ]);
        }
    }

    private function nextNumber(): string
    {
        $prefix = config('restaurant.check_number_prefix');
        $padding = (int) config('restaurant.check_number_padding');

        // Zero-padded → lex sort coincide con orden numérico.
        $last = Check::query()->orderByDesc('number')->value('number');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $n, $padding, '0', STR_PAD_LEFT);
    }

    private function serializeCheck(Check $check): array
    {
        return [
            'id' => $check->id,
            'number' => $check->number,
            'status' => $check->status->value,
            'covers' => $check->covers,
            'notes' => $check->notes,
            'subtotal' => (float) $check->subtotal,
            'tax' => (float) $check->tax,
            'tip' => (float) $check->tip,
            'total' => (float) $check->total,
            'opened_at' => $check->opened_at?->toIso8601String(),
            'is_ready_to_close' => $check->isReadyToClose(),
            'table' => $check->table ? [
                'id' => $check->table->id,
                'name' => $check->table->name,
                'area_name' => $check->table->area?->name,
                'capacity' => $check->table->capacity,
            ] : null,
            'waiter' => [
                'id' => $check->waiter->id,
                'name' => $check->waiter->name,
            ],
            'items' => $check->items->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name_snapshot,
                'price' => (float) $i->price_snapshot,
                'quantity' => $i->quantity,
                'notes' => $i->notes,
                'status' => $i->status->value,
                'kitchen_station' => $i->kitchenStation?->only(['id', 'name', 'code']),
                'sent_at' => $i->sent_at?->toIso8601String(),
                'ready_at' => $i->ready_at?->toIso8601String(),
                'served_at' => $i->served_at?->toIso8601String(),
                'modifiers' => $i->modifiers->map(fn ($m) => [
                    'name' => $m->name_snapshot,
                    'price_delta' => (float) $m->price_snapshot,
                ])->values()->all(),
                'line_total' => (float) (($i->price_snapshot + $i->modifiers->sum('price_snapshot')) * $i->quantity),
            ]),
        ];
    }
}
