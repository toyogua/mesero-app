<?php

namespace Database\Seeders;

use App\Enums\CheckItemStatus;
use App\Enums\CheckStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\Ingredient;
use App\Models\KitchenStation;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\RecipeItem;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── Usuarios ──────────────────────────────
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@mesero.app',
            'password' => Hash::make('secret123'),
            'pin' => Hash::make('9999'),
            'role' => UserRole::Admin->value,
            'active' => true,
        ]);

        $waiters = collect([
            ['name' => 'María López', 'pin' => '1111'],
            ['name' => 'Carlos Pérez', 'pin' => '2222'],
            ['name' => 'Sofía Méndez', 'pin' => '3333'],
            ['name' => 'Diego Ramírez', 'pin' => '4444'],
        ])->map(fn ($w) => User::create([
            'name' => $w['name'],
            'email' => str_replace(' ', '.', strtolower($w['name'])).'@mesero.app',
            'password' => Hash::make('secret123'),
            'pin' => Hash::make($w['pin']),
            'role' => UserRole::Waiter->value,
            'active' => true,
        ]));

        User::create([
            'name' => 'Cocina',
            'email' => 'cocina@mesero.app',
            'password' => Hash::make('secret123'),
            'pin' => Hash::make('5555'),
            'role' => UserRole::Kitchen->value,
            'active' => true,
        ]);

        User::create([
            'name' => 'Caja',
            'email' => 'caja@mesero.app',
            'password' => Hash::make('secret123'),
            'pin' => Hash::make('6666'),
            'role' => UserRole::Cashier->value,
            'active' => true,
        ]);

        // ── Estaciones de cocina ──────────────────
        $stations = collect([
            ['name' => 'Cocina caliente', 'code' => 'hot_kitchen', 'display_order' => 1],
            ['name' => 'Cocina fría', 'code' => 'cold_kitchen', 'display_order' => 2],
            ['name' => 'Bar', 'code' => 'bar', 'display_order' => 3],
            ['name' => 'Postres', 'code' => 'desserts', 'display_order' => 4],
        ])->map(fn ($s) => KitchenStation::create($s + ['active' => true]));

        $hot = $stations->firstWhere('code', 'hot_kitchen');
        $cold = $stations->firstWhere('code', 'cold_kitchen');
        $bar = $stations->firstWhere('code', 'bar');
        $desserts = $stations->firstWhere('code', 'desserts');

        // ── Menú (precios netos, sin IVA) ─────────
        $menu = collect([
            // Entradas (cocina fría)
            ['name' => 'Guacamol con totopos', 'price' => 45.00, 'category' => 'entradas', 'station' => $cold],
            ['name' => 'Ceviche de camarón', 'price' => 75.00, 'category' => 'entradas', 'station' => $cold],
            ['name' => 'Tabla de quesos', 'price' => 95.00, 'category' => 'entradas', 'station' => $cold],

            // Platos fuertes (cocina caliente)
            ['name' => 'Pepián de pollo', 'price' => 85.00, 'category' => 'platos_fuertes', 'station' => $hot],
            ['name' => 'Kak-ik', 'price' => 95.00, 'category' => 'platos_fuertes', 'station' => $hot],
            ['name' => 'Hilachas', 'price' => 80.00, 'category' => 'platos_fuertes', 'station' => $hot],
            ['name' => 'Tamales colorados (3u)', 'price' => 55.00, 'category' => 'platos_fuertes', 'station' => $hot],
            ['name' => 'Chiles rellenos', 'price' => 70.00, 'category' => 'platos_fuertes', 'station' => $hot],
            ['name' => 'Lomo a la parrilla', 'price' => 145.00, 'category' => 'platos_fuertes', 'station' => $hot],
            ['name' => 'Pescado a la plancha', 'price' => 120.00, 'category' => 'platos_fuertes', 'station' => $hot],

            // Bebidas (bar)
            ['name' => 'Limonada con chía', 'price' => 25.00, 'category' => 'bebidas', 'station' => $bar],
            ['name' => 'Refresco', 'price' => 18.00, 'category' => 'bebidas', 'station' => $bar],
            ['name' => 'Cerveza Gallo', 'price' => 28.00, 'category' => 'bebidas', 'station' => $bar],
            ['name' => 'Café americano', 'price' => 22.00, 'category' => 'bebidas', 'station' => $bar],
            ['name' => 'Agua mineral', 'price' => 15.00, 'category' => 'bebidas', 'station' => $bar],

            // Postres
            ['name' => 'Rellenitos de plátano', 'price' => 35.00, 'category' => 'postres', 'station' => $desserts],
            ['name' => 'Mole de plátano', 'price' => 40.00, 'category' => 'postres', 'station' => $desserts],
            ['name' => 'Flan de coco', 'price' => 38.00, 'category' => 'postres', 'station' => $desserts],
        ])->map(fn ($m) => MenuItem::create([
            'kitchen_station_id' => $m['station']->id,
            'name' => $m['name'],
            'price' => $m['price'],
            'category' => $m['category'],
            'active' => true,
        ]));

        // ── Áreas + mesas ─────────────────────────
        $salon = Area::create(['name' => 'Salón principal', 'display_order' => 1, 'active' => true]);
        $terraza = Area::create(['name' => 'Terraza', 'display_order' => 2, 'active' => true]);
        $vip = Area::create(['name' => 'VIP', 'display_order' => 3, 'active' => true]);

        $tables = collect();
        foreach (range(1, 10) as $i) {
            $tables->push(Table::create([
                'area_id' => $salon->id,
                'name' => "Mesa $i",
                'capacity' => $i % 3 === 0 ? 6 : 4,
                'active' => true,
            ]));
        }
        foreach (range(1, 6) as $i) {
            $tables->push(Table::create([
                'area_id' => $terraza->id,
                'name' => "Terr-$i",
                'capacity' => 4,
                'active' => true,
            ]));
        }
        foreach (range(1, 3) as $i) {
            $tables->push(Table::create([
                'area_id' => $vip->id,
                'name' => "VIP-$i",
                'capacity' => 8,
                'active' => true,
            ]));
        }

        // ── Comandas abiertas en algunas mesas ────
        $tablesWithChecks = $tables->random(5);
        $checkNumber = 1;

        foreach ($tablesWithChecks as $idx => $table) {
            $waiter = $waiters->random();
            $check = Check::create([
                'number' => 'C-'.str_pad((string) $checkNumber++, 6, '0', STR_PAD_LEFT),
                'table_id' => $table->id,
                'waiter_user_id' => $waiter->id,
                'status' => CheckStatus::Open->value,
                'covers' => fake()->numberBetween(2, 6),
                'opened_at' => now()->subMinutes(fake()->numberBetween(5, 95)),
            ]);

            $itemCount = fake()->numberBetween(2, 5);
            $picked = $menu->random($itemCount);

            foreach ($picked as $m) {
                $status = fake()->randomElement([
                    CheckItemStatus::Ordered,
                    CheckItemStatus::Preparing,
                    CheckItemStatus::Ready,
                    CheckItemStatus::Served,
                ]);

                CheckItem::create([
                    'check_id' => $check->id,
                    'menu_item_id' => $m->id,
                    'kitchen_station_id' => $m->kitchen_station_id,
                    'name_snapshot' => $m->name,
                    'price_snapshot' => $m->price,
                    'quantity' => fake()->numberBetween(1, 2),
                    'status' => $status->value,
                    'sent_at' => $status !== CheckItemStatus::Draft ? $check->opened_at->copy()->addMinutes(2) : null,
                    'ready_at' => in_array($status, [CheckItemStatus::Ready, CheckItemStatus::Served]) ? $check->opened_at->copy()->addMinutes(15) : null,
                    'served_at' => $status === CheckItemStatus::Served ? $check->opened_at->copy()->addMinutes(18) : null,
                ]);
            }

            $check->recalculate();
        }

        // ── Ingredientes con costo ────────────────
        $ingredients = collect([
            ['name' => 'Pollo',        'unit' => 'kg',      'qty' => 25,  'min' => 5,  'cost' => 28.00],
            ['name' => 'Res',          'unit' => 'kg',      'qty' => 18,  'min' => 5,  'cost' => 55.00],
            ['name' => 'Pescado',      'unit' => 'kg',      'qty' => 12,  'min' => 3,  'cost' => 65.00],
            ['name' => 'Camarón',      'unit' => 'kg',      'qty' => 8,   'min' => 2,  'cost' => 85.00],
            ['name' => 'Tortillas',    'unit' => 'unit',    'qty' => 300, 'min' => 50, 'cost' => 0.75],
            ['name' => 'Aguacate',     'unit' => 'unit',    'qty' => 40,  'min' => 10, 'cost' => 4.50],
            ['name' => 'Tomate',       'unit' => 'kg',      'qty' => 15,  'min' => 3,  'cost' => 8.00],
            ['name' => 'Cebolla',      'unit' => 'kg',      'qty' => 10,  'min' => 2,  'cost' => 6.00],
            ['name' => 'Chiles',       'unit' => 'kg',      'qty' => 5,   'min' => 1,  'cost' => 12.00],
            ['name' => 'Limón',        'unit' => 'unit',    'qty' => 80,  'min' => 20, 'cost' => 0.50],
            ['name' => 'Plátano',      'unit' => 'unit',    'qty' => 50,  'min' => 10, 'cost' => 1.50],
            ['name' => 'Queso',        'unit' => 'kg',      'qty' => 6,   'min' => 1,  'cost' => 45.00],
        ])->mapWithKeys(fn ($i) => [
            $i['name'] => Ingredient::create([
                'name'             => $i['name'],
                'unit'             => $i['unit'],
                'quantity_on_hand' => $i['qty'],
                'minimum_stock'    => $i['min'],
                'cost_price'       => $i['cost'],
                'active'           => true,
            ]),
        ]);

        // ── Recetas BOM ──────────────────────────
        $recipes = [
            'Pepián de pollo'        => [['Pollo', 0.3], ['Tomate', 0.15], ['Cebolla', 0.05], ['Chiles', 0.02]],
            'Kak-ik'                 => [['Pollo', 0.35], ['Tomate', 0.1], ['Chiles', 0.03]],
            'Hilachas'               => [['Res', 0.25], ['Tomate', 0.1], ['Cebolla', 0.05]],
            'Lomo a la parrilla'     => [['Res', 0.3], ['Tortillas', 4]],
            'Pescado a la plancha'   => [['Pescado', 0.3], ['Limón', 2]],
            'Ceviche de camarón'     => [['Camarón', 0.2], ['Limón', 4], ['Tomate', 0.1], ['Cebolla', 0.05]],
            'Guacamol con totopos'   => [['Aguacate', 2], ['Tomate', 0.08], ['Tortillas', 6]],
            'Tabla de quesos'        => [['Queso', 0.15]],
            'Tamales colorados (3u)' => [['Pollo', 0.15], ['Tomate', 0.1]],
            'Chiles rellenos'        => [['Chiles', 0.1], ['Queso', 0.08], ['Tomate', 0.1]],
            'Rellenitos de plátano'  => [['Plátano', 3]],
            'Mole de plátano'        => [['Plátano', 3], ['Queso', 0.05]],
        ];

        foreach ($recipes as $itemName => $lines) {
            $menuItem = $menu->firstWhere('name', $itemName);
            if (!$menuItem) continue;
            foreach ($lines as [$ingName, $qty]) {
                if (!isset($ingredients[$ingName])) continue;
                RecipeItem::create([
                    'menu_item_id'   => $menuItem->id,
                    'ingredient_id'  => $ingredients[$ingName]->id,
                    'quantity_used'  => $qty,
                ]);
            }
        }

        // ── Modifier groups + options ────────────
        $coccion = ModifierGroup::create(['name' => 'Término de cocción', 'selection_type' => 'single', 'required' => true, 'display_order' => 1]);
        foreach (['Término medio', 'Tres cuartos', 'Bien cocido'] as $i => $name) {
            ModifierOption::create(['modifier_group_id' => $coccion->id, 'name' => $name, 'price_delta' => 0, 'display_order' => $i, 'active' => true]);
        }

        $extras = ModifierGroup::create(['name' => 'Extras', 'selection_type' => 'multi', 'required' => false, 'display_order' => 2]);
        foreach ([['Queso extra', 8.00], ['Aguacate extra', 6.00], ['Tortillas extras (3u)', 4.00]] as $i => [$name, $price]) {
            ModifierOption::create(['modifier_group_id' => $extras->id, 'name' => $name, 'price_delta' => $price, 'display_order' => $i, 'active' => true]);
        }

        $tamano = ModifierGroup::create(['name' => 'Tamaño', 'selection_type' => 'single', 'required' => true, 'display_order' => 1]);
        foreach ([['Pequeño', -5.00], ['Mediano', 0.00], ['Grande', 8.00]] as $i => [$name, $price]) {
            ModifierOption::create(['modifier_group_id' => $tamano->id, 'name' => $name, 'price_delta' => $price, 'display_order' => $i, 'active' => true]);
        }

        // Asignar grupos a ítems
        $lomo = $menu->firstWhere('name', 'Lomo a la parrilla');
        if ($lomo) $lomo->modifierGroups()->attach([$coccion->id => ['display_order' => 1], $extras->id => ['display_order' => 2]]);

        $pepian = $menu->firstWhere('name', 'Pepián de pollo');
        if ($pepian) $pepian->modifierGroups()->attach([$extras->id => ['display_order' => 1]]);

        $limonada = $menu->firstWhere('name', 'Limonada con chía');
        if ($limonada) $limonada->modifierGroups()->attach([$tamano->id => ['display_order' => 1]]);
    }
}
