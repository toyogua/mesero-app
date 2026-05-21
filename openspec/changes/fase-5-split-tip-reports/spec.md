# Spec — Fase 5: División de Cuenta, Propina y Reportes

## Alcance

- Propina configurable (% o monto fijo) sobre el subtotal
- División de cuenta en N partes iguales, con registro de pago por parte
- Panel de reportes para el administrador con métricas operativas

## Requisitos funcionales

### Propina
- `PATCH /checks/{check}/tip` con `amount` (numérico, >= 0)
- Persiste en columna `tip` del check
- Dispara `recalculate()` → `total = subtotal + tax + tip`
- UI: botones de acceso rápido 10% / 15% / 20% + campo manual

### División de cuenta
- `POST /checks/{check}/splits { parts: N }` → N partes iguales (resto en última)
- Alternativa: `{ splits: [{label, amount},...] }` para montos custom
- `PATCH /check-splits/{split}/pay { method: cash|card|transfer }` → marca como pagado
- `DELETE /checks/{check}/splits` → elimina todas las partes y permite recrear
- Solo en comandas abiertas

### Reportes admin (`GET /admin/reports`)
- Filtro por rango de fechas (`from`, `to`), default: hoy
- **Resumen**: total_checks, subtotal, IVA, propinas, revenue total, ticket promedio
- **Top 15 productos**: por cantidad vendida, con revenue
- **Ventas por día**: checks y total por fecha
- **Food cost estimado**: requiere `cost_price` en ingredientes y receta configurada
- **Rotación de mesa**: tiempo promedio por comanda (opened_at → closed_at)

## Restricciones

- `TIMESTAMPDIFF` es MySQL-only → fallback `julianday` para SQLite (tests)
- Food cost solo incluye ítems con receta Y costo de ingrediente > 0
- Splits solo en comandas `open`

## Tests incluidos

`Phase5Test` (10 tests): tip set, tip en total, tip validación, split equal, split pay, split reset,
reports admin acceso, resumen counts, food cost cálculo, non-admin forbidden
