<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CheckStatus;
use App\Http\Controllers\Controller;
use App\Models\Check;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'from'       => 'nullable|date',
            'to'         => 'nullable|date|after_or_equal:from',
            'waiter_id'  => 'nullable|string|exists:users,id',
            'search'     => 'nullable|string|max:50',
        ]);

        $from = $request->filled('from')
            ? now()->parse($request->from)->startOfDay()
            : now()->startOfDay();

        $to = $request->filled('to')
            ? now()->parse($request->to)->endOfDay()
            : now()->endOfDay();

        $query = Check::query()
            ->with(['table.area', 'waiter', 'felInvoice'])
            ->where('status', CheckStatus::Closed)
            ->whereBetween('closed_at', [$from, $to])
            ->when($request->filled('waiter_id'), fn ($q) => $q->where('waiter_user_id', $request->waiter_id))
            ->when($request->filled('search'), fn ($q) => $q->where('number', 'like', '%'.$request->search.'%'))
            ->withCount('items')
            ->orderByDesc('closed_at');

        $checks = $query->paginate(30)->withQueryString()->through(fn ($c) => [
            'id'          => $c->id,
            'number'      => $c->number,
            'table'       => $c->table ? ['name' => $c->table->name, 'area' => $c->table->area?->name] : null,
            'waiter'      => $c->waiter ? ['id' => $c->waiter->id, 'name' => $c->waiter->name] : null,
            'covers'      => $c->covers,
            'items_count' => $c->items_count,
            'subtotal'    => (float) $c->subtotal,
            'tax'         => (float) $c->tax,
            'tip'         => (float) $c->tip,
            'total'       => (float) $c->total,
            'opened_at'   => $c->opened_at?->toIso8601String(),
            'closed_at'   => $c->closed_at?->toIso8601String(),
            'fel_status'  => $c->felInvoice?->status->value,
            'fel_uuid'    => $c->felInvoice?->uuid,
        ]);

        $waiters = User::where('active', true)
            ->whereIn('role', ['admin', 'waiter', 'cashier'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Checks/Index', [
            'checks'    => $checks,
            'waiters'   => $waiters,
            'filters'   => [
                'from'      => $from->toDateString(),
                'to'        => $to->toDateString(),
                'waiter_id' => $request->waiter_id,
                'search'    => $request->search,
            ],
        ]);
    }
}
