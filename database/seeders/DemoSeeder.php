<?php

namespace Database\Seeders;

use App\Enums\CheckItemStatus;
use App\Enums\CheckStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\KitchenStation;
use App\Models\MenuItem;
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
    }
}
