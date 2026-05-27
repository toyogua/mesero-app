<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\StockEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockEntryController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'ingredient_id' => 'nullable|string|exists:ingredients,id',
            'from'          => 'nullable|date',
            'to'            => 'nullable|date|after_or_equal:from',
        ]);

        $from = $request->filled('from')
            ? now()->parse($request->from)->startOfDay()
            : now()->subDays(29)->startOfDay();

        $to = $request->filled('to')
            ? now()->parse($request->to)->endOfDay()
            : now()->endOfDay();

        $entries = StockEntry::with(['ingredient:id,name,unit', 'user:id,name'])
            ->when($request->filled('ingredient_id'), fn ($q) => $q->where('ingredient_id', $request->ingredient_id))
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn ($e) => [
                'id'              => $e->id,
                'ingredient'      => ['id' => $e->ingredient->id, 'name' => $e->ingredient->name, 'unit' => $e->ingredient->unit],
                'user'            => $e->user?->name ?? 'Sistema',
                'quantity'        => (float) $e->quantity,
                'quantity_before' => $e->quantity_before !== null ? (float) $e->quantity_before : null,
                'cost_price'      => $e->cost_price !== null ? (float) $e->cost_price : null,
                'notes'           => $e->notes,
                'created_at'      => $e->created_at->toIso8601String(),
            ]);

        $ingredients = Ingredient::orderBy('name')->get(['id', 'name', 'unit']);

        return Inertia::render('Admin/StockEntries/Index', [
            'entries'     => $entries,
            'ingredients' => $ingredients,
            'filters'     => [
                'ingredient_id' => $request->ingredient_id,
                'from'          => $from->toDateString(),
                'to'            => $to->toDateString(),
            ],
        ]);
    }
}
