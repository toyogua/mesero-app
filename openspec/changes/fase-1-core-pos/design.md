# Design — Fase 1: Core POS

## Dominio

```
Area (1) ──── (N) Table (1) ──── (1) Check
                                      │
                              (N) CheckItem
                                      │
                              KitchenStation
```

## Modelos clave

| Modelo | PK | Notas |
|--------|----|-------|
| `Area` | ULID | Agrupa mesas (ej. "Terraza", "Salón") |
| `Table` | ULID | `status`: free/occupied/requested |
| `KitchenStation` | ULID | Destino de ítems (ej. "Cocina caliente", "Bar") |
| `MenuItem` | ULID | Pertenece a una KitchenStation, tiene precio y categoría |
| `Check` | ULID | Número secuencial, pertenece a Table + User (waiter) |
| `CheckItem` | ULID | Snapshot de nombre/precio al momento del pedido |
| `User` | ULID | role: admin/waiter/kitchen; pin nullable (solo waiters) |

## State machine de CheckItem

```
draft → ordered → preparing → ready → served
         │
         └→ cancelled  (también desde draft)
```

`CheckItemStatus::canTransitionTo(CheckItemStatus $target): bool`

## Cálculo de totales (Check::recalculate)

```
subtotal = Σ (price_snapshot + modifiers.sum) × quantity  (ítems no cancelados)
tax      = round(subtotal × iva_rate, 2)       # iva_rate = 0.12 por defecto
total    = subtotal + tax + tip
```

## Broadcasting

- Driver local: **Reverb** (WebSocket propio)
- Driver prod: **Ably** (DreamHost no puede correr Reverb)
- Canales: privados, autenticados via `routes/channels.php`
- Frontend: `useChannel(channel, event, callback)` composable + Inertia reload

## Archivos principales

```
app/Models/{Area,Table,KitchenStation,MenuItem,Check,CheckItem,User}.php
app/Http/Controllers/{FloorController,CheckController,CheckItemController,KitchenController}.php
app/Events/{CheckUpdated,FloorChanged,KitchenQueueChanged,ItemReady}.php
app/Enums/{CheckStatus,CheckItemStatus,UserRole}.php
resources/js/Pages/{Floor/Index,Checks/Show,Kitchen/Display}.vue
resources/js/composables/useChannel.js
```
