# Demo — Credenciales y datos de prueba

## Setup inicial

```bash
php artisan migrate:fresh --seed
php artisan serve
```

---

## Usuarios

### Admin
- **URL login**: `/admin/login`
- **Email**: `admin@mesero.app`
- **Password**: `secret123`
- **PIN**: `9999`

### Meseros
- **URL login**: `/`  (login con PIN)

| Nombre | PIN |
|--------|-----|
| María López | 1111 |
| Carlos Pérez | 2222 |
| Sofía Méndez | 3333 |
| Diego Ramírez | 4444 |

### Cocina
| Nombre | PIN |
|--------|-----|
| Cocina | 5555 |

### Cajero
| Nombre | PIN | Acceso |
|--------|-----|--------|
| Caja | 6666 | Salón (solo lectura) + Cierre de caja |

---

## Datos de demo cargados

### Áreas y mesas
| Área | Mesas |
|------|-------|
| Salón principal | Mesa 1 – Mesa 10 (cap. 4–6) |
| Terraza | Terr-1 – Terr-6 (cap. 4) |
| VIP | VIP-1 – VIP-3 (cap. 8) |

### Estaciones de cocina
| Nombre | Código |
|--------|--------|
| Cocina caliente | `hot_kitchen` |
| Cocina fría | `cold_kitchen` |
| Bar | `bar` |
| Postres | `desserts` |

### Menú (precios netos, IVA 12% se agrega al cerrar)
| Categoría | Platillo | Precio |
|-----------|----------|--------|
| Entradas | Guacamol con totopos | Q 45.00 |
| Entradas | Ceviche de camarón | Q 75.00 |
| Entradas | Tabla de quesos | Q 95.00 |
| Platos fuertes | Pepián de pollo | Q 85.00 |
| Platos fuertes | Kak-ik | Q 95.00 |
| Platos fuertes | Hilachas | Q 80.00 |
| Platos fuertes | Tamales colorados (3u) | Q 55.00 |
| Platos fuertes | Chiles rellenos | Q 70.00 |
| Platos fuertes | Lomo a la parrilla | Q 145.00 |
| Platos fuertes | Pescado a la plancha | Q 120.00 |
| Bebidas | Limonada con chía | Q 25.00 |
| Bebidas | Refresco | Q 18.00 |
| Bebidas | Cerveza Gallo | Q 28.00 |
| Bebidas | Café americano | Q 22.00 |
| Bebidas | Agua mineral | Q 15.00 |
| Postres | Rellenitos de plátano | Q 35.00 |
| Postres | Mole de plátano | Q 40.00 |
| Postres | Flan de coco | Q 38.00 |

### Modificadores disponibles
| Grupo | Tipo | Aplica a |
|-------|------|----------|
| Término de cocción | single / requerido | Lomo a la parrilla |
| Extras (queso, aguacate, tortillas) | multi / opcional | Lomo, Pepián |
| Tamaño (pequeño/mediano/grande) | single / requerido | Limonada con chía |

### Estado al iniciar
- 5 mesas aleatorias tendrán comandas abiertas con ítems en distintos estados (ordered/preparing/ready/served)

### FEL — NIT de prueba
| Campo | Valor |
|-------|-------|
| NIT sin especificar | `CF` / `CONSUMIDOR FINAL` |
| NIT empresa ejemplo | `1234567-8` |

---

## Menú público (sin login)
```
/menu
```
