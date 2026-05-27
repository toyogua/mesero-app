<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CheckStatus;
use App\Enums\FelStatus;
use App\Http\Controllers\Controller;
use App\Models\Check;
use App\Models\FelInvoice;
use App\Models\Ingredient;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = now()->toDateString();

        $todayRow = Check::where('status', CheckStatus::Closed->value)
            ->whereDate('closed_at', $today)
            ->selectRaw('COUNT(*) as checks_count, COALESCE(SUM(total),0) as revenue, COALESCE(AVG(total),0) as avg_ticket, COALESCE(SUM(covers),0) as covers')
            ->first();

        $openChecks = Check::with(['table:id,name,area_id', 'table.area:id,name', 'waiter:id,name'])
            ->where('status', CheckStatus::Open->value)
            ->orderBy('opened_at')
            ->get()
            ->map(fn ($c) => [
                'id'        => $c->id,
                'number'    => $c->number,
                'table'     => $c->table?->name,
                'area'      => $c->table?->area?->name,
                'waiter'    => $c->waiter?->name,
                'opened_at' => $c->opened_at?->toIso8601String(),
                'total'     => (float) $c->total,
            ]);

        $lowStock = Ingredient::active()
            ->lowStock()
            ->orderBy('quantity_on_hand')
            ->limit(10)
            ->get(['id', 'name', 'unit', 'quantity_on_hand', 'minimum_stock']);

        $felFailures = FelInvoice::where('status', FelStatus::Failed->value)
            ->with('check:id,number')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($inv) => [
                'id'            => $inv->id,
                'check_number'  => $inv->check?->number,
                'error_message' => $inv->error_message,
                'retries'       => $inv->retries,
            ]);

        $weekRevenue = Check::where('status', CheckStatus::Closed->value)
            ->where('closed_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw("DATE(closed_at) as day, COUNT(*) as checks_count, COALESCE(SUM(total),0) as revenue")
            ->groupByRaw('DATE(closed_at)')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => [
                'day'          => $r->day,
                'checks_count' => (int) $r->checks_count,
                'revenue'      => (float) $r->revenue,
            ]);

        return Inertia::render('Admin/Dashboard', [
            'today_stats'  => [
                'checks_count' => (int) $todayRow->checks_count,
                'revenue'      => (float) $todayRow->revenue,
                'avg_ticket'   => (float) $todayRow->avg_ticket,
                'covers'       => (int) $todayRow->covers,
                'open_count'   => $openChecks->count(),
            ],
            'open_checks'  => $openChecks,
            'low_stock'    => $lowStock,
            'fel_failures' => $felFailures,
            'week_revenue' => $weekRevenue,
        ]);
    }
}
