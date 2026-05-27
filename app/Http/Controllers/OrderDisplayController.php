<?php

namespace App\Http\Controllers;

use App\Enums\CheckItemStatus;
use App\Enums\CheckStatus;
use App\Models\BusinessSetting;
use App\Models\Check;
use App\Models\KitchenStation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderDisplayController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $setting = BusinessSetting::instance();
        $pin     = $setting->display_pin;

        // Si hay PIN configurado y la sesión no está desbloqueada → mostrar lock
        if ($pin && ! $request->session()->get('display_unlocked')) {
            return Inertia::render('DisplayLock');
        }

        $stations = KitchenStation::active()->pluck('code')->all();
        [$preparing, $ready] = $this->fetchOrders();

        return Inertia::render('OrderDisplay', [
            'preparing'     => $preparing,
            'ready'         => $ready,
            'station_codes' => $stations,
        ]);
    }

    public function unlock(Request $request): RedirectResponse
    {
        $setting = BusinessSetting::instance();
        $pin     = $setting->display_pin;

        if ($pin && $request->input('pin') === $pin) {
            $request->session()->put('display_unlocked', true);
            return redirect()->route('display.index');
        }

        return back()->withErrors(['pin' => 'PIN incorrecto.']);
    }

    private function fetchOrders(): array
    {
        $open = Check::query()
            ->where('status', CheckStatus::Open)
            ->with([
                'table:id,name',
                'items' => fn ($q) => $q->whereNotIn('status', [
                    CheckItemStatus::Draft->value,
                    CheckItemStatus::Cancelled->value,
                ]),
            ])
            ->get();

        $preparing = [];
        $ready     = [];

        foreach ($open as $check) {
            $sent = $check->items;
            if ($sent->isEmpty()) {
                continue;
            }

            $hasPending = $sent->where('status', CheckItemStatus::Preparing)->isNotEmpty();

            $allReady = $sent->every(
                fn ($i) => $i->status === CheckItemStatus::Ready
            );

            $isTakeout = $check->order_type?->value === 'takeout';
            $row = [
                'id'            => $check->id,
                'number'        => $check->number,
                'order_type'    => $check->order_type?->value ?? 'dine_in',
                'table_name'    => $check->table?->name,
                'customer_name' => $check->customer_name,
                'display_name'  => $isTakeout ? $check->customer_name : $check->table?->name,
            ];

            if ($hasPending) {
                $preparing[] = $row;
            } elseif ($allReady) {
                $ready[] = $row;
            }
        }

        return [$preparing, $ready];
    }
}
