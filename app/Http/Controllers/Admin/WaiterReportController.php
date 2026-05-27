<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CheckItemStatus;
use App\Enums\CheckStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WaiterReportController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'from'      => 'nullable|date',
            'to'        => 'nullable|date|after_or_equal:from',
            'waiter_id' => 'nullable|integer|exists:users,id',
        ]);

        $from = $request->filled('from')
            ? now()->parse($request->from)->startOfDay()
            : now()->startOfDay();

        $to = $request->filled('to')
            ? now()->parse($request->to)->endOfDay()
            : now()->endOfDay();

        $diffExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST((julianday(checks.closed_at) - julianday(checks.opened_at)) * 1440 AS INTEGER)"
            : "TIMESTAMPDIFF(MINUTE, checks.opened_at, checks.closed_at)";

        // Resumen por mesero
        $rows = User::where('role', UserRole::Waiter->value)
            ->where('active', true)
            ->leftJoin('checks', function ($join) use ($from, $to) {
                $join->on('checks.waiter_user_id', '=', 'users.id')
                    ->where('checks.status', CheckStatus::Closed->value)
                    ->whereBetween('checks.closed_at', [$from, $to]);
            })
            ->selectRaw("
                users.id,
                users.name,
                COUNT(checks.id)              AS total_checks,
                COALESCE(SUM(checks.total),0) AS total_revenue,
                COALESCE(AVG(checks.total),0) AS avg_ticket,
                COALESCE(SUM(checks.covers),0) AS total_covers,
                COALESCE(SUM(checks.tip),0)   AS total_tips,
                COALESCE(AVG({$diffExpr}),0)  AS avg_service_min
            ")
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_revenue')
            ->get();

        // Ítems servidos y cancelados por mesero (join separado)
        $itemStats = DB::table('check_items')
            ->join('checks', 'checks.id', '=', 'check_items.check_id')
            ->where('checks.status', CheckStatus::Closed->value)
            ->whereBetween('checks.closed_at', [$from, $to])
            ->selectRaw("
                checks.waiter_user_id,
                SUM(CASE WHEN check_items.status = ? THEN check_items.quantity ELSE 0 END) AS served,
                SUM(CASE WHEN check_items.status = ? THEN check_items.quantity ELSE 0 END) AS cancelled,
                SUM(check_items.quantity) AS total_items
            ", [CheckItemStatus::Served->value, CheckItemStatus::Cancelled->value])
            ->groupBy('checks.waiter_user_id')
            ->get()
            ->keyBy('waiter_user_id');

        $waiters = $rows->map(function ($row) use ($itemStats) {
            $items = $itemStats->get($row->id);
            $served    = (int) ($items?->served ?? 0);
            $cancelled = (int) ($items?->cancelled ?? 0);
            $total     = (int) ($items?->total_items ?? 0);

            return [
                'id'              => $row->id,
                'name'            => $row->name,
                'total_checks'    => (int) $row->total_checks,
                'total_revenue'   => (float) $row->total_revenue,
                'avg_ticket'      => (float) $row->avg_ticket,
                'total_covers'    => (int) $row->total_covers,
                'total_tips'      => (float) $row->total_tips,
                'avg_service_min' => (float) $row->avg_service_min,
                'items_served'    => $served,
                'items_cancelled' => $cancelled,
                'cancel_rate'     => $total > 0 ? round($cancelled / $total * 100, 1) : 0,
            ];
        })->values();

        // Detalle día a día del mesero seleccionado
        $daily = collect();
        $selectedWaiter = null;

        if ($request->filled('waiter_id')) {
            $selectedWaiter = User::find($request->waiter_id, ['id', 'name']);

            $daily = DB::table('checks')
                ->where('waiter_user_id', $request->waiter_id)
                ->where('status', CheckStatus::Closed->value)
                ->whereBetween('closed_at', [$from, $to])
                ->selectRaw('DATE(closed_at) as day, COUNT(*) as checks, SUM(total) as revenue, SUM(covers) as covers')
                ->groupBy('day')
                ->orderBy('day')
                ->get();
        }

        return Inertia::render('Admin/Reports/Waiters', [
            'from'            => $from->toDateString(),
            'to'              => $to->toDateString(),
            'waiters'         => $waiters,
            'daily'           => $daily,
            'selected_waiter' => $selectedWaiter,
        ]);
    }
}
