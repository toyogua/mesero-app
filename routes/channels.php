<?php

use App\Enums\UserRole;
use App\Models\Check;
use App\Models\KitchenStation;
use Illuminate\Support\Facades\Broadcast;

/*
 * Cada usuario autenticado puede escuchar cambios en cualquier comanda.
 * Restricciones más finas (solo la propia mesa) llegan en una fase futura.
 */
Broadcast::channel('check.{checkId}', function ($user, string $checkId) {
    return Check::whereKey($checkId)->exists();
});

/*
 * KDS — solo cocina y admin pueden escuchar las colas.
 */
Broadcast::channel('kitchen.{stationCode}', function ($user, string $stationCode) {
    if (! in_array($user->role, [UserRole::Kitchen, UserRole::Admin], strict: true)) {
        return false;
    }

    return KitchenStation::where('code', $stationCode)->exists();
});

/*
 * Salón — cualquier usuario operativo ve el estado de las mesas.
 */
Broadcast::channel('floor.{areaId}', function ($user) {
    return in_array($user->role, [UserRole::Waiter, UserRole::Admin, UserRole::Cashier], strict: true);
});
