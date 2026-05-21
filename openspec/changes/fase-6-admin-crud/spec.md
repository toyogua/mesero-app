# Spec — Fase 6: CRUD Admin (Menú, Mesas, Usuarios, Seeder)

## Problema

Sin UI de administración para entidades base, el sistema no puede ser
configurado por un operador sin acceso a la base de datos directamente.

## Alcance

- CRUD de ítems de menú (con asignación de estación y categoría)
- CRUD de áreas y mesas
- CRUD de usuarios (meseros, cocina, admins)
- Seeder de demo con datos realistas para un restaurante guatemalteco

## Requisitos funcionales

### Menú (`/admin/menu-items`)
- Crear / editar / activar / desactivar ítems
- Campos: nombre, descripción, precio, categoría, estación de cocina, SKU, activo
- Categorías predefinidas: entradas, platos_fuertes, bebidas, postres
- No se borra físicamente → `active = false`
- Desde la misma vista, link a la receta del ítem

### Áreas y Mesas (`/admin/areas`, `/admin/tables`)
- CRUD de áreas: nombre, activo
- CRUD de mesas: nombre, área, capacidad, activo
- No eliminar mesa con comandas abiertas

### Usuarios (`/admin/users`)
- Crear mesero (nombre, PIN de 4 dígitos, rol waiter/kitchen)
- Crear admin (nombre, email, password)
- Activar / desactivar usuarios
- No se puede desactivar el propio usuario logueado

### Seeder de demo
- 2 áreas: "Salón principal", "Terraza"
- 8 mesas distribuidas entre áreas
- 3 estaciones: "Cocina caliente", "Bar", "Postres"
- 15+ ítems de menú con precios en GTQ
- 3 grupos de modificadores comunes
- 1 admin, 2 meseros, 1 cocinero
- Ingredientes básicos con stock y costo

## Restricciones

- PIN debe ser único entre usuarios activos
- No se puede cambiar el rol de admin a waiter (ni viceversa) por UI
- Seeder solo se ejecuta en entornos no-production

## Tests

- `AdminCrudTest`: CRUD menú, áreas, mesas, usuarios
- Validaciones de PIN único, mesa con comandas, auto-desactivación
