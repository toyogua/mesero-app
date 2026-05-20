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

## Convenciones

- IVA: 12% (Guatemala)
- Moneda: GTQ (con soporte futuro USD para turismo)
- Zona horaria: `America/Guatemala`
- Locale: `es` con faker `es_GT`
- Propina sugerida: 10% (no obligatoria)
