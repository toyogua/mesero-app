<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Http\Controllers\Controller;
use App\Models\Check;
use App\Models\CheckItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TakeoutReportController extends Controller
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

        $base = Check::query()
            ->where('order_type', CheckType::Takeout)
            ->where('status', CheckStatus::Closed)
            ->whereBetween('closed_at', [$from, $to]);

        $summary = (clone $base)->selectRaw('
            COUNT(*)                                                    as total_checks,
            SUM(CASE WHEN source = \'web\' THEN 1 ELSE 0 END)          as web_orders,
            SUM(CASE WHEN source = \'pos\' THEN 1 ELSE 0 END)          as pos_orders,
            COALESCE(SUM(subtotal), 0)                                  as total_subtotal,
            COALESCE(SUM(tax), 0)                                       as total_tax,
            COALESCE(SUM(tip), 0)                                       as total_tip,
            COALESCE(SUM(total), 0)                                     as total_revenue,
            COALESCE(AVG(total), 0)                                     as avg_ticket
        ')->first();

        $daily = (clone $base)
            ->selectRaw("
                DATE(closed_at)                                             as day,
                COUNT(*)                                                    as checks,
                SUM(CASE WHEN source = 'web' THEN 1 ELSE 0 END)            as web_checks,
                SUM(CASE WHEN source = 'pos' THEN 1 ELSE 0 END)            as pos_checks,
                SUM(total)                                                  as revenue
            ")
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $topItems = CheckItem::query()
            ->join('checks', 'checks.id', '=', 'check_items.check_id')
            ->where('checks.order_type', CheckType::Takeout)
            ->where('checks.status', CheckStatus::Closed)
            ->whereBetween('checks.closed_at', [$from, $to])
            ->whereNotNull('check_items.served_at')
            ->selectRaw('
                check_items.name_snapshot,
                SUM(check_items.quantity)                                  as total_qty,
                SUM(check_items.price_snapshot * check_items.quantity)     as total_revenue
            ')
            ->groupBy('check_items.name_snapshot')
            ->orderByDesc('total_qty')
            ->limit(15)
            ->get();

        $topCustomers = (clone $base)
            ->selectRaw('
                customer_name,
                customer_phone,
                COUNT(*)          as total_orders,
                SUM(total)        as total_spent
            ')
            ->groupBy('customer_name', 'customer_phone')
            ->orderByDesc('total_orders')
            ->limit(15)
            ->get();

        $diffExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST((julianday(closed_at) - julianday(opened_at)) * 1440 AS INTEGER)"
            : "TIMESTAMPDIFF(MINUTE, opened_at, closed_at)";

        $avgPrep = (clone $base)
            ->whereNotNull('opened_at')
            ->selectRaw("AVG({$diffExpr}) as avg_minutes")
            ->value('avg_minutes') ?? 0;

        return Inertia::render('Admin/Reports/Takeout', [
            'from'          => $from->toDateString(),
            'to'            => $to->toDateString(),
            'summary'       => [
                'total_checks'   => (int)   $summary->total_checks,
                'web_orders'     => (int)   $summary->web_orders,
                'pos_orders'     => (int)   $summary->pos_orders,
                'total_subtotal' => (float) $summary->total_subtotal,
                'total_tax'      => (float) $summary->total_tax,
                'total_tip'      => (float) $summary->total_tip,
                'total_revenue'  => (float) $summary->total_revenue,
                'avg_ticket'     => (float) $summary->avg_ticket,
            ],
            'daily'          => $daily,
            'top_items'      => $topItems,
            'top_customers'  => $topCustomers,
            'avg_prep_minutes' => (float) $avgPrep,
        ]);
    }
}
