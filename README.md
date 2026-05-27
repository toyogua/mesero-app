# mesero-app

POS para restaurantes en Guatemala — comandas en tiempo real, control de inventario por recetas, mesas, modificadores, FEL SAT.

**Stack**: Laravel 12 · PHP 8.2 · Vue 3 + Inertia · MySQL · Tailwind · Realtime dual (Reverb local / Ably producción).

**Tenancy**: single-tenant. Cada restaurante = una instalación con su propia base de datos.

---

## Modos de despliegue

| Modo | Cuándo | WebSocket | Cache/Queue | Frontend build |
|---|---|---|---|---|
| **Local / On-premise (Docker)** | Desarrollo, restaurante con servidor propio | Reverb (self-hosted) | Redis + Horizon | `npm run dev` (HMR) |
| **Shared hosting (DreamHost)** | Despliegue cliente típico | Ably (SaaS, plan gratis 6M msg/mes) | `database` driver + cron | Build local, subir `public/build/` |

El código es **el mismo** en ambos modos. Cambia solo `.env` y el deploy.

---

## Setup local con Docker

Requisitos: Docker Desktop, Node 20+, Composer 2.

```bash
git clone <repo-url> mesero-app && cd mesero-app
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
npm install
npm run dev
```

Abrir <http://localhost:8080>.

Servicios expuestos:

| Puerto | Servicio |
|---|---|
| 8080 | Nginx (la app) |
| 3306 | MySQL |
| 6379 | Redis |
| 8090 | Reverb WebSocket |
| 8025 | Mailpit UI (mails de prueba) |

Variables `.env` clave para modo local:

```
DB_CONNECTION=mysql
DB_HOST=mysql
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=reverb
REVERB_HOST=localhost
REVERB_PORT=8090
```

---

## Despliegue en DreamHost (shared hosting)

DreamHost shared **no permite** procesos largos, Docker, Redis, ni Node en el server. Workaround:

### 1. Pre-build local

En tu máquina:
```bash
npm run build           # genera public/build/
composer install --no-dev --optimize-autoloader
```

### 2. Subir por SFTP / rsync

```bash
rsync -avz --exclude='.env' --exclude='node_modules' --exclude='.git' \
      --exclude='tests' --exclude='database/database.sqlite' \
      ./ user@hosting.dreamhost.com:~/mesero/
```

### 3. `.env` en el server

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://restaurante.com

DB_CONNECTION=mysql
DB_HOST=mysql.midominio.com
DB_DATABASE=cliente_mesero
DB_USERNAME=cliente_mesero
DB_PASSWORD=...

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=ably

ABLY_KEY=...
VITE_ABLY_PUBLIC_KEY=...
```

### 4. Migrar DB

```bash
ssh user@hosting "cd ~/mesero && php artisan migrate --force"
ssh user@hosting "cd ~/mesero && php artisan config:cache && php artisan route:cache"
```

### 5. Cron (reemplaza daemons)

En el panel DreamHost → Cron Jobs:

```cron
* * * * * cd ~/mesero && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd ~/mesero && php artisan queue:work --stop-when-empty --tries=3 --timeout=50 >> /dev/null 2>&1
```

El segundo arranca un worker cada minuto, procesa pendientes, muere. Para FEL al SAT: si está caído, reintenta sin perder facturas.

### 6. WebSocket via Ably

1. Crear cuenta en <https://ably.com> (plan gratis: 6M msg/mes, 200 conexiones).
2. Crear una app, copiar API key.
3. Setear `ABLY_KEY` y `VITE_ABLY_PUBLIC_KEY` en `.env` del server.
4. Re-buildear assets local (con la VITE_ABLY_PUBLIC_KEY correcta) y volver a subir `public/build/`.

---

## Roadmap (fases)

1. **Fase 0** — Bootstrap (✅ esta versión)
2. **Fase 1** — Mesas + Comandas (estados open/sent_to_kitchen/served/closed)
3. **Fase 2** — Recetas (BOM) + descuento automático de inventario
4. **Fase 3** — Modificadores + áreas de impresión (cocina caliente/fría/bar)
5. **Fase 4** — FEL SAT Guatemala (certificadores: Infile, G&T, Megaprint)
6. **Fase 5** — Dividir cuenta, propina, reportes restauranteros

---

## Terminología de la industria

Conceptos implementados en este sistema con sus nombres técnicos en la industria de restaurantes y POS. Útil para demos, propuestas comerciales y onboarding de nuevos clientes.

---

### BOM — Bill of Materials
**Nombre local:** Receta / Ficha técnica

En manufactura se llama "lista de materiales". En restaurantes es la receta que define qué ingredientes y en qué cantidad consume cada plato del menú. Cuando un mesero marca un ítem como servido, el sistema descuenta automáticamente del inventario según esta receta — sin intervención manual. Si una hamburguesa lleva 150 g de carne molida, 1 bollo y 10 mL de salsa, esos tres ingredientes se descontarán en esas cantidades exactas con cada venta.

**Valor para el cliente:** control de costos real, sin depender de que alguien "acuerde" bajar el stock. El inventario refleja la realidad.

#### Ingrediente vs Ítem de menú — la distinción clave

El sistema separa deliberadamente dos conceptos que en la vida cotidiana suelen confundirse:

| Concepto | Qué es | Ejemplo |
|---|---|---|
| **Ingredient** | Lo que se guarda en bodega o nevera. Tiene stock, unidad de medida y mínimo. | `Coca Cola Lata` — 58 und en stock |
| **MenuItem** | Lo que aparece en el menú y el mesero puede ordenar. Tiene nombre, categoría y precio. | `Coca Cola` — Q10, categoría Bebidas |

Un `Ingredient` no aparece en el menú por sí solo. Un `MenuItem` no tiene stock propio. Los une la receta (BOM).

**Por qué esta separación importa:**

- El mismo ingrediente físico (`Agua purificada 600ml`) puede usarse en distintos ítems del menú con distintos precios o presentaciones.
- El inventario refleja la realidad de la bodega, no cómo se llama en la carta.
- Se puede cambiar el precio o el nombre del ítem del menú sin afectar el inventario.

#### Cómo hacer que un producto aparezca en el menú

Para que `Coca Cola Lata` aparezca en el menú de bebidas y descuente inventario al venderse, se necesitan **tres pasos**:

**Paso 1 — Crear el Ingrediente** (sección Inventario → Ingredientes)
```
Nombre:              Coca Cola Lata
Unidad de almacén:   und
Empaque:             Caja × 12
Stock mínimo:        24
Stock inicial:       58
```

**Paso 2 — Crear el Ítem de Menú** (sección Catálogos → Menú)
```
Nombre:    Coca Cola
Categoría: Bebidas
Precio:    Q10.00
Estado:    Activo
```

**Paso 3 — Crear la Receta BOM** (en el ítem de menú, sección Receta)
```
Ingrediente:       Coca Cola Lata
Cantidad usada:    1 und
```

Desde ese momento, cada vez que se marque una `Coca Cola` como servida, el sistema descuenta `1 und` de `Coca Cola Lata` del inventario automáticamente.

#### Reglas de cantidad en la receta según tipo de producto

La cantidad que se define en la receta depende de cómo se consume el producto:

| Tipo de producto | Cantidad en receta | Por qué |
|---|---|---|
| Coca Cola Lata | `1 und` | Se consume completa, una unidad por venta |
| Botella de agua 600ml | `1 und` | Se consume completa |
| Hamburguesa clásica (carne) | `0.150 kg` | Se usan 150g de un stock a granel |
| Hamburguesa clásica (bollo) | `1 und` | Un bollo completo por hamburguesa |
| Café americano (café molido) | `10 g` | 10 gramos de un saco de café |
| Limonada (limón) | `2 und` | 2 limones por preparación |

**Regla práctica:** si el producto llega empacado individualmente y se vende completo (lata, botella, fardo individual) → siempre `1 und`. Si es un ingrediente a granel que se mide o pesa para cada plato → la cantidad exacta en la unidad base del ingrediente.

#### Conversor de unidades inline

Al ingresar la cantidad en cualquier receta (BOM del ítem de menú o receta de una opción de modificador), el formulario muestra un conversor inline que permite escribir la cantidad en la unidad que uno conoce y obtener automáticamente el valor en la unidad del ingrediente.

**Cuándo aparece:** solo para unidades de peso (`g`, `kg`, `lb`, `oz`) y volumen (`mL`, `L`). Para `unit` y `portion` no aparece — no tiene sentido convertir unidades enteras.

**Cómo funciona:**

```
Ingrediente: Carne molida (kg)
Cantidad (kg — Kilogramo): [ 0.15 ]
⇄ [ 150 ] [ g ▼ ] → 0.15 kg
```

El usuario escribe `150` en el campo del conversor, selecciona `g` en el dropdown, y el campo principal se actualiza a `0.15` automáticamente. El conversor alimenta el campo principal — no lo reemplaza. El usuario puede igualmente escribir directo en el campo si ya sabe la cantidad en la unidad correcta.

**Conversiones soportadas:**

| Familia | Unidades | Ejemplos |
|---|---|---|
| Peso | `g`, `kg`, `lb`, `oz` | 150 g → 0.15 kg · 0.5 lb → 226.8 g |
| Volumen | `mL`, `L` | 500 mL → 0.5 L · 2 L → 2000 mL |

**Componente:** `resources/js/Components/UI/UnitConverter.vue` — reutilizable, recibe `:target-unit` y emite el valor convertido vía `v-model`.

---

### KDS — Kitchen Display System
**Nombre local:** Pantalla digital de cocina / Monitor de cocina

Una pantalla táctil o de solo lectura instalada en la cocina que muestra las comandas en tiempo real, sin papel ni tickets. Cada estación (cocina caliente, cocina fría, bar) recibe solo los ítems que le corresponden. El cocinero confirma cada ítem y la comanda se actualiza en la pantalla del mesero al instante vía WebSocket.

**Valor para el cliente:** elimina errores por letra ilegible, reduce tiempos de comunicación cocina-salón, permite saber en segundos qué está pendiente y desde cuándo.

---

### UoM Conversion — Unit of Measure Conversion
**Nombre local:** Conversión de unidad de medida de compra

El inventario se almacena en una unidad base (ej. unidades, gramos, litros), pero las compras suelen hacerse en empaques más grandes: cajas de 12, fardos de 24, pacas. La conversión UoM permite registrar "compré 3 cajas de Coca Cola" y el sistema convierte automáticamente a 36 unidades en stock, sin necesidad de hacer la cuenta a mano. El proveedor vende en cajas; el restaurante vende y consume en unidades — el sistema habla los dos idiomas.

**Valor para el cliente:** menos errores en el ingreso de inventario, compatibilidad con la forma real en que se compra.

#### Cómo se configura

Al crear un ingrediente se definen tres conceptos separados:

| Campo | Qué representa | Ejemplo |
|---|---|---|
| **Unidad de almacén** | La unidad más pequeña en que se vende o consume | `Unidad (und)` |
| **Nombre del empaque** | Cómo llama el proveedor al bulto de compra | `Caja` |
| **Unidades por empaque** | Cuántas unidades base trae ese empaque | `12` |

El empaque es **opcional**. Si el producto no se compra en bultos, se deja vacío y el stock se ingresa directamente en unidades.

#### Ejemplo completo: Coca Cola Lata

El restaurante compra Coca Cola en cajas de 12 latas y las vende de a una.

**Configuración del ingrediente:**
```
Nombre:              Coca Cola Lata
Unidad de almacén:   Unidad (und)      ← unidad más pequeña que se vende
Empaque:             Caja × 12         ← cómo llega del proveedor
Stock mínimo:        24                ← equivale a 2 cajas; cuando queden menos de 24 el sistema avisa
```

**Stock inicial al instalar el sistema:**

El dueño hace el conteo físico antes de ingresar el sistema:
```
5  latas sueltas en la nevera
5  latas en una caja abierta
4  cajas cerradas × 12 = 48 latas
─────────────────────────────────
Total: 58 unidades
```
Escribe `58` directo en el campo "Total en units". El empaque no afecta este número — es solo para facilitar ingresos futuros.

**Reposición mensual:**

Cuando llega el proveedor con 3 cajas, el mesero abre "Reponer" y escribe `3` en "Cajas a ingresar". El sistema muestra:
```
3 Cajas × 12 = 36 unit
```
Confirma, y el stock sube de 58 a 94 automáticamente. No hay que calcular nada.

**Descuento por venta:**

Cuando se vende una Coca Cola, el sistema descuenta `1 unit` del inventario — independientemente de cómo se compró. La caja solo existe en el momento de la entrada de stock. Una vez adentro, todo son unidades.

```
Venta de 1 Coca Cola  →  stock: 94 - 1 = 93 unit
Venta de 1 Coca Cola  →  stock: 93 - 1 = 92 unit
```

#### Reposición con cajas completas y unidades sueltas

No siempre llega una cantidad exacta de cajas. El formulario de reposición acepta ambas cosas al mismo tiempo y calcula el total:

```
Cajas completas:   2  × 12 = 24 und
Unidades sueltas:  5
─────────────────────────────────────
Total a agregar:   29 und
```

Cada campo es independiente — se puede llenar solo cajas, solo sueltas, o los dos. Lo que se guarda en el historial de stock es siempre el total en unidades (`29`).

#### Cómo el descuento de inventario interactúa con el empaque

El descuento **no sabe nada del empaque**. Cuando se marca un ítem como servido, el sistema hace:

```
quantity_used (de la receta) × quantity (del ítem en la comanda)
```

Ejemplo: la receta de "Coca Cola" dice `quantity_used = 1 unit`. Si la comanda tiene 3 Coca Colas:
```
1 × 3 = 3 unidades descontadas del inventario
```

Los campos `purchase_unit` y `units_per_package` solo participan en el momento de la **entrada** de stock (reposición). Una vez adentro, todo es unidades. No hay forma de que el empaque cause un descuadre.

#### Regla general para el stock mínimo

Pensarlo siempre en unidades, no en empaques:
```
Quiero que me avise cuando queden menos de 2 cajas
→ stock mínimo = 2 × 12 = 24
```

---

### Stock Ledger / Inventory Ledger
**Nombre local:** Historial de entradas de inventario / Bitácora de stock

Un registro permanente e inmutable de cada movimiento de inventario: quién ingresó stock, cuándo, cuánto había antes, cuánto se agregó, cuánto quedó, a qué costo. Es el equivalente restaurantero del libro mayor contable — cada entrada tiene trazabilidad completa.

**Valor para el cliente:** auditoría ante robos o errores, control de costos histórico, respaldo ante discrepancias con proveedores.

---

### Par Level
**Nombre local:** Stock mínimo / Nivel mínimo de reorden

Cantidad mínima que debe haber de un ingrediente antes de que el sistema emita una alerta. Si el stock cae por debajo de ese nivel, aparece una advertencia visual en el panel de administración. El nombre "par" viene de golf: el nivel esperado al que siempre querés llegar.

**Valor para el cliente:** nunca quedarse sin un ingrediente clave en plena hora pico. Las alertas se ven antes de que el problema ocurra.

---

### Auto-deduction
**Nombre local:** Descuento automático de inventario

Cada vez que un ítem de una comanda es marcado como servido, el sistema busca su receta (BOM) y descuenta los ingredientes correspondientes del inventario en tiempo real. No hay un paso manual de "bajar el inventario" — ocurre como efecto secundario directo de la operación normal del restaurante.

**Valor para el cliente:** el inventario siempre está actualizado sin trabajo extra para el personal. El costo real de cada venta es calculable en cualquier momento.

---

### Check / Tab
**Nombre local:** Comanda / Cuenta

La unidad central de trabajo en un restaurante. Representa una orden desde que se abre hasta que se cierra y se cobra. Tiene un ciclo de vida definido: `abierta → enviada a cocina → servida → cerrada`. Puede ser de mesa, para llevar, o de pedido web. Todo — modificadores, ítems, estado de cocina, descuentos — vive dentro de una comanda.

**Valor para el cliente:** trazabilidad completa de cada transacción, desde el primer ítem pedido hasta el pago final.

---

### Void
**Nombre local:** Anulación / Cancelación

Una comanda anulada queda registrada en el sistema pero excluida de todos los reportes de ventas, inventario y facturación. No es un borrado — es una marca que indica que la operación no se concretó. Permite auditar cuántas órdenes se anulan y por qué, sin contaminar los números reales del negocio.

**Valor para el cliente:** control de anulaciones como indicador de problemas operativos (errores del mesero, clientes que se van), sin afectar los reportes de cierre.

---

### Modifier Groups
**Nombre local:** Grupos de modificadores / Extras / Personalizaciones

Opciones configurables que se agregan a un plato al momento de pedirlo: término de la carne, tipo de pan, extras pagados, salsas, alergias. Cada grupo define cuántas opciones puede elegir el cliente — desde una sola hasta un número exacto o ilimitado. Se definen una vez y se asignan a los platos del menú que correspondan.

**Valor para el cliente:** el mesero nunca olvida preguntar el término de la carne. Las personalizaciones llegan exactas a la cocina. Los extras se cobran automáticamente. El sistema guía al mesero y no le deja confirmar el pedido hasta que se cumplan todas las reglas de selección.

#### Tipos de selección

El tipo de selección controla **cuántas opciones puede elegir el comensal**, no cuántas opciones existen en la lista. La lista de opciones disponibles la define el administrador y puede tener 2, 10, 20 o las que sean necesarias — el tipo no la limita.

| Tipo | El comensal puede elegir | Ejemplo típico |
|---|---|---|
| **Una opción** | exactamente 1 de la lista | Término de la carne: jugoso, término medio, bien cocido |
| **Múltiple** | todas las que quiera de la lista | Toppings de pizza: champiñones, jalapeño, aceitunas… |
| **Personalizado** | el rango exacto que defina el admin | Guarniciones: elegí exactamente 2 de 5 opciones |

#### Ejemplos concretos por tipo

---

**Una opción — Término de la carne**

El comensal debe elegir exactamente uno de los términos disponibles. No importa cuántos términos haya en la lista — solo puede elegir uno.

```
Nombre:    Término de la carne
Tipo:      Una opción
Requerido: sí

Opciones disponibles (pueden ser las que se necesiten):
  - Jugoso
  - Término medio
  - Tres cuartos
  - Bien cocido
```

El mesero no puede confirmar el pedido sin elegir un término.

---

**Múltiple — Toppings de pizza**

El comensal puede elegir todos los toppings que quiera, o ninguno. La lista puede tener 15 opciones y el comensal puede marcar todas si quiere.

```
Nombre:    Toppings adicionales
Tipo:      Múltiple
Requerido: no

Opciones disponibles:
  - Champiñones (+Q5.00)
  - Jalapeño
  - Aceitunas (+Q5.00)
  - Cebolla caramelizada (+Q8.00)
  - Tocino (+Q12.00)
  ... (pueden agregarse más sin límite)
```

El mesero puede confirmar el pedido sin elegir ningún topping — son opcionales.

---

**Personalizado — Guarniciones de plato fuerte**

El restaurante ofrece 5 guarniciones distintas pero el precio del plato incluye solo 2. El comensal debe elegir exactamente 2.

```
Nombre:         Guarniciones
Tipo:           Personalizado
Mín. opciones:  2
Máx. opciones:  2

Opciones disponibles:
  - Puré de papas
  - Frijoles volteados
  - Frijoles parados
  - Aguacate
  - Verduras cocidas
```

El picker muestra el contador `0 / 2`. El botón "Agregar" permanece deshabilitado hasta elegir exactamente 2. Al llegar a 2, el sistema deja de aceptar más selecciones.

Otro ejemplo con rango: si el plato incluye entre 1 y 3 guarniciones, se configura Mín: `1` / Máx: `3`. El mesero puede elegir 1, 2 o 3 — pero no puede confirmar sin elegir al menos 1.

#### Cómo se configura (paso a paso)

1. Ir a **Catálogos → Modificadores → Nuevo grupo**
2. Escribir el nombre del grupo (ej. "Guarniciones")
3. Seleccionar el tipo: **Una opción**, **Múltiple**, o **Personalizado**
4. Si es Personalizado: escribir el mínimo y el máximo
5. Marcar "Obligatorio" si el mesero debe seleccionar al menos una opción
6. Guardar y agregar las opciones (ej. "Puré de papas", "Frijoles volteados", etc.)
7. Ir a la receta del plato fuerte (**Catálogos → Menú → Receta**) y asignar el grupo

#### Validación en backend y frontend

La regla de selección se valida en dos capas:

- **Frontend (picker)**: el botón "Agregar" no se habilita hasta que se cumple el mínimo. Las opciones se bloquean cuando se llega al máximo — no se puede seleccionar más aunque el mesero lo intente.
- **Backend (API)**: aunque el frontend falle o se manipule la petición, el servidor rechaza la orden si el conteo de opciones seleccionadas no está dentro del rango `[min, max]` del grupo.

#### Precios adicionales por opción

Cada opción puede tener un precio delta (positivo o negativo). Si la guarnición "Aguacate" tiene un costo extra de Q5.00, ese monto se suma automáticamente al total de la comanda cuando el mesero la selecciona. No hay que calcular ni recordar nada.

#### Ingredientes por opción — Mini-BOM

Cada opción de modificador puede tener su propia lista de ingredientes (mini receta). Cuando el mesero marca un ítem como servido, el sistema descuenta tanto la receta base del plato como los ingredientes de cada modificador seleccionado — todo en una sola transacción atómica.

**Por qué existe esta capa:** sin ella, "extra tocino" solo afecta el precio pero nunca descuenta tocino del inventario. El stock termina desfasado de la realidad.

**Flujo de descuento completo al servir:**

```
CheckItem (2 Hamburguesas, con Extra Queso + Extra Tocino)
│
├─ Receta base del plato (× 2):
│    Pan:   1 unit × 2 = 2 unit
│    Carne: 0.15 kg × 2 = 0.30 kg
│    Queso: 0.03 kg × 2 = 0.06 kg
│
├─ Modificador "Extra Queso" (× 2):
│    Queso: 0.03 kg × 2 = 0.06 kg
│
└─ Modificador "Extra Tocino" (× 2):
     Tocino: 0.04 kg × 2 = 0.08 kg

Stock descontado total:
  Pan    → 2 unit
  Carne  → 0.30 kg
  Queso  → 0.12 kg   ← receta base + modificador sumados
  Tocino → 0.08 kg
```

**Modificadores sin ingredientes:** si una opción no tiene ingredientes configurados (ej. "Término medio"), simplemente no descuenta nada adicional. El comportamiento es igual al anterior — solo afecta precio.

**Cómo se configura:**

1. Ir a **Admin → Grupos de modificadores**
2. Expandir el grupo → expandir la opción → hacer clic en **"▼ Receta"**
3. Seleccionar el ingrediente y escribir la cantidad
4. Hacer clic en **"+ Agregar"**

El badge junto al botón "▼ Receta" muestra cuántos ingredientes tiene configurada la opción. Si dice `(0)`, esa opción no descuenta stock.

**Tabla de base de datos:**

```
modifier_option_ingredients
  modifier_option_id  FK → modifier_options (cascade delete)
  ingredient_id       FK → ingredients (cascade delete)
  quantity_used       decimal(10,4)
  UNIQUE (modifier_option_id, ingredient_id)
```

Si se elimina una opción, sus líneas de ingredientes se eliminan en cascada.

---

### Floor Plan
**Nombre local:** Plano de salón / Mapa de mesas

Vista visual del restaurante organizada por áreas (salón principal, terraza, bar, VIP). Cada mesa muestra su estado en tiempo real: libre, ocupada, con comanda abierta. El mesero selecciona la mesa directamente desde el plano para abrir o continuar una comanda.

**Valor para el cliente:** el personal entiende de un vistazo qué mesas están ocupadas, cuánto tiempo llevan esperando y qué necesita atención — sin caminar por el salón a revisar.

---

### Customer Display
**Nombre local:** Pantalla de display / Monitor de sala / Pantalla de orden

Una pantalla secundaria — TV, tablet, monitor — que muestra el estado de las órdenes al cliente o al personal de sala. Se actualiza en tiempo real cuando cambia el estado de las comandas (enviada a cocina, lista, servida). No requiere login ni autenticación.

**Valor para el cliente:** los clientes de para llevar o delivery ven el avance de su pedido sin preguntar. Reduce interrupciones al personal.

---

### Online Ordering
**Nombre local:** Ordenar en línea / Pedidos web / Carta digital con pedido

Los clientes acceden desde su teléfono (sin descargar nada, solo un link o QR) y hacen su pedido directamente. La orden entra al sistema como una comanda estándar — el sistema la trata igual que una creada por el mesero, con modificadores, descuento de inventario y todo el ciclo de vida normal. El administrador puede habilitar o deshabilitar el módulo y configurar un monto mínimo de pedido.

**Valor para el cliente:** canal de ventas adicional sin costo de plataformas de delivery externas, operaciones más rápidas en hora pico, menor dependencia de meseros para órdenes simples.

---

### Takeout / To-Go
**Nombre local:** Para llevar / Delivery

Comandas que no están asociadas a una mesa sino a un cliente identificado por nombre, teléfono y dirección opcional. El sistema distingue si la orden fue creada por el POS (un mesero la ingresó) o vino del canal web, lo que permite reportes separados de ventas web vs. presenciales.

**Valor para el cliente:** operación de para llevar integrada al mismo sistema de mesas, sin apps externas. Reportes que muestran qué canal de venta está funcionando mejor.

---

### Cash Close / End of Day
**Nombre local:** Cierre de caja / Corte de caja / Liquidación

Proceso al final de cada turno o día que consolida el total vendido, lo compara con el efectivo físico en caja y registra si hay sobrante o faltante. Queda registrado con fecha, hora y usuario responsable. Permite detectar descuadres de caja y tener un historial de cierres para auditoría.

**Valor para el cliente:** control financiero diario, responsabilidad por turno, evidencia ante el contador o el dueño.

---

### FEL — Factura Electrónica en Línea
**Nombre local:** FEL / Factura electrónica SAT Guatemala

Obligación legal en Guatemala desde 2023: toda factura debe certificarse electrónicamente ante la SAT a través de un certificador autorizado (Infile, G&T, Megaprint, entre otros) antes de entregarse al cliente. El sistema genera el XML de la factura, lo firma digitalmente, lo envía al certificador, recibe el número de autorización SAT y lo imprime en el comprobante — todo en segundos, de forma transparente para el usuario.

**Valor para el cliente:** cumplimiento legal garantizado, sin procesos manuales ni riesgo de multas por facturación incorrecta.

---

### QR Menu
**Nombre local:** Menú QR / Carta digital

Un código QR que el cliente escanea con su teléfono para ver el menú completo con fotos, descripciones y precios. No requiere que el cliente descargue nada. Si el módulo de pedidos en línea está activo, desde la misma pantalla puede ordenar directamente.

**Valor para el cliente:** reduce el costo de imprimir cartas, siempre está actualizado cuando cambian precios o disponibilidad, y es el punto de entrada al canal de pedidos web.

---

### Audit Log
**Nombre local:** Bitácora / Registro de auditoría

Registro automático de las acciones críticas del sistema: quién creó o modificó un ingrediente, quién anuló una comanda, quién cambió precios. Cada entrada tiene usuario, fecha, hora y el detalle del cambio (valor anterior vs. valor nuevo).

**Valor para el cliente:** trazabilidad ante robos, errores o disputas internas. Si algo cambió, hay evidencia de quién lo hizo y cuándo.

---

## Convenciones

- IVA: 12% (Guatemala)
- Moneda: GTQ (con soporte futuro USD para turismo)
- Zona horaria: `America/Guatemala`
- Locale: `es` con faker `es_GT`
- Propina sugerida: 10% (no obligatoria)

---

## Manual de Usuario

Guía de uso del sistema organizada por rol. Cada sección describe qué pantallas ve ese usuario, cómo accede y qué puede hacer.

---

### Roles del sistema

| Rol | Acceso | Qué hace |
|---|---|---|
| **Mesero** | PIN de 4 dígitos | Mesas, comandas, para llevar |
| **Cocinero** | PIN de 4 dígitos | Pantalla KDS de cocina |
| **Cajero** | PIN de 4 dígitos | Cierre de caja, comandas |
| **Administrador** | Email + contraseña | Todo el sistema |

---

### Mesero

#### Ingreso al sistema

1. Abrir la app en el navegador o tablet del salón
2. En la pantalla de login, tocar el avatar con las iniciales del nombre
3. Ingresar el PIN de 4 dígitos en el teclado numérico
4. El sistema redirige automáticamente al **Salón**

#### Salón — vista de mesas

Muestra todas las mesas del restaurante agrupadas por área (Salón, Terraza, Bar, etc.).

**Indicadores de color por mesa:**
- **Verde** — libre, sin comanda activa
- **Ámbar** — ocupada, tiempo razonable
- **Rojo** — ocupada, lleva mucho tiempo esperando atención

**Filtros disponibles:** Todas · Ocupadas · Libres

**Abrir una mesa:**
1. Tocar la mesa libre deseada
2. Ingresar el número de comensales (covers)
3. Confirmar — el sistema crea la comanda y abre la pantalla de detalle

**Ver una mesa ocupada:**
1. Tocar la mesa — abre directamente la comanda activa

#### Comanda — detalle

Pantalla central de trabajo. Muestra todos los ítems pedidos, su estado y el total.

**Agregar ítems:**
1. Usar las pestañas de categorías en la parte inferior (Entradas, Platos fuertes, Bebidas, etc.)
2. Tocar el ítem deseado — se agrega a la comanda
3. Si el ítem tiene modificadores obligatorios, el sistema abre el picker automáticamente

**Picker de modificadores:**
- Grupos obligatorios aparecen primero (no se puede continuar sin completarlos)
- Grupos opcionales se pueden omitir
- El contador muestra cuántas opciones quedan por seleccionar: `0 / 2`
- Los ítems con precio adicional muestran el delta: `+Q5.00`
- Al completar todos los grupos requeridos, el botón **Confirmar** se habilita

**Editar cantidad:** tocar el número junto al ítem — campo editable inline

**Eliminar ítem:** tocar el botón ✕ al lado del ítem (solo mientras no esté enviado a cocina)

**Enviar a cocina:**
1. Revisar los ítems
2. Tocar **Enviar** — los ítems pasan a estado "En cocina" y aparecen en el KDS
3. El mesero puede seguir agregando ítems después del envío

**Notas de comanda:** botón de notas en el encabezado — texto libre visible en cocina

**Transferir mesa:** mueve toda la comanda a otra mesa (útil cuando los clientes cambian de lugar)

**Cerrar cuenta:**
1. Tocar **Cerrar cuenta**
2. Ingresar propina (opcional, sugerida 10%)
3. El sistema emite la FEL automáticamente y genera el ticket
4. La mesa queda libre en el salón

#### Para llevar

Comandas sin mesa asociada, identificadas por nombre y teléfono del cliente.

**Crear orden para llevar:**
1. Ir a **Para llevar** en el menú
2. Tocar **Nueva orden**
3. Ingresar nombre del cliente, teléfono y dirección (opcional)
4. Confirmar — se abre la comanda de para llevar con el mismo flujo que una mesa

**Ver órdenes activas:** lista con nombre del cliente, estado, tiempo transcurrido y total

---

### Cocinero / Cocina

#### Ingreso al sistema

Mismo flujo que el mesero: seleccionar usuario + PIN. El sistema redirige al **KDS (Kitchen Display)**.

#### KDS — Pantalla de cocina

Muestra todos los ítems pendientes de preparar, agrupados por comanda.

**Pestañas de estación:** si hay múltiples estaciones (Cocina Caliente, Bar, Prep), cada una tiene su pestaña con el conteo de ítems pendientes. El cocinero filtra por su estación.

**Información por tarjeta:**
- Nombre de la mesa o cliente
- Lista de ítems a preparar con modificadores
- Tiempo transcurrido desde que se envió (se pone ámbar y luego rojo al pasar tiempo)

**Marcar ítem como listo:**
1. Tocar el ítem o el botón **Listo** de la tarjeta
2. El ítem desaparece del KDS y aparece como "Listo" en la comanda del mesero
3. El mesero recibe notificación en tiempo real

**Auto-refresh:** el KDS se actualiza automáticamente cada 30 segundos, además de en tiempo real vía WebSocket.

---

### Cajero

Acceso a las mismas pantallas que el mesero, más:

#### Cierre de caja

Al final de cada turno o día.

1. Ir a **Cierre de caja**
2. El sistema muestra el período pendiente de cierre con el resumen:
   - Número de comandas cerradas
   - Subtotal, IVA, propinas, total
3. Tocar **Realizar cierre**
4. Ingresar el efectivo contado en caja
5. Ingresar el total cobrado con tarjeta
6. Agregar notas si hay discrepancias
7. El sistema calcula automáticamente el sobrante o faltante
8. Confirmar — el cierre queda registrado con fecha, hora y usuario

**Historial:** todos los cierres anteriores quedan visibles con sus totales y varianzas.

---

### Cliente (sin login)

#### Menú QR

Accesible escaneando el QR del restaurante. Solo lectura.

- Navegar por categorías con pestañas horizontales deslizables
- Ver nombre, descripción, precio y modificadores disponibles de cada ítem
- No requiere cuenta ni descarga

#### Ordenar en línea

Si el restaurante tiene habilitada esta función, el cliente puede hacer su pedido desde el QR.

1. Seleccionar ítems tocando **Agregar**
2. Ajustar cantidad con `+` / `−`
3. Si el ítem tiene modificadores, el picker se abre automáticamente
4. Ver el carrito con subtotal en tiempo real
5. Agregar notas por ítem si es necesario
6. Tocar **Hacer pedido** — si hay un mínimo de compra configurado, el sistema avisa antes
7. La orden entra al sistema como comanda de para llevar — el restaurante la ve igual que una ingresada por el mesero

**Confirmación:** pantalla con número de orden, ítems y total. El restaurante llama al cliente cuando esté lista.

#### Pantalla de display (TV)

Pantalla pública para que los clientes vean el estado de su pedido.

- Columna **Preparando**: órdenes en cocina con tiempo transcurrido
- Columna **Listo**: órdenes listas para retirar o ser servidas
- Reloj en tiempo real
- Acceso protegido por PIN de 4 dígitos (configurado por el administrador en Ajustes)

#### Calificación

Link enviado al cliente tras cerrar la cuenta.

1. Seleccionar de 1 a 5 estrellas (interactivo con emojis)
2. Escribir comentario opcional
3. Enviar — la calificación queda visible en los reportes del administrador

---

### Administrador

Acceso completo al sistema vía email + contraseña en `/admin/login`.

---

#### Dashboard

Vista general del estado del negocio en tiempo real.

**KPIs del día:**
- Ingresos totales
- Comandas cerradas
- Ticket promedio
- Comensales atendidos (covers)
- Mesas abiertas actualmente

**Tabla de comandas abiertas:** mesa, mesero, tiempo abierto, total acumulado

**Gráfico semanal:** ingresos por día de la semana (barras)

**Alertas activas:**
- Ingredientes bajo el stock mínimo — con nombre, cantidad actual y mínimo
- Facturas FEL fallidas que requieren atención

---

#### Reportes

##### Ventas generales

Filtrar por rango de fechas o usar presets: **Hoy**, **7 días**, **30 días**.

| Métrica | Descripción |
|---|---|
| Ingresos totales | Suma de todas las comandas cerradas |
| Subtotal | Sin IVA |
| IVA recaudado | 12% Guatemala |
| Food cost % | Costo de ingredientes / ingresos |
| Ítems más vendidos | Ranking con cantidad y revenue por ítem |
| Ingresos por día | Tabla desglosada día a día |
| Rotación de mesas | Tiempo promedio por mesa, covers promedio |

##### Reporte de meseros

- Seleccionar mesero específico o ver todos
- Métricas por mesero: ingresos, comandas, ticket promedio, covers, calificación, duración promedio de comanda
- Grid de datos diarios para el mesero seleccionado

##### Calificaciones

- Distribución de estrellas (1 a 5) con porcentajes
- Calificación promedio general
- Comentarios recientes con número de comanda y timestamp
- Desglose por mesero
- Tendencia diaria de calificaciones

##### Para llevar

- Órdenes de para llevar y web separadas de las de mesa
- Tiempo promedio de preparación
- Clientes frecuentes (pedidos repetidos)
- Ítems más pedidos para llevar

---

#### Historial de comandas

Lista de todas las comandas cerradas, con filtros:

- Rango de fechas
- Mesero
- Número de comanda o nombre de cliente

**Columnas:** número, mesero, mesa/cliente, duración, subtotal, IVA, propina, total, estado FEL

---

#### Inventario — Ingredientes

**Ver ingredientes:** tabla con nombre, unidad, stock actual, stock mínimo. Los ingredientes bajo mínimo aparecen resaltados.

**Crear ingrediente:**
1. Tocar **Nuevo ingrediente**
2. Nombre, unidad de almacén (g, kg, lb, oz, L, mL, unit, portion)
3. Nombre del empaque y unidades por empaque (opcional — para compras en bultos)
4. Stock mínimo
5. Guardar

**Reponer stock:**
1. Tocar **Reponer** junto al ingrediente
2. Ingresar cajas completas (si tiene empaque configurado) o unidades sueltas
3. El sistema calcula el total: `2 cajas × 12 = 24 + 5 sueltas = 29 und`
4. Confirmar — el stock sube y queda registrado en el historial

**Historial de entradas:** en **Inventario → Entradas de stock** — log completo de todas las reposiciones con fecha, cantidad, usuario y notas.

---

#### Catálogos — Menú

Lista de ítems del menú con categoría, precio, estación de cocina y estado activo.

**Crear ítem:**
1. Tocar **Nuevo ítem**
2. Nombre, categoría, precio, descripción (opcional), SKU (opcional)
3. Estación de cocina (define dónde aparece en el KDS)
4. Guardar

**Configurar receta (BOM):** en la fila del ítem, tocar el botón de receta o acceder desde el ícono de configuración → pestaña **Receta**.

**Asignar modificadores:** en el mismo panel → pestaña **Modificadores** — seleccionar qué grupos aplican al ítem.

---

#### Catálogos — Grupos de modificadores

**Crear grupo:**
1. Tocar **Nuevo grupo**
2. Nombre (ej. "Término de la carne")
3. Tipo: **Una opción** / **Múltiple** / **Personalizado** (con mín. y máx.)
4. Marcar **Obligatorio** si el mesero no puede omitirlo
5. Guardar

**Agregar opciones al grupo:**
1. Expandir el grupo con **▼ Opciones**
2. Escribir el nombre de la opción y su precio adicional (0 si no tiene costo extra)
3. Tocar **Agregar**

**Configurar ingredientes por opción (Mini-BOM):**
1. Dentro del panel de opciones, tocar **▼ Receta** junto a la opción
2. Seleccionar el ingrediente del listado
3. Ingresar la cantidad — usar el conversor inline si es necesario (ej. escribir `30` en `mL` para que el campo se complete con `0.03` en `L`)
4. Tocar **+ Agregar**

Esto permite que al servir un ítem con esa opción, el ingrediente se descuente del inventario automáticamente.

---

#### Recetas — BOM (Bill of Materials)

Accesible desde **Menú → ícono de configuración del ítem → pestaña Receta**.

**Agregar ingrediente a la receta:**
1. Seleccionar el ingrediente del dropdown — muestra el stock actual entre paréntesis
2. Ingresar la cantidad en la unidad del ingrediente
3. Usar el conversor inline si la cantidad se conoce en otra unidad: `⇄ [ 150 ] [ g ▼ ] → 0.15 kg`
4. El sistema muestra hints de conversión y warnings si la cantidad parece incorrecta
5. Tocar **Guardar receta**

**Hints por unidad:**
- `kg` → "para 150 g escribí 0.15 · para 500 g escribí 0.5"
- `L` → "para 100 mL escribí 0.1 · para 500 mL escribí 0.5"
- `unit` → "usá 1, 2, 3… Los decimales no están permitidos"

**Warnings automáticos:**
- Cantidad muy alta para la unidad → ámbar con mensaje de advertencia
- Decimal en unidad `unit` → advertencia de que debe ser entero

---

#### Configuración del negocio

- Logo (subir imagen, quitar)
- Nombre del restaurante
- Dirección y teléfono
- PIN del display TV (4 dígitos — protege la pantalla pública)
- Habilitar / deshabilitar pedidos en línea
- Monto mínimo de pedido en línea (en Q)

---

#### FEL — Facturas electrónicas

Lista de todas las facturas generadas con su estado SAT.

| Estado | Color | Significado |
|---|---|---|
| Autorizada | Verde | Certificada por SAT, válida |
| Pendiente | Ámbar | En proceso de certificación |
| Fallida | Rojo | Error en el certificador — requiere acción |
| Anulada | Gris | Cancelada ante SAT |

**Reintentar factura fallida:** tocar **Reintentar** — el sistema reenvía al certificador

**Anular factura:** tocar **Anular**, ingresar el motivo de anulación, confirmar

---

#### Usuarios

**Crear usuario:**
1. Tocar **Nuevo usuario**
2. Nombre completo
3. Rol: Admin / Mesero / Cocinero / Cajero
4. PIN de 4 dígitos (para mesero, cocinero, cajero)
5. Email y contraseña (solo para admin)
6. Guardar

**Desactivar usuario:** el usuario queda en el sistema pero no puede ingresar

---

#### Auditoría

Log inmutable de todas las acciones críticas del sistema.

**Filtros:** tipo de entidad · acción · usuario · rango de fechas

**Columnas:** entidad, acción (creó / modificó / eliminó), usuario, fecha y hora

**Ver detalle de cambio:** expandir la fila — muestra el JSON con el valor anterior y el nuevo, campo por campo

---

#### Áreas y Mesas

**Áreas:** secciones del restaurante (Salón, Terraza, Bar, VIP). Cada área agrupa mesas.

**Crear área:** nombre + orden de visualización → Guardar

**Mesas:** pertenecen a un área, tienen nombre/número y capacidad (covers).

**Crear mesa:** seleccionar área, nombre (ej. "Mesa 5"), capacidad → Guardar

**Desactivar mesa:** solo si no tiene una comanda abierta. Una mesa ocupada no se puede desactivar.

---

#### Estaciones de cocina

Definen dónde aparece cada ítem en el KDS.

**Crear estación:**
- Nombre (ej. "Cocina Caliente")
- Código único (ej. `HOT`) — usado en los canales de broadcast WebSocket
- Orden de visualización

Cada ítem del menú se asigna a una estación. En el KDS, el cocinero filtra por su estación y solo ve lo que le corresponde.

---

#### QR Menú

Panel de administración del menú QR para clientes.

- Genera y muestra el QR que el restaurante imprime o pone en las mesas
- El QR apunta al menú público (`/menu`) o al sistema de pedidos en línea (`/order`)
- Sin configuración adicional necesaria — el QR se genera automáticamente

---

### Flujo completo de una comanda (referencia rápida)

```
Mesero abre mesa
  └─ Agrega ítems + modificadores
       └─ Envía a cocina
            └─ Cocina marca ítems como listos (KDS)
                 └─ Mesero sirve y marca como servido
                      └─ Stock se descuenta automáticamente
                           └─ Cliente paga → mesero cierra cuenta
                                └─ FEL se emite automáticamente
                                     └─ Mesa queda libre
```

**Descuento de inventario al servir:**
- Se descuenta la receta base del ítem (BOM)
- Se descuenta la receta de cada modificador seleccionado (Mini-BOM)
- Todo en una sola transacción atómica — si hay error, ningún descuento se aplica
