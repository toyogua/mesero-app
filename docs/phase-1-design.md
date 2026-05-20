# Fase 1 — Mesas + Comandas (diseño de dominio)

> Esta es la **fuente de verdad** del modelo antes de escribir migraciones. Si algo del código contradice este doc, gana este doc (o lo actualizamos primero).

## Alcance de la Fase 1

**Sí incluye:**
- Áreas, mesas, estaciones de cocina, items de menú (catálogo)
- Comanda (check) con ciclo de vida completo
- Items de comanda con estado independiente (kitchen lifecycle)
- KDS básico: pantalla por estación que muestra items en cola
- Realtime: mesero envía → cocina ve aparecer; cocina marca listo → mesero ve aparecer
- Auth básico (usuarios con PIN/username, no email)

**NO incluye en esta fase** (se ven después):
- Recetas / descuento de inventario (Fase 2)
- Modificadores estructurados (Fase 3)
- FEL SAT (Fase 4)
- Dividir cuenta, propina, reportes (Fase 5)
- Multi-cuenta por mesa, take-out, delivery (post Fase 5)

## Entidades

### `Area`
Sección física: salón, terraza, barra, VIP. Solo agrupa mesas para vista de mesero.

| Campo | Tipo | Notas |
|---|---|---|
| id | ulid | |
| name | string | "Salón principal", "Terraza" |
| display_order | int | Orden en UI |
| active | bool | |

### `Table`
Mesa física. Pertenece a un Area. Estado de ocupación se deriva de check abierto, NO se almacena duplicado.

| Campo | Tipo | Notas |
|---|---|---|
| id | ulid | |
| area_id | fk → areas | |
| name | string | "Mesa 1", "VIP-3" |
| capacity | int | Comensales máximos sugeridos |
| active | bool | |

**Estado computado**: `free` (sin check open) / `occupied` (con check open). NO se persiste — siempre se calcula desde `checks`.

### `KitchenStation`
Estación física de preparación: cocina caliente, fría, bar, postres, panadería. Cada `MenuItem` rutea a UNA estación. Cada estación tiene un dispositivo viendo el KDS.

| Campo | Tipo | Notas |
|---|---|---|
| id | ulid | |
| name | string | "Cocina caliente" |
| code | string | "hot_kitchen" (canal broadcast) |
| display_order | int | |
| active | bool | |

### `MenuItem`
Catálogo del menú. Es el "qué se puede pedir".

| Campo | Tipo | Notas |
|---|---|---|
| id | ulid | |
| kitchen_station_id | fk → kitchen_stations | A dónde se cocina |
| name | string | "Pepián de pollo" |
| description | text | nullable |
| price | decimal(10,2) | Precio base sin IVA (lo agrega el sistema al calcular) |
| category | string | "platos_fuertes", "bebidas", "postres" |
| sku | string unique | nullable |
| active | bool | |

> El precio se guarda **sin IVA**. El IVA (12%) se calcula y desglosa al cerrar la cuenta. Decisión: trabajamos siempre con precios netos internamente, y a la hora de mostrar al cliente / facturar, se aplica el 12%.

### `Check` (la comanda)
La entidad central. Vive desde que el mesero abre la mesa hasta que cierra y cobra.

| Campo | Tipo | Notas |
|---|---|---|
| id | ulid | |
| number | string unique | "C-000001" — correlativo para humanos |
| table_id | fk → tables | nullable (para futuras barras/take-out) |
| waiter_user_id | fk → users | Quién la abrió |
| status | enum | open, closing, closed, void |
| covers | int | Cantidad de comensales |
| notes | text | nullable — notas generales |
| subtotal | decimal(10,2) | Suma de items netos |
| tax | decimal(10,2) | 12% IVA |
| tip | decimal(10,2) | Propina (default 0 hasta Fase 5) |
| total | decimal(10,2) | subtotal + tax + tip |
| opened_at | datetime | |
| closed_at | datetime | nullable |

**Estados:**
- `open`: mesero está armando + cocina está preparando. Default al crear.
- `closing`: mesero pidió cuenta → ya no se aceptan nuevos items. Esperando pago.
- `closed`: pagada. Inmutable.
- `void`: anulada (con permiso).

**Regla de negocio**: una mesa solo puede tener UN check `open|closing` a la vez. Constraint enforced en la lógica (no DB, porque table_id es nullable).

### `CheckItem`
Cada item pedido. Tiene SU PROPIO ciclo de vida en cocina, separado del estado del check.

| Campo | Tipo | Notas |
|---|---|---|
| id | ulid | |
| check_id | fk → checks | |
| menu_item_id | fk → menu_items | |
| kitchen_station_id | fk → kitchen_stations | Denormalizado al crear (snapshot) |
| name_snapshot | string | Nombre en el momento del pedido |
| price_snapshot | decimal(10,2) | Precio en el momento del pedido (inmutable) |
| quantity | int | |
| notes | string | "sin cebolla", "término medio" — texto libre en Fase 1 |
| status | enum | draft, ordered, preparing, ready, served, cancelled |
| sent_at | datetime | nullable — cuando pasó a `ordered` |
| ready_at | datetime | nullable |
| served_at | datetime | nullable |

**Estados (kitchen lifecycle):**
1. `draft`: mesero está armando, todavía no envió a cocina
2. `ordered`: enviado a cocina, aparece en KDS
3. `preparing`: cocinero la tomó (clickeó en KDS)
4. `ready`: lista para servir
5. `served`: entregada al cliente
6. `cancelled`: anulada antes de servir

**Por qué snapshots de name/price**: si cambia el menú mañana, las comandas viejas mantienen los precios y nombres con los que se vendieron. Audit trail inmutable.

## Reglas de negocio clave

1. **Un check abierto por mesa**: validación en `ChecksController@store` y `Table::canOpenCheck()`.
2. **Items en `draft` NO van a KDS**: se filtran al hacer broadcast.
3. **Transición `draft → ordered`**: dispara broadcast a canal `kitchen.{station_code}`.
4. **Transición `ready → served`**: la hace el mesero, no la cocina.
5. **Check no se puede cerrar** si hay items con status `draft`, `ordered`, `preparing` o `ready`. Solo cuando todos los items están `served` o `cancelled`.
6. **Recálculo de totales**: cada cambio en items recalcula subtotal/tax/total del check (en un Observer/Event de Eloquent).
7. **Cancelación de item**: solo permitida si está en `draft` u `ordered` (no `preparing` o más). Si está `preparing+`, se requiere flujo de "anulación con motivo" (Fase 5).

## Eventos broadcast

| Evento | Canal | Cuándo se dispara | Payload mínimo |
|---|---|---|---|
| `ItemsSentToKitchen` | private `kitchen.{station_code}` | Items pasan `draft → ordered` | check_id, items[] (id, name, qty, notes) |
| `ItemReady` | private `check.{check_id}` | Item pasa `preparing → ready` | item_id, name |
| `CheckUpdated` | private `check.{check_id}` | Cualquier mutación al check | check (resumen) |
| `TableOccupied` / `TableFreed` | presence `floor.{area_id}` | Mesa abre/cierra check | table_id, status |

## Concurrencia

- **Last-write-wins con broadcast**: dos meseros editando misma mesa = el último que guardó gana, pero los demás reciben el `CheckUpdated` y refrescan su vista. Sin locks pesimistas en Fase 1.
- En Fase 5 podría agregarse `locked_by_user_id` con TTL para evitar conflictos visibles.

## Auth

- Tabla `users` (la default de Laravel) con campos extra: `pin` (4-6 dígitos, hashed), `role` (waiter, kitchen, admin).
- Login web tradicional para admin (email + password).
- Login para meseros: pantalla con PIN numérico (más rápido en tablet).
- Middleware:
  - `auth` — cualquier user logueado
  - `role:waiter|admin` — para abrir/modificar comandas
  - `role:kitchen|admin` — para KDS y marcar ready

## Rutas principales (Inertia)

```
GET  /                         → Dashboard según role
GET  /floor                    → Tables/Index.vue (grid mesas por área)
GET  /checks/{check}           → Checks/Show.vue (detalle + agregar items)
POST /checks                   → Crear check (open mesa)
POST /checks/{check}/items     → Agregar item (draft)
POST /checks/{check}/send      → draft → ordered (broadcast a cocina)
PUT  /check-items/{item}/ready → preparing → ready
PUT  /check-items/{item}/served→ ready → served
POST /checks/{check}/close     → close cuenta (todos items served)

GET  /kitchen/{station}        → Kitchen/Display.vue (KDS por estación)
PUT  /check-items/{item}/take  → ordered → preparing
```

## Esquema visual (resumen)

```
Area ─< Table ─< Check ─< CheckItem >─ MenuItem >─ KitchenStation
                  │                                     │
                  └─ User (waiter)                      └─ (mismo) KitchenStation
```

## Próximos pasos (esta fase)

1. Migraciones (task #10)
2. Modelos + factories (task #11)
3. Auth + layouts (task #12)
4. Controllers + Inertia pages (task #13)
5. Eventos broadcast (task #14)
6. Tests Pest (task #15)
