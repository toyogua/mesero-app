<?php

namespace App\Http\Controllers;

use App\Enums\CheckItemStatus;
use App\Models\CheckItem;
use App\Models\KitchenStation;
use Inertia\Inertia;
use Inertia\Response;

class KitchenController extends Controller
{
    public function index(): Response
    {
        $stations = KitchenStation::active()
            ->orderBy('display_order')
            ->get(['id', 'name', 'code']);

        $items = CheckItem::query()
            ->whereIn('status', [
                CheckItemStatus::Ordered->value,
                CheckItemStatus::Preparing->value,
                CheckItemStatus::Ready->value,
            ])
            ->with([
                'check:id,number,table_id,covers,opened_at,order_type,customer_name',
                'check.table:id,name,area_id',
                'check.table.area:id,name',
                'kitchenStation:id,name,code',
                'modifiers',
            ])
            ->orderBy('sent_at')
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name_snapshot,
                'quantity' => $i->quantity,
                'notes' => $i->notes,
                'status' => $i->status->value,
                'station_code' => $i->kitchenStation?->code,
                'station_name' => $i->kitchenStation?->name,
                'sent_at' => $i->sent_at?->toIso8601String(),
                'ready_at' => $i->ready_at?->toIso8601String(),
                'check_id' => $i->check_id,
                'check_number' => $i->check?->number,
                'order_type'    => $i->check?->order_type?->value ?? 'dine_in',
                'customer_name' => $i->check?->customer_name,
                'table_name'    => $i->check?->table?->name,
                'area_name'     => $i->check?->table?->area?->name,
                'covers' => $i->check?->covers,
                'opened_at' => $i->check?->opened_at?->toIso8601String(),
                'modifiers' => $i->modifiers->map(fn ($m) => [
                    'name' => $m->name_snapshot,
                    'price_delta' => (float) $m->price_snapshot,
                ])->values()->all(),
            ]);

        return Inertia::render('Kitchen/Display', [
            'stations' => $stations,
            'items' => $items,
        ]);
    }
}
