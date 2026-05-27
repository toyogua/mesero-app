<?php

namespace App\Http\Controllers;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Events\CheckUpdated;
use App\Models\Check;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TakeoutController extends Controller
{
    public function index(): Response
    {
        $checks = Check::query()
            ->where('order_type', CheckType::Takeout)
            ->where('status', CheckStatus::Open)
            ->with(['waiter:id,name', 'items'])
            ->orderBy('opened_at')
            ->get()
            ->map(fn ($c) => [
                'id'               => $c->id,
                'number'           => $c->number,
                'customer_name'    => $c->customer_name,
                'customer_phone'   => $c->customer_phone,
                'customer_address' => $c->customer_address,
                'opened_at'        => $c->opened_at?->toIso8601String(),
                'total'            => (float) $c->total,
                'source'           => $c->source?->value ?? 'pos',
                'items_count'      => $c->items->whereNotIn('status', ['cancelled'])->count(),
                'waiter'           => ['name' => $c->waiter?->name],
            ]);

        return Inertia::render('Takeout/Index', [
            'checks' => $checks,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name'    => 'required|string|max:100',
            'customer_phone'   => 'required|string|max:20',
            'customer_address' => 'nullable|string|max:255',
        ]);

        $check = DB::transaction(function () use ($request, $data) {
            return Check::create([
                'number'           => $this->nextNumber(),
                'order_type'       => CheckType::Takeout->value,
                'waiter_user_id'   => $request->user()->id,
                'status'           => CheckStatus::Open->value,
                'covers'           => 1,
                'opened_at'        => now(),
                'customer_name'    => $data['customer_name'],
                'customer_phone'   => $data['customer_phone'],
                'customer_address' => $data['customer_address'] ?? null,
            ]);
        });

        broadcast(new CheckUpdated($check, 'opened'))->toOthers();

        return redirect()->route('checks.show', $check)
            ->with('success', "Orden #{$check->number} creada para {$check->customer_name}");
    }

    private function nextNumber(): string
    {
        $prefix  = config('restaurant.check_number_prefix');
        $padding = (int) config('restaurant.check_number_padding');
        $last    = Check::query()->orderByDesc('number')->value('number');
        $n       = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $n, $padding, '0', STR_PAD_LEFT);
    }
}
