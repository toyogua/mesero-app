# Design — Fase 6: CRUD Admin

## Decisiones clave

### PIN uniqueness con hash
El campo `users.pin` tiene cast `'hashed'`. La validación `unique:users,pin`
de Laravel NO funciona porque compara texto plano contra hash. Solución:
método privado `assertPinUnique()` que recorre usuarios y usa `Hash::check`.

Costo: O(N) por crear/editar usuario. Aceptable para restaurantes con
< 50 empleados. Si crece, migrar a un hash determinístico (HMAC con secret).

### Email obligatorio para todos los usuarios
La columna `users.email` es NOT NULL (default Laravel). Los meseros con PIN
no tienen email "real" → el controller genera uno sintético
(`{slug}.{uniqid}@local`) para satisfacer la constraint. Si se quisiera
hacer email nullable habría que migrar.

### Soft-delete por bandera `active`
Ninguna entidad se borra físicamente. `destroy` solo pone `active = false`.
Esto preserva la integridad referencial (FelInvoice, CheckItem, etc.) y
permite reactivar usuarios/mesas/ítems sin perder histórico.

### Validación de mesa ocupada
Antes de desactivar:
- `Area`: chequea si tiene mesas con `openCheck`
- `Table`: usa el método `isOccupied()` que viste en Fase 1

## Rutas

| Método | URI | Controlador |
|--------|-----|-------------|
| GET    | /admin/menu-items                | MenuItemController@index |
| POST   | /admin/menu-items                | MenuItemController@store |
| PATCH  | /admin/menu-items/{menuItem}     | MenuItemController@update |
| DELETE | /admin/menu-items/{menuItem}     | MenuItemController@destroy |
| GET    | /admin/areas                     | AreaController@index |
| POST   | /admin/areas                     | AreaController@store |
| PATCH  | /admin/areas/{area}              | AreaController@update |
| DELETE | /admin/areas/{area}              | AreaController@destroy |
| GET    | /admin/tables                    | TableController@index |
| POST   | /admin/tables                    | TableController@store |
| PATCH  | /admin/tables/{table}            | TableController@update |
| DELETE | /admin/tables/{table}            | TableController@destroy |
| GET    | /admin/users                     | UserController@index |
| POST   | /admin/users                     | UserController@store |
| PATCH  | /admin/users/{user}              | UserController@update |
| DELETE | /admin/users/{user}              | UserController@destroy |

Todas con middleware `auth` + `role:admin`.

## DemoSeeder

Extiende el seeder existente con:
- 12 ingredientes con `cost_price` (pollo, res, pescado, vegetales)
- 12 recetas BOM mapeando platos a ingredientes
- 3 grupos de modificadores: Término de cocción (single, required),
  Extras (multi, optional), Tamaño (single, required)
- Asignación de grupos a ítems específicos (lomo → cocción + extras, etc.)
