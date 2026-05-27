<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CheckRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RatingReportController extends Controller
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

        $base = CheckRating::query()->whereBetween('rated_at', [$from, $to]);

        $summary = (clone $base)->selectRaw('
            COUNT(*)          as total_ratings,
            AVG(stars)        as avg_stars,
            SUM(stars = 5)    as five_stars,
            SUM(stars = 4)    as four_stars,
            SUM(stars = 3)    as three_stars,
            SUM(stars <= 2)   as low_stars
        ')->first();

        $recent = (clone $base)
            ->with(['check:id,number,waiter_user_id,order_type', 'check.waiter:id,name'])
            ->orderByDesc('rated_at')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'id'           => $r->id,
                'stars'        => $r->stars,
                'comment'      => $r->comment,
                'rated_at'     => $r->rated_at->toIso8601String(),
                'check_number' => $r->check?->number,
                'order_type'   => $r->check?->order_type?->value ?? 'dine_in',
                'waiter_name'  => $r->check?->waiter?->name,
            ]);

        $byWaiter = CheckRating::query()
            ->join('checks', 'checks.id', '=', 'check_ratings.check_id')
            ->join('users', 'users.id', '=', 'checks.waiter_user_id')
            ->whereBetween('check_ratings.rated_at', [$from, $to])
            ->selectRaw('
                users.name        as waiter_name,
                COUNT(*)          as total_ratings,
                AVG(check_ratings.stars) as avg_stars
            ')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('avg_stars')
            ->get();

        $daily = (clone $base)
            ->selectRaw('DATE(rated_at) as day, COUNT(*) as total, AVG(stars) as avg_stars')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return Inertia::render('Admin/Reports/Ratings', [
            'from'      => $from->toDateString(),
            'to'        => $to->toDateString(),
            'summary'   => [
                'total_ratings' => (int)   ($summary->total_ratings ?? 0),
                'avg_stars'     => (float) ($summary->avg_stars ?? 0),
                'five_stars'    => (int)   ($summary->five_stars ?? 0),
                'four_stars'    => (int)   ($summary->four_stars ?? 0),
                'three_stars'   => (int)   ($summary->three_stars ?? 0),
                'low_stars'     => (int)   ($summary->low_stars ?? 0),
            ],
            'recent'    => $recent,
            'by_waiter' => $byWaiter,
            'daily'     => $daily,
        ]);
    }
}
