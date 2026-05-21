# Spec — Fase 2: Recetas BOM + Inventario

## Problema

Sin trazabilidad de ingredientes, el sistema no puede calcular food cost ni
alertar cuando un ingrediente está por agotarse.

## Alcance

- Modelo de recetas (Bill of Materials) por ítem de menú
- Deducción automática de stock cuando un ítem se marca como servido
- CRUD de ingredientes en panel admin
- Alertas de stock bajo

## Requisitos funcionales

### Ingredientes
- Campos: nombre, unidad (unit/kg/g/L/mL/portion), cantidad_en_stock,
  stock_mínimo, costo_unitario, activo
- Alerta visual cuando `quantity_on_hand <= minimum_stock`
- CRUD exclusivo para admins

### Recetas
- Un `MenuItem` puede tener N `RecipeItem` (BOM)
- Cada línea: ingrediente + cantidad usada por porción
- Si el ítem no tiene receta → se sirve sin deducción (no falla)

### Deducción automática
- Trigger: `CheckItemController::served()` al marcar ítem como servido
- Deducción = `quantity_used × item.quantity` (proporcional a cantidad pedida)
- Ejecutada en transacción DB
- Inyectado via `InventoryService`

## Restricciones

- Ingrediente sin receta → silencioso (no error)
- Deducción puede llevar stock a negativo (registra el hecho, no bloquea el servicio)
- Cancelación de ítem NO deduce stock

## Tests incluidos

`InventoryDeductionTest` (7 tests), `IngredientTest` (unit)
