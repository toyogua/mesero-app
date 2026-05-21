# Spec — Fase 1: Core POS

## Problema

Sistema legacy en CodeIgniter 3 sin soporte nativo para realtime. El flujo
mesero → cocina requería recargas manuales. Se necesitaba un sistema nuevo
con estado en tiempo real entre sala y cocina.

## Alcance

- Autenticación de meseros por PIN (sin teclado) y admins por email/password
- Gestión de áreas y mesas con estado de ocupación
- Comandas (checks) con ciclo de vida completo
- Kitchen Display System (KDS) con actualizaciones en tiempo real
- Transiciones de estado por ítems individuales

## Requisitos funcionales

### Auth
- Meseros se autentican con PIN numérico (UX de tablet sin teclado)
- Admins se autentican con email + password
- Roles: `admin`, `waiter`, `kitchen`
- Usuarios inactivos no pueden autenticarse

### Floor (sala)
- Vista de áreas con sus mesas
- Cada mesa muestra estado: libre / ocupada / cuenta solicitada
- Abrir comanda en una mesa libre
- Si ya tiene comanda abierta → redirige a ella

### Comandas (checks)
- Numeración secuencial con prefijo configurable (ej. `CMD-0001`)
- Estados: `open` → `closed` (solo cuando todos los ítems están servidos o cancelados)
- Ítems con estado propio: `draft` → `ordered` → `preparing` → `ready` → `served`
- Cancelación de ítems en `draft` o `ordered`
- Cálculo automático: subtotal, IVA 12%, total

### KDS (Kitchen Display)
- Vista agrupada por estación de cocina
- Solo muestra ítems en estados activos (`ordered`, `preparing`, `ready`)
- Mesero puede marcar ítems como servidos desde la comanda

### Realtime
- `CheckUpdated` → canal `private-check.{id}` (Inertia reload selectivo)
- `FloorChanged` → canal `private-floor.{area_id}` y `private-floor.all`
- `KitchenQueueChanged` → canal `private-kitchen.{station_code}`

## Restricciones

- No se puede cerrar una comanda con ítems pendientes
- No se puede saltar estados en las transiciones de ítems
- Usuarios inactivos bloqueados en middleware

## Tests incluidos

`AuthFlowTest`, `CheckLifecycleTest`, `FloorViewTest`, `CheckItemStatusTest`
