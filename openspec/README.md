# OpenSpec — mesero-app

Documentación estructurada del sistema de POS para restaurantes.  
Cada carpeta en `changes/` corresponde a una fase o cambio significativo.

## Stack

- **Backend**: Laravel 12, PHP 8.2, MySQL (prod) / SQLite (tests)
- **Frontend**: Vue 3 + Inertia.js (monolito SPA, sin API separada)
- **CSS**: Tailwind v4 con design tokens OKLCH
- **Queue**: Redis + Reverb (Docker local) / Ably + sync queue (DreamHost)
- **PKs**: ULIDs en todas las entidades de dominio

## Fases completadas

| Fase | Descripción | Estado |
|------|-------------|--------|
| [fase-1](changes/fase-1-core-pos/) | Core POS: mesas, comandas, KDS, auth, realtime | ✅ |
| [fase-2](changes/fase-2-recipes-inventory/) | Recetas BOM + deducción automática de inventario | ✅ |
| [fase-3](changes/fase-3-modifiers-tickets/) | Modificadores estructurados + tickets de impresión | ✅ |
| [fase-4](changes/fase-4-fel-sat/) | Facturación electrónica FEL SAT Guatemala | ✅ |
| [fase-5](changes/fase-5-split-tip-reports/) | División de cuenta, propina, reportes | ✅ |
| [fase-6](changes/fase-6-admin-crud/) | CRUD admin: menú, mesas, usuarios, seeder | 🔄 |

## Convenciones clave

- **IVA Guatemala**: 12% sobre subtotal. `MontoGravable = Total / 1.12` en FEL XML.
- **State machine**: `CheckItemStatus::canTransitionTo()` — transiciones solo hacia adelante.
- **Role guard**: middleware `role:admin` en todas las rutas `/admin/*`.
- **Dual-mode**: mismo código corre en Docker (Reverb+Redis) y DreamHost (sync+Ably), controlado por `.env`.
- **Tests**: SQLite `:memory:` con `RefreshDatabase`. Expresiones SQL driver-aware donde aplica.
