# Proposal: Fase 7 — FEL on-demand vía RPA en SAT Guatemala

## Intent

El restaurante no tiene un certificador FEL contratado, pero SÍ tiene acceso al portal gratuito de SAT Guatemala. Necesitamos emitir facturas legales **a demanda** (botón del mesero tras cerrar la comanda), automatizando el portal con un RPA (Playwright) que corre en GitHub Actions — porque el hosting compartido (DreamHost) no tiene Python ni puede correr navegadores. El reto central: el contrato `FelAdapterInterface::submit()` es **síncrono** (marca Issued/Failed en el acto), pero el RPA tarda ~2 min y devuelve resultado por callback. Hay que volver el flujo asíncrono sin romper los adapters existentes (`infile`, `null`).

## Scope

### In Scope
- Estado `Processing` en `FelStatus` (dispatched, esperando callback).
- `FelResult::pending()` + nuevo `RpaAdapter` que despacha el workflow y devuelve pending (no bloquea).
- Generación **on-demand**: `POST /checks/{check}/fel/request` con `nit`/`name` (defaults CF / Consumidor Final).
- Workflow `workflow_dispatch` + script Playwright que automatiza SAT, baja el PDF y hace POST al callback.
- `FelRpaCallbackController` con verificación HMAC (`POST /fel/rpa/callback`).
- Almacenamiento del PDF autorizado + columna `pdf_path` en `FelInvoice`.
- Broadcast realtime (Ably/Reverb) al emitir; UI con estado pending → emitida + botón descargar.
- Flag `FEL_ADAPTER=rpa` + secreto HMAC en config.

### Out of Scope
- Anulación de facturas vía RPA (`cancel()` queda no soportado en RpaAdapter — lanza excepción).
- Migrar `infile`/`null` a asíncrono (siguen síncronos).
- Mantenimiento del script Playwright ante cambios del portal SAT (riesgo operativo aceptado).

## Capabilities

### New Capabilities
- `fel-rpa-issuance`: emisión FEL on-demand asíncrona vía RPA en GitHub Actions, callback HMAC, almacenamiento de PDF y notificación realtime.

### Modified Capabilities
- `fel-issuance`: el contrato de emisión deja de asumir resultado síncrono; se introduce estado `Processing` y `FelResult::pending()`. (Si no existe spec previa, se crea como parte de esta capacidad.)

## Approach

Resolución de las 6 decisiones clave:

1. **Contrato**: `submit()` puede devolver `FelResult::pending()`. `FelService::issue()` deja de asumir éxito/fallo binario: si `pending`, marca `Processing` y NO lanza. Adapters síncronos no cambian su comportamiento.
2. **Enum**: agregar `FelStatus::Processing` (no final, no error). Permite retry desde Processing/Failed.
3. **Trigger**: `workflow_dispatch` vía GitHub API (no `repository_dispatch`) — permite pasar inputs tipados y consultar el run.
4. **Entrega de datos fiscales**: el dispatch solo lleva el `invoice_id` opaco. El runner obtiene XML/NIT desde un endpoint HMAC autenticado; NUNCA se envían XML, NIT, credenciales ni secretos como inputs de Actions (el repo es público).
5. **PDF**: `storage/app/fel/invoices/{invoice_id}.pdf` (privado, servido vía ruta autenticada).
6. **Realtime**: evento `FelInvoiceIssued` broadcasteado en el callback; UI escucha por Ably (DreamHost) / Reverb (Docker), con polling como fallback.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Enums/FelStatus.php` | Modified | Nuevo case `Processing` |
| `app/Services/Fel/FelResult.php` | Modified | Factory `pending()` + flag estado |
| `app/Services/Fel/FelService.php` | Modified | `resolveAdapter()` case `rpa`; `issue()` maneja pending |
| `app/Services/Fel/Adapters/RpaAdapter.php` | New | Despacha workflow GitHub Actions |
| `app/Http/Controllers/Fel/FelRpaCallbackController.php` | New | Callback + HMAC |
| `app/Http/Controllers/Fel/FelRpaPayloadController.php` | New | Entrega XML/NIT al runner con HMAC + timestamp |
| `app/Http/Controllers/.../CheckFelController.php` | New | Endpoint on-demand |
| `app/Models/FelInvoice.php` + migration | Modified | Columna `pdf_path`, `markProcessing()` |
| `app/Events/FelInvoiceIssued.php` | New | Broadcast realtime |
| `.github/workflows/fel-rpa.yml` + `scripts/fel_sat_rpa.py` | New | RPA Playwright |
| `config/restaurant.php` | Modified | `rpa` block (repo, token, HMAC secret) |
| Vue: vista de comanda cerrada | Modified | Botón generar + estado realtime |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Portal SAT cambia layout → RPA rompe | High | Selectores robustos + alerta en fallo; retry manual existente |
| Callback nunca llega (run falla) | Med | Timeout → estado Failed por job de barrido; botón reintentar |
| Token GitHub / HMAC filtrado | Med | Secretos solo en `.env`/Actions secrets; nunca en inputs; HMAC obligatorio en payload y callback |
| Credenciales SAT en GitHub Actions | Med | GitHub Secrets cifrados; nunca en repo ni logs |
| DreamHost no procesa la cola async | High | Adapter despacha vía HTTP API directo, no depende de la cola del server |

## Rollback Plan

Poner `FEL_ADAPTER=infile` o `null` (o `FEL_ENABLED=false`) desactiva el flujo RPA por completo sin tocar código. La migración de `pdf_path` es aditiva (nullable) y reversible. El nuevo case `Processing` no afecta invoices existentes.

## Dependencies

- Repo en GitHub con Actions habilitado + token con permiso `workflow`.
- Credenciales del portal SAT como GitHub Secrets.
- Ably configurado (DreamHost) o Reverb (Docker) ya existentes.

## Success Criteria

- [ ] Mesero genera factura on-demand; UI muestra "en proceso" sin bloquear.
- [ ] Workflow dispatch corre, automatiza SAT y POSTea el callback con HMAC válido.
- [ ] Callback con HMAC inválido es rechazado (403).
- [ ] Invoice pasa Pending → Processing → Issued; PDF descargable.
- [ ] UI se actualiza en realtime al emitirse (sin recargar).
- [ ] `FEL_ADAPTER` distinto de `rpa` no activa ninguna ruta nueva.
- [ ] Feature tests cubren controller on-demand, callback HMAC y RpaAdapter (pending).
