<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CheckStatus;
use App\Http\Controllers\Controller;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\RecipeItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $from = $request->filled('from')
            ? now()->parse($request->from)->startOfDay()
            : now()->startOfDay();

        $to = $request->filled('to')
            ? now()->parse($request->to)->endOfDay()
            : now()->endOfDay();

        $checks = Check::query()
            ->where('status', CheckStatus::Closed)
            ->whereBetween('closed_at', [$from, $to]);

        $summary = $checks->selectRaw('
            COUNT(*) as total_checks,
            COALESCE(SUM(subtotal), 0) as total_subtotal,
            COALESCE(SUM(tax), 0)      as total_tax,
            COALESCE(SUM(tip), 0)      as total_tip,
            COALESCE(SUM(total), 0)    as total_revenue,
            COALESCE(AVG(total), 0)    as avg_ticket
        ')->first();

        $topItems = CheckItem::query()
            ->join('checks', 'checks.id', '=', 'check_items.check_id')
            ->where('checks.status', CheckStatus::Closed)
            ->whereBetween('checks.closed_at', [$from, $to])
            ->whereNotNull('check_items.served_at')
            ->selectRaw('
                check_items.name_snapshot,
                SUM(check_items.quantity) as total_qty,
                SUM(check_items.price_snapshot * check_items.quantity) as total_revenue
            ')
            ->groupBy('check_items.name_snapshot')
            ->orderByDesc('total_qty')
            ->limit(15)
            ->get();

        $daily = Check::query()
            ->where('status', CheckStatus::Closed)
            ->whereBetween('closed_at', [$from, $to])
            ->selectRaw('DATE(closed_at) as day, COUNT(*) as checks, SUM(total) as revenue')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $foodCost = RecipeItem::query()
            ->join('ingredients', 'ingredients.id', '=', 'recipe_items.ingredient_id')
            ->join('menu_items', 'menu_items.id', '=', 'recipe_items.menu_item_id')
            ->join('check_items', 'check_items.menu_item_id', '=', 'menu_items.id')
            ->join('checks', 'checks.id', '=', 'check_items.check_id')
            ->where('checks.status', CheckStatus::Closed)
            ->whereBetween('checks.closed_at', [$from, $to])
            ->whereNotNull('check_items.served_at')
            ->selectRaw('
                SUM(recipe_items.quantity_used * check_items.quantity * ingredients.cost_price) as total_food_cost
            ')
            ->value('total_food_cost') ?? 0;

        $diffExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST((julianday(closed_at) - julianday(opened_at)) * 1440 AS INTEGER)"
            : "TIMESTAMPDIFF(MINUTE, opened_at, closed_at)";

        $tableRotation = Check::query()
            ->where('status', CheckStatus::Closed)
            ->whereBetween('closed_at', [$from, $to])
            ->whereNotNull('opened_at')
            ->selectRaw("AVG({$diffExpr}) as avg_minutes, COUNT(*) as total_checks")
            ->first();

        return Inertia::render('Admin/Reports/Index', [
            'from'    => $from->toDateString(),
            'to'      => $to->toDateString(),
            'summary' => [
                'total_checks'   => (int) $summary->total_checks,
                'total_subtotal' => (float) $summary->total_subtotal,
                'total_tax'      => (float) $summary->total_tax,
                'total_tip'      => (float) $summary->total_tip,
                'total_revenue'  => (float) $summary->total_revenue,
                'avg_ticket'     => (float) $summary->avg_ticket,
            ],
            'top_items'      => $topItems,
            'daily'          => $daily,
            'food_cost'      => (float) $foodCost,
            'table_rotation' => [
                'avg_minutes'  => $tableRotation ? (float) $tableRotation->avg_minutes : 0,
                'total_checks' => $tableRotation ? (int) $tableRotation->total_checks : 0,
            ],
        ]);
    }
}
