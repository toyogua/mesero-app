<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Inertia\Inertia;
use Inertia\Response;

class FloorController extends Controller
{
    public function index(): Response
    {
        $areas = Area::query()
            ->active()
            ->with(['tables' => function ($q) {
                $q->active()->with(['openCheck:id,table_id,number,covers,opened_at,subtotal']);
            }])
            ->orderBy('display_order')
            ->get();

        $payload = $areas->map(function ($area) {
            return [
                'id' => $area->id,
                'name' => $area->name,
                'tables' => $area->tables->map(function ($t) {
                    $check = $t->openCheck;

                    return [
                        'id' => $t->id,
                        'name' => $t->name,
                        'capacity' => $t->capacity,
                        'occupied' => $check !== null,
                        'check' => $check ? [
                            'id' => $check->id,
                            'number' => $check->number,
                            'covers' => $check->covers,
                            'opened_at' => $check->opened_at?->toIso8601String(),
                            'subtotal' => (float) $check->subtotal,
                        ] : null,
                    ];
                }),
            ];
        });

        return Inertia::render('Floor/Index', [
            'areas' => $payload,
        ]);
    }
}
