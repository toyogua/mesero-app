<?php

namespace App\Http\Controllers;

use App\Enums\CheckItemStatus;
use App\Enums\CheckSource;
use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Events\CheckUpdated;
use App\Models\BusinessSetting;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\CheckItemModifier;
use App\Models\MenuItem;
use App\Models\ModifierOption;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OnlineOrderController extends Controller
{
    public function index(): Response
    {
        $business = BusinessSetting::instance();
        $enabled  = (bool) ($business->online_ordering_enabled ?? true);

        $categories = $enabled
            ? MenuItem::active()
                ->with(['modifierGroups' => fn ($q) => $q
                    ->with(['options' => fn ($q) => $q->where('active', true)->orderBy('name')])
                ])
                ->orderBy('category')
                ->orderBy('name')
                ->get()
                ->groupBy('category')
                ->map(fn ($items, $category) => [
                    'category' => $category,
                    'items'    => $items->map(fn ($i) => [
                        'id'          => $i->id,
                        'name'        => $i->name,
                        'description' => $i->description,
                        'price'       => (float) $i->price,
                        'modifier_groups' => $i->modifierGroups->map(fn ($g) => [
                            'id'             => $g->id,
                            'name'           => $g->name,
                            'selection_type' => $g->selection_type,
                            'required'       => (bool) $g->required,
                            'options'        => $g->options->map(fn ($o) => [
                                'id'          => $o->id,
                                'name'        => $o->name,
                                'price_delta' => (float) $o->price_delta,
                            ])->values(),
                        ])->values(),
                    ])->values(),
                ])
                ->values()
            : collect();

        return Inertia::render('Order/Index', [
            'categories'       => $categories,
            'restaurant_name'  => $business->business_name ?: config('app.name'),
            'ordering_enabled' => $enabled,
            'min_amount'       => $business->online_ordering_min_amount
                ? (float) $business->online_ordering_min_amount
                : null,
        ]);
    }

    public function store(Request $request): Response|RedirectResponse
    {
        $business = BusinessSetting::instance();

        if (! ($business->online_ordering_enabled ?? true)) {
            throw ValidationException::withMessages([
                'order' => 'El servicio de órdenes en línea no está disponible en este momento.',
            ]);
        }

        $data = $request->validate([
            'customer_name'              => 'required|string|max:100',
            'customer_phone'             => 'required|string|max:20',
            'customer_address'           => 'nullable|string|max:255',
            'items'                      => 'required|array|min:1',
            'items.*.menu_item_id'       => 'required|string|exists:menu_items,id',
            'items.*.quantity'           => 'required|integer|min:1|max:50',
            'items.*.notes'              => 'nullable|string|max:255',
            'items.*.modifiers'          => 'nullable|array',
            'items.*.modifiers.*'        => 'string|exists:modifier_options,id',
        ]);

        if ($business->online_ordering_min_amount > 0) {
            $menuItems = MenuItem::whereIn('id', collect($data['items'])->pluck('menu_item_id'))->get()->keyBy('id');
            $optionIds = collect($data['items'])->flatMap(fn ($r) => $r['modifiers'] ?? []);
            $options   = $optionIds->isNotEmpty()
                ? ModifierOption::whereIn('id', $optionIds)->get()->keyBy('id')
                : collect();

            $orderTotal = collect($data['items'])->sum(function ($row) use ($menuItems, $options) {
                $item     = $menuItems[$row['menu_item_id']];
                $modTotal = collect($row['modifiers'] ?? [])->sum(fn ($id) => $options[$id]?->price_delta ?? 0);
                return ($item->price + $modTotal) * $row['quantity'];
            });

            if ($orderTotal < $business->online_ordering_min_amount) {
                throw ValidationException::withMessages([
                    'items' => 'El monto mínimo de la orden es Q ' . number_format($business->online_ordering_min_amount, 2),
                ]);
            }
        }

        $check = DB::transaction(function () use ($data) {
            $check = Check::create([
                'number'           => $this->nextNumber(),
                'order_type'       => CheckType::Takeout->value,
                'source'           => CheckSource::Web->value,
                'waiter_user_id'   => $this->systemUserId(),
                'status'           => CheckStatus::Open->value,
                'covers'           => 1,
                'opened_at'        => now(),
                'customer_name'    => $data['customer_name'],
                'customer_phone'   => $data['customer_phone'],
                'customer_address' => $data['customer_address'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $menuItem = MenuItem::findOrFail($row['menu_item_id']);

                $item = CheckItem::create([
                    'check_id'           => $check->id,
                    'menu_item_id'       => $menuItem->id,
                    'kitchen_station_id' => $menuItem->kitchen_station_id,
                    'name_snapshot'      => $menuItem->name,
                    'price_snapshot'     => $menuItem->price,
                    'quantity'           => $row['quantity'],
                    'notes'              => $row['notes'] ?? null,
                    'status'             => CheckItemStatus::Draft->value,
                ]);

                foreach ($row['modifiers'] ?? [] as $optionId) {
                    $option = ModifierOption::find($optionId);
                    if ($option) {
                        CheckItemModifier::create([
                            'check_item_id'      => $item->id,
                            'modifier_option_id' => $option->id,
                            'name_snapshot'      => $option->name,
                            'price_snapshot'     => $option->price_delta,
                        ]);
                    }
                }
            }

            $check->recalculate();

            return $check;
        });

        broadcast(new CheckUpdated($check, 'opened'));

        return Inertia::render('Order/Confirmation', [
            'number'        => $check->number,
            'customer_name' => $check->customer_name,
            'total'         => (float) $check->total,
            'items_count'   => count($data['items']),
        ]);
    }

    private function nextNumber(): string
    {
        $prefix  = config('restaurant.check_number_prefix');
        $padding = (int) config('restaurant.check_number_padding');
        $last    = Check::query()->orderByDesc('number')->value('number');
        $n       = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $n, $padding, '0', STR_PAD_LEFT);
    }

    private function systemUserId(): string
    {
        // Web orders are assigned to the first admin user as owner
        return \App\Models\User::where('role', 'admin')->value('id')
            ?? \App\Models\User::first()->id;
    }
}
