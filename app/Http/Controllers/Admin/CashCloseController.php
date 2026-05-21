<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CheckStatus;
use App\Http\Controllers\Controller;
use App\Models\CashClose;
use App\Models\Check;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashCloseController extends Controller
{
    public function index(Request $request): Response
    {
        $closes = CashClose::with('user')
            ->orderByDesc('period_to')
            ->paginate(20)
            ->through(fn ($c) => [
                'id'           => $c->id,
                'user'         => $c->user?->name,
                'period_from'  => $c->period_from->toIso8601String(),
                'period_to'    => $c->period_to->toIso8601String(),
                'checks_count' => $c->checks_count,
                'subtotal'     => (float) $c->subtotal,
                'tax'          => (float) $c->tax,
                'tip'          => (float) $c->tip,
                'total'        => (float) $c->total,
                'cash_counted' => $c->cash_counted !== null ? (float) $c->cash_counted : null,
                'card_counted' => $c->card_counted !== null ? (float) $c->card_counted : null,
                'notes'        => $c->notes,
                'created_at'   => $c->created_at->toIso8601String(),
            ]);

        $preview = $this->buildPreview();

        return Inertia::render('Admin/CashCloses/Index', [
            'closes'  => $closes,
            'preview' => $preview,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'cash_counted' => 'nullable|numeric|min:0',
            'card_counted' => 'nullable|numeric|min:0',
            'notes'        => 'nullable|string|max:500',
        ]);

        $preview = $this->buildPreview();

        if ($preview['checks_count'] === 0) {
            return back()->withErrors(['close' => 'No hay comandas cerradas pendientes de cierre.']);
        }

        CashClose::create([
            'user_id'      => $request->user()->id,
            'period_from'  => $preview['period_from'],
            'period_to'    => $preview['period_to'],
            'checks_count' => $preview['checks_count'],
            'subtotal'     => $preview['subtotal'],
            'tax'          => $preview['tax'],
            'tip'          => $preview['tip'],
            'total'        => $preview['total'],
            'cash_counted' => $request->cash_counted,
            'card_counted' => $request->card_counted,
            'notes'        => $request->notes,
        ]);

        return back()->with('success', 'Cierre de caja registrado.');
    }

    private function buildPreview(): array
    {
        $lastClose = CashClose::latest('period_to')->first();
        $from = $lastClose ? $lastClose->period_to : now()->subYears(10);
        $to   = now();

        $agg = Check::query()
            ->where('status', CheckStatus::Closed)
            ->where('closed_at', '>', $from)
            ->where('closed_at', '<=', $to)
            ->selectRaw('
                COUNT(*)            as checks_count,
                COALESCE(SUM(subtotal), 0) as subtotal,
                COALESCE(SUM(tax), 0)      as tax,
                COALESCE(SUM(tip), 0)      as tip,
                COALESCE(SUM(total), 0)    as total
            ')
            ->first();

        return [
            'period_from'  => $from,
            'period_to'    => $to,
            'checks_count' => (int) $agg->checks_count,
            'subtotal'     => (float) $agg->subtotal,
            'tax'          => (float) $agg->tax,
            'tip'          => (float) $agg->tip,
            'total'        => (float) $agg->total,
        ];
    }
}
