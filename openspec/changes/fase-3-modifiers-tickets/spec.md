# Spec — Fase 3: Modificadores Estructurados + Tickets

## Problema

Sin modificadores, un "Burger con queso extra" requería un ítem de menú
separado. Los tickets de cocina no existían.

## Alcance

- Grupos de modificadores configurables (ej. "Término de cocción", "Extras")
- Opciones con delta de precio (ej. "Queso extra +Q5.00")
- Validación server-side de selecciones requeridas y límites de selección
- Tickets de impresión para sala (80mm) y para cocina por estación
- Los modificadores aparecen en el ticket FEL (fase 4) como parte de la descripción

## Requisitos funcionales

### ModifierGroup
- `selection_type`: `single` (radio) o `multi` (checkbox)
- `required`: boolean — si es true, el mesero debe seleccionar al menos una opción
- `display_order`: orden de aparición en la UI
- Asignado a N `MenuItem` via tabla pivot `menu_item_modifier_group`

### ModifierOption
- Pertenece a un `ModifierGroup`
- `price_delta`: decimal, puede ser negativo (descuento)
- `active`: para desactivar sin borrar

### CheckItemModifier
- Snapshot al momento del pedido: `name_snapshot`, `price_snapshot`
- Desacoplado de `ModifierOption` original (puede cambiar después)

### Validación server-side (assertModifiersValid)
1. Opciones seleccionadas deben pertenecer a grupos del ítem
2. Grupos `required` deben tener al menos 1 selección
3. Grupos `single` no pueden tener más de 1 selección

### Tickets de impresión
- Rutas Blade (no Inertia) → `window.print()` al cargar
- `GET /checks/{check}/ticket` → ticket de sala (80mm, IVA, FEL UUID si aplica)
- `GET /kitchen/stations/{station}/ticket` → ticket por estación

## Restricciones

- Validación en servidor, no solo en UI (el frontend puede ser bypassado)
- Modificadores afectan `Check::recalculate()` → `(price_snapshot + modifiers.sum) × qty`

## Tests incluidos

`ModifierTest` (7 tests): almacenamiento, precio en totales, requeridos, single-select, ticket Blade
