<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(): Response
    {
        $categories = MenuItem::active()
            ->with(['modifierGroups' => fn ($q) => $q->with(['options' => fn ($q) => $q->where('active', true)->orderBy('name')])])
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category')
            ->map(fn ($items, $category) => [
                'category' => $category,
                'items'    => $items->map(fn ($i) => [
                    'id'          => $i->id,
                    'name'        => $i->name,
                    'description' => $i->description,
                    'price'       => (float) $i->price,
                    'modifier_groups' => $i->modifierGroups->map(fn ($g) => [
                        'name'    => $g->name,
                        'options' => $g->options->map(fn ($o) => [
                            'name'        => $o->name,
                            'price_delta' => (float) $o->price_delta,
                        ])->values(),
                    ]),
                ])->values(),
            ])
            ->values();

        $restaurantName = config('restaurant.name', 'Restaurante');

        return Inertia::render('Menu/Index', [
            'categories'      => $categories,
            'restaurant_name' => $restaurantName,
        ]);
    }
}
