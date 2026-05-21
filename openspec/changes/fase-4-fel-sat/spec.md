# Spec — Fase 4: FEL SAT Guatemala

## Problema

Guatemala exige facturación electrónica (FEL) desde 2019 para contribuyentes
afiliados. El sistema necesita emitir DTEs ante SAT en tiempo real al cerrar
una comanda.

## Alcance

- Generación de XML DTE tipo FACT según especificación SAT v0.2.0
- Emisión asíncrona (job en cola) para no bloquear el cierre de comanda
- Adaptador Null para desarrollo/test + Adaptador Infile para producción
- Panel admin para ver facturas y reintentar fallidas
- UUID del DTE impreso en el ticket de sala

## Requisitos funcionales

### DTE XML
- Tipo: `FACT` (Factura)
- IVA incluido en precio: `MontoGravable = Total / 1.12`, `MontoImpuesto = Total - MontoGravable`
- Modificadores concatenados en `<Descripcion>` del ítem
- Escape XML: `htmlspecialchars` con `ENT_XML1 | ENT_QUOTES`

### FelInvoice
- Creada en estado `pending` al cerrar comanda
- Campos: check_id (unique), status, receptor_nit/name, uuid, serie, numero,
  issued_at, xml_request, xml_authorized, error_message, retries

### Job IssueFelInvoice
- `ShouldQueue`, 3 intentos, backoff [60, 300, 600]s
- `failed()` → marca factura como `failed` con mensaje de error
- En DreamHost (`QUEUE_CONNECTION=sync`) corre inline (sin worker)

### Adaptadores
- `NullAdapter` — genera UUID falso, siempre exitoso (dev/test)
- `InfileAdapter` — POST a `cert.api.infile.com.gt/api/1/create_xml`,
  XML en base64, parsea `xml_certificado` con DOMXPath

### Panel admin `/admin/fel-invoices`
- Listado paginado (50/página) con stats: emitidas/pendientes/fallidas
- Botón "Reintentar" para facturas `failed` o `pending`
- No se puede reintentar una factura `issued`

## Restricciones

- `FEL_ENABLED=false` en `.env` → no crea facturas (modo offline)
- UUID solo disponible después de que Infile confirme
- FelStatus tiene método `isFinal()` para bloquear transiciones inválidas

## Gotchas

- `config('restaurant.fel.emisor_commercial_name')` puede ser null en test
  si el `.env` no tiene las vars → `esc(?string)` acepta nullable
- Dual-mode: DreamHost no tiene workers → `QUEUE_CONNECTION=sync` es requerido

## Tests incluidos

`FelTest` (7 tests): null adapter, dispatch al cerrar, XML campos, retry, no-retry issued, lista admin
