<?php

namespace App\Http\Controllers;

use App\Enums\CheckItemStatus;
use App\Enums\CheckStatus;
use App\Events\CheckUpdated;
use App\Events\FloorChanged;
use App\Events\KitchenQueueChanged;
use App\Jobs\IssueFelInvoice;
use App\Models\Check;
use App\Models\MenuItem;
use App\Models\Table;
use App\Services\Fel\FelService;
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
        broadcast(new CheckUpdated($check, 'opened'))->toOthers();

        return redirect()->route('checks.show', $check)
            ->with('success', "Comanda {$check->number} abierta");
    }

    public function show(Check $check): Response
    {
        $check->load([
            'table.area',
            'waiter',
            'items' => fn ($q) => $q->orderBy('created_at'),
            'items.kitchenStation:id,name,code',
            'items.modifiers',
            'felInvoice',
            'splits',
        ]);

        $menu = \Illuminate\Support\Facades\Cache::remember('menu_for_check', 300, function () {
            return MenuItem::query()
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
                        'kitchen_station_name' => $i->kitchenStation?->name,
                        'modifier_groups' => $i->modifierGroups->map(fn ($g) => [
                            'id'             => $g->id,
                            'name'           => $g->name,
                            'min_selections' => $g->min_selections,
                            'max_selections' => $g->max_selections,
                            'options' => $g->options->where('active', true)->map(fn ($o) => [
                                'id' => $o->id,
                                'name' => $o->name,
                                'price_delta' => (float) $o->price_delta,
                            ])->values(),
                        ]),
                    ]),
                ])
                ->values();
        });

        $availableTables = \App\Models\Table::with('area:id,name')
            ->active()
            ->whereDoesntHave('openCheck')
            ->where('id', '!=', $check->table_id)
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id'   => $t->id,
                'name' => $t->name . ($t->area ? ' — ' . $t->area->name : ''),
            ]);

        $stockMap = MenuItem::query()
            ->active()
            ->with(['recipeItems.ingredient:id,quantity_on_hand'])
            ->get()
            ->mapWithKeys(function ($item) {
                if ($item->recipeItems->isEmpty()) {
                    return [$item->id => null];
                }
                $available = $item->recipeItems->min(function ($ri) {
                    if ($ri->quantity_used <= 0) return PHP_INT_MAX;
                    return (int) floor($ri->ingredient->quantity_on_hand / $ri->quantity_used);
                });
                return [$item->id => max(0, $available)];
            });

        return Inertia::render('Checks/Show', [
            'check'            => $this->serializeCheck($check),
            'menu'             => $menu,
            'stock_map'        => $stockMap,
            'available_tables' => $availableTables,
            'fel_enabled'      => (bool) config('restaurant.fel.enabled'),
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
            broadcast(new KitchenQueueChanged($stationCodes, 'new_items'))->toOthers();
        }
        broadcast(new CheckUpdated($check, 'sent_to_kitchen'))->toOthers();

        return back()->with('success', "{$drafts->count()} items enviados a cocina");
    }

    public function notes(Request $request, Check $check): RedirectResponse
    {
        $this->assertMutable($check);

        $request->validate(['notes' => 'nullable|string|max:500']);

        $check->update(['notes' => $request->input('notes')]);
        broadcast(new CheckUpdated($check, 'notes_updated'))->toOthers();

        return back();
    }

    public function tip(Request $request, Check $check): RedirectResponse
    {
        $this->assertMutable($check);

        $request->validate(['amount' => 'required|numeric|min:0']);

        $check->forceFill(['tip' => (float) $request->input('amount')])->save();
        $check->recalculate();
        broadcast(new CheckUpdated($check, 'tip_updated'))->toOthers();

        return back();
    }

    /**
     * Cerrar comanda. Solo permitido si todos los items están served o cancelled.
     */
    public function close(Request $request, Check $check, FelService $fel): RedirectResponse
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
        broadcast(new CheckUpdated($check, 'closed'))->toOthers();

        if (config('restaurant.fel.enabled')) {
            $nit  = strtoupper(trim($request->input('receptor_nit', ''))) ?: 'CF';
            $name = trim($request->input('receptor_name', ''))             ?: 'CONSUMIDOR FINAL';
            $invoice = $fel->createPending($check, $nit, $name);
            IssueFelInvoice::dispatch($invoice);
        }

        return redirect()->route('floor.index')
            ->with('success', "Comanda {$check->number} cerrada");
    }

    /**
     * Transferir comanda a otra mesa. Solo admin o el mesero de la comanda.
     */
    public function transfer(Request $request, Check $check): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->assertMutable($check);

        $data = $request->validate([
            'table_id' => 'required|exists:tables,id',
        ]);

        $target = \App\Models\Table::findOrFail($data['table_id']);

        if ($target->openCheck) {
            throw ValidationException::withMessages([
                'table_id' => 'La mesa destino ya tiene una comanda abierta.',
            ]);
        }

        $fromAreaId = $check->table?->area_id;
        $toAreaId   = $target->area_id;
        $fromName   = $check->table?->name;

        $check->forceFill([
            'table_id'         => $target->id,
            'transferred_from' => $fromName,
        ])->save();

        if ($fromAreaId) {
            FloorChanged::dispatch($fromAreaId, 'freed');
        }
        if ($toAreaId && $toAreaId !== $fromAreaId) {
            FloorChanged::dispatch($toAreaId, 'occupied');
        }
        broadcast(new CheckUpdated($check, 'transferred'))->toOthers();

        return back()->with('success', "Comanda movida a {$target->name}.");
    }

    /**
     * Anular comanda completa. Solo admin. Cancela todos los ítems no finales.
     */
    public function void(Request $request, Check $check): RedirectResponse
    {
        if (! $request->user()->isAdmin()) {
            abort(403);
        }

        $this->assertMutable($check);

        DB::transaction(function () use ($check) {
            // Bypass transition validation — admin override cancels any active item.
            $check->items()
                ->whereNotIn('status', [CheckItemStatus::Served->value, CheckItemStatus::Cancelled->value])
                ->update(['status' => CheckItemStatus::Cancelled->value]);

            $check->forceFill([
                'status'    => CheckStatus::Void->value,
                'closed_at' => now(),
            ])->save();
        });

        if ($check->table?->area_id) {
            FloorChanged::dispatch($check->table->area_id, 'freed');
        }
        broadcast(new CheckUpdated($check, 'voided'))->toOthers();

        return redirect()->route('floor.index')
            ->with('success', "Comanda {$check->number} anulada.");
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
            'transferred_from'  => $check->transferred_from,
            'order_type'        => $check->order_type?->value ?? 'dine_in',
            'source'            => $check->source?->value ?? 'pos',
            'customer_name'     => $check->customer_name,
            'customer_phone'    => $check->customer_phone,
            'customer_address'  => $check->customer_address,
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
            'fel' => $check->felInvoice ? [
                'status' => $check->felInvoice->status->value,
                'uuid'   => $check->felInvoice->uuid,
                'serie'  => $check->felInvoice->serie,
                'numero' => $check->felInvoice->numero,
            ] : null,
            'splits' => $check->splits->map(fn ($s) => [
                'id'      => $s->id,
                'label'   => $s->label,
                'amount'  => (float) $s->amount,
                'method'  => $s->method,
                'paid_at' => $s->paid_at?->toIso8601String(),
            ])->values()->all(),
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
