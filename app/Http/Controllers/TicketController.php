<?php

namespace App\Http\Controllers;

use App\Enums\CheckItemStatus;
use App\Models\Check;
use App\Models\KitchenStation;
use Illuminate\Contracts\View\View;

class TicketController extends Controller
{
    /**
     * Printable receipt for the full check (customer copy).
     */
    public function check(Check $check): View
    {
        $check->load([
            'table.area',
            'waiter',
            'items' => fn ($q) => $q->orderBy('created_at'),
            'items.modifiers',
            'items.kitchenStation:id,name',
            'felInvoice',
        ]);

        $ivaRate = (float) config('restaurant.iva_rate');

        return view('tickets.check', compact('check', 'ivaRate'));
    }

    /**
     * Printable kitchen ticket for a station — shows all pending items.
     */
    public function station(KitchenStation $station): View
    {
        $items = $station->checkItems()
            ->whereIn('status', [
                CheckItemStatus::Ordered->value,
                CheckItemStatus::Preparing->value,
            ])
            ->with([
                'check.table.area',
                'modifiers',
            ])
            ->orderBy('sent_at')
            ->get();

        return view('tickets.station', compact('station', 'items'));
    }
}
