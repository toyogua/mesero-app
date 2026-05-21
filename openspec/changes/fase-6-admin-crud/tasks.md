# Tasks — Fase 6: CRUD Admin

- [x] `MenuItemController` (index/store/update/destroy)
- [x] `AreaController` con validación de mesas ocupadas
- [x] `TableController` con validación de comanda abierta
- [x] `UserController` con PIN uniqueness via Hash::check + auto-email para PINs
- [x] Rutas registradas bajo `/admin/*` con middleware `role:admin`
- [x] Vue: `Admin/MenuItems/Index.vue` con filtros por categoría
- [x] Vue: `Admin/Areas/Index.vue` con CRUD inline
- [x] Vue: `Admin/Tables/Index.vue` agrupado por área
- [x] Vue: `Admin/Users/Index.vue` con formulario adaptativo PIN/email
- [x] DemoSeeder extendido: ingredientes + recetas + modificadores
- [x] `AdminCrudTest` (11 tests): CRUD básico, validaciones, role guard
- [x] Suite completa pasando (64 tests, 282 asserts)

## Pendiente para próximas fases

- [ ] Link a recetas desde el menú admin (ya existe el endpoint `/admin/menu-items/{id}/recipe`)
- [ ] Búsqueda/paginación cuando crezcan los catálogos
- [ ] Historial de cambios (audit log) de quién creó/modificó qué
