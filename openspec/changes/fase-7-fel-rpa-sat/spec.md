# Spec — Fase 7: FEL on-demand vía RPA en SAT Guatemala

---

## Part 1 — Delta for `fel-issuance`

_Modifies behavior established in fase-4-fel-sat. Describes CHANGES only._

### MODIFIED Requirements

---

### Requirement: FelStatus States

`FelStatus` MUST define the cases `pending`, `issued`, `failed`, `cancelled`, and `Processing`.
`Processing` is a non-final, non-error state that represents a dispatched asynchronous job awaiting callback.
(Previously: only `pending | issued | failed | cancelled` were defined.)

Valid transitions:
| From | To | Allowed |
|---|---|---|
| `pending` | `Processing` | YES |
| `pending` | `issued` | YES (sync adapters) |
| `pending` | `failed` | YES (sync adapters) |
| `Processing` | `issued` | YES (callback) |
| `Processing` | `failed` | YES (callback or timeout sweep) |
| `issued` | any | NO — final |
| `cancelled` | any | NO — final |

#### Scenario: Transition pending → Processing

- GIVEN a `FelInvoice` with status `pending`
- WHEN `markProcessing($rpaJobId)` is called
- THEN invoice status is `Processing` and `rpa_job_id` is persisted

#### Scenario: Transition Processing → issued (via callback)

- GIVEN a `FelInvoice` with status `Processing`
- WHEN a valid callback payload with `success=true` is received
- THEN invoice status is `issued`, `uuid`, `serie`, `numero`, `issued_at`, `xml_authorized` are persisted

#### Scenario: Transition Processing → failed (via callback)

- GIVEN a `FelInvoice` with status `Processing`
- WHEN a valid callback payload with `success=false` is received
- THEN invoice status is `failed` and `error_message` is persisted

#### Scenario: Final state blocks transition

- GIVEN a `FelInvoice` with status `issued`
- WHEN any `mark*` method is called
- THEN the call is ignored or throws — status remains `issued`

---

### Requirement: FelResult Contract

`FelResult` MUST support three factory states: `success()`, `failure()`, and `pending()`.
`pending()` MUST be distinguishable from success and failure via a dedicated flag or state value.
(Previously: only `success()` and `failure()` existed; `$result->ok` was boolean.)

| Factory | `ok` | `pending` | Notes |
|---|---|---|---|
| `success(uuid, serie, numero, xml)` | `true` | `false` | Synchronous issue confirmed |
| `failure(error)` | `false` | `false` | Synchronous issue rejected |
| `pending()` | `false` | `true` | Async dispatch; final result arrives via callback |

#### Scenario: pending() is distinguishable from failure

- GIVEN `FelResult::pending()` is constructed
- WHEN `$result->ok` and `$result->pending` are checked
- THEN `ok === false` AND `pending === true`
- AND `$result->errorMessage` is null

#### Scenario: success() is not pending

- GIVEN `FelResult::success(...)` is constructed
- WHEN `$result->pending` is checked
- THEN `pending === false`

---

### Requirement: FelService::issue() handles pending result

`FelService::issue()` MUST handle a `FelResult::pending()` from the adapter by marking the invoice `Processing`. It MUST NOT throw, MUST NOT mark `issued`, and MUST NOT mark `failed` when the result is pending.
Synchronous adapters (`infile`, `null`) are unaffected — their `success()`/`failure()` results are still handled as before.
(Previously: `issue()` assumed binary success/failure from all adapters.)

#### Scenario: issue() with pending result marks Processing

- GIVEN a `FelInvoice` in `pending` status and an adapter that returns `FelResult::pending()`
- WHEN `FelService::issue($invoice)` is called
- THEN `$invoice->status === Processing`
- AND no exception is thrown
- AND `IssueFelInvoice` job does NOT retry

#### Scenario: issue() with success result still marks Issued (no regression)

- GIVEN a `FelInvoice` in `pending` status and an adapter that returns `FelResult::success(...)`
- WHEN `FelService::issue($invoice)` is called
- THEN `$invoice->status === issued`

#### Scenario: issue() with failure result still marks Failed (no regression)

- GIVEN a `FelInvoice` in `pending` status and an adapter that returns `FelResult::failure(...)`
- WHEN `FelService::issue($invoice)` is called
- THEN `$invoice->status === failed`
- AND the job DOES retry (exception is thrown)

---

## Part 2 — New Capability: `fel-rpa-issuance`

_Full spec — no prior spec exists for this capability._

---

## Purpose

Asynchronous FEL issuance on-demand via RPA (Playwright) running in GitHub Actions. The waiter triggers the request explicitly after closing a check; Laravel dispatches a `workflow_dispatch` to GitHub, the RPA script automates the SAT Guatemala portal, and posts the result back via HMAC-verified callback. The invoice transitions `pending → Processing → issued/failed`.

---

## Requirements

---

### Requirement: FelInvoice model — pdf_path and rpa_job_id

`FelInvoice` MUST store the GitHub Actions run ID in a nullable `rpa_job_id` column and the authorized PDF path in a nullable `pdf_path` column.
`markProcessing(string $rpaJobId)` MUST set both `status = Processing` and persist `rpa_job_id`.

#### Scenario: markProcessing persists run ID

- GIVEN a `FelInvoice` with `status = pending`
- WHEN `$invoice->markProcessing('12345678')` is called
- THEN `rpa_job_id === '12345678'` and `status === Processing` in the database

---

### Requirement: On-demand issuance endpoint

`POST /checks/{check}/fel/request` MUST be available when `FEL_ENABLED=true`.
The endpoint MUST be authenticated (web guard).
It MUST accept optional `nit` and `name` fields; if omitted, defaults are `CF` and `Consumidor Final`.

#### Scenario: Happy path — closed check, no existing invoice

- GIVEN an authenticated user, `FEL_ENABLED=true`, `FEL_ADAPTER=rpa`, and a closed check with no `FelInvoice`
- WHEN `POST /checks/{check}/fel/request` is called (nit + name omitted)
- THEN response is `202 Accepted`
- AND a `FelInvoice` with `status = Processing` is created with `receptor_nit = 'CF'`
- AND `RpaAdapter::submit()` was called (workflow dispatch triggered)

#### Scenario: Check is not closed

- GIVEN an authenticated user, `FEL_ENABLED=true`, and a check that is NOT closed
- WHEN `POST /checks/{check}/fel/request` is called
- THEN response is `422 Unprocessable Entity`
- AND no `FelInvoice` is created

#### Scenario: Invoice already exists

- GIVEN an authenticated user, `FEL_ENABLED=true`, and a check that already has a `FelInvoice` (any status)
- WHEN `POST /checks/{check}/fel/request` is called
- THEN response is `409 Conflict`
- AND no new `FelInvoice` is created

#### Scenario: FEL_ENABLED is false

- GIVEN `FEL_ENABLED=false`
- WHEN `POST /checks/{check}/fel/request` is called
- THEN response is `404 Not Found`

#### Scenario: FEL_ADAPTER is not rpa

- GIVEN `FEL_ENABLED=true` and `FEL_ADAPTER=infile`
- WHEN `POST /checks/{check}/fel/request` is called
- THEN response is `422 Unprocessable Entity`

#### Scenario: Unauthenticated request

- GIVEN no authenticated session
- WHEN `POST /checks/{check}/fel/request` is called
- THEN response is `401 Unauthorized`

#### Scenario: NIT and name defaults applied

- GIVEN an authenticated user and a valid closed check
- WHEN the request body omits `nit` and `name`
- THEN the created `FelInvoice` has `receptor_nit = 'CF'` and `receptor_name = 'Consumidor Final'`

---

### Requirement: RPA Callback endpoint

`POST /fel/rpa/callback` MUST be exempt from CSRF verification.
It MUST verify the `X-Hmac-Signature` header using `HMAC-SHA256` with the configured `rpa.hmac_secret`.
On HMAC failure it MUST return `403 Forbidden` and make no state changes.

#### Scenario: Valid callback — success

- GIVEN a `FelInvoice` with `status = Processing` and a valid HMAC signature
- WHEN `POST /fel/rpa/callback` is called with `success=true`, `uuid`, `serie`, `numero`, `xml_authorized`, and optional `pdf_base64`
- THEN response is `200 OK`
- AND invoice status is `issued`
- AND `FelInvoiceIssued` event is broadcast
- AND if `pdf_base64` was present, the file is stored at `storage/app/fel/invoices/{invoice_id}.pdf` and `pdf_path` is set

#### Scenario: Valid callback — failure

- GIVEN a `FelInvoice` with `status = Processing` and a valid HMAC signature
- WHEN `POST /fel/rpa/callback` is called with `success=false` and an `error` message
- THEN response is `200 OK`
- AND invoice status is `failed` with `error_message` set

#### Scenario: Invalid HMAC

- GIVEN any request to `POST /fel/rpa/callback` with a wrong or missing `X-Hmac-Signature`
- WHEN the endpoint is called
- THEN response is `403 Forbidden`
- AND no invoice state changes

#### Scenario: Invoice not found

- GIVEN a valid HMAC but an `invoice_id` that does not exist in the database
- WHEN `POST /fel/rpa/callback` is called
- THEN response is `404 Not Found`

#### Scenario: Duplicate callback — idempotency

- GIVEN a `FelInvoice` with `status = issued` and a valid HMAC
- WHEN `POST /fel/rpa/callback` is called again with `success=true`
- THEN response is `200 OK`
- AND invoice status remains `issued` (no double-write)

#### Scenario: CSRF exemption confirmed

- GIVEN no session cookie or CSRF token
- WHEN `POST /fel/rpa/callback` is called with a valid HMAC
- THEN the request is NOT rejected with `419 CSRF token mismatch`

---

### Requirement: GitHub Actions workflow dispatch

`RpaAdapter::submit()` MUST dispatch `workflow_dispatch` via the GitHub API with only an opaque `invoice_id` input.
Fiscal XML, NIT, callback URL, credentials, tokens, and HMAC secrets MUST NOT be sent as workflow inputs. The runner MUST obtain XML/NIT from the HMAC-authenticated payload endpoint; its app URL and HMAC secret MUST come from GitHub Actions secrets.
The adapter MUST return `FelResult::pending()` immediately after a successful dispatch.
A non-empty `invoice_id` MUST be validated before dispatch.

#### Scenario: Workflow dispatched with correct inputs

- GIVEN valid `$xml` and `$nit` and a configured GitHub repo + token
- WHEN `RpaAdapter::submit($xml, $nit)` is called
- THEN a `workflow_dispatch` HTTP request is made containing only `invoice_id` (the invoice ULID)
- AND the request contains no XML, NIT, callback URL, credentials, token, or HMAC secret
- AND the method returns `FelResult::pending()`

#### Scenario: Dispatch failure returns FelResult::failure

- GIVEN the GitHub API returns a non-2xx response
- WHEN `RpaAdapter::submit($xml, $nit)` is called
- THEN the method returns `FelResult::failure($error)` (no exception)

---

### Requirement: Private RPA payload delivery

`POST /fel/rpa/payload` MUST accept an `invoice_id` and return its `xml_base64`, `emisor_nit`, and callback URL only after validating `HMAC-SHA256` over `{timestamp}.{raw_body}`. It MUST reject timestamps outside a five-minute window and MUST be CSRF-exempt.

#### Scenario: Valid signed payload request

- GIVEN a `Processing` invoice and a current timestamp with a valid HMAC signature
- WHEN the runner requests `POST /fel/rpa/payload`
- THEN response is `200 OK` with the invoice payload
- AND no credential, GitHub token, or HMAC secret is included

#### Scenario: Invalid or stale payload request

- GIVEN an invalid HMAC or a timestamp older than five minutes
- WHEN `POST /fel/rpa/payload` is called
- THEN response is `403 Forbidden`
- AND no fiscal data is returned

---

### Requirement: PDF storage

When the callback includes `pdf_base64`, the PDF MUST be stored at `storage/app/fel/invoices/{invoice_id}.pdf` (private disk).
PDF is OPTIONAL — a callback without `pdf_base64` MUST still mark the invoice `issued`.
The PDF MUST be served via an authenticated route `GET /fel/invoices/{invoice}/pdf`.

#### Scenario: PDF stored when present in callback

- GIVEN a valid callback with `pdf_base64` set
- WHEN the callback is processed
- THEN the file exists at `storage/app/fel/invoices/{invoice_id}.pdf`
- AND `$invoice->pdf_path` is set to that path

#### Scenario: PDF absent — invoice still issued

- GIVEN a valid callback with `success=true` but no `pdf_base64`
- WHEN the callback is processed
- THEN invoice status is `issued`
- AND `$invoice->pdf_path` is null

#### Scenario: Authenticated PDF download

- GIVEN an authenticated user and an `issued` invoice with a stored PDF
- WHEN `GET /fel/invoices/{invoice}/pdf` is called
- THEN response is `200 OK` with `Content-Type: application/pdf`

#### Scenario: Unauthenticated PDF download blocked

- GIVEN no authenticated session
- WHEN `GET /fel/invoices/{invoice}/pdf` is called
- THEN response is `401 Unauthorized`

---

### Requirement: Realtime notification on issuance

`FelInvoiceIssued` MUST be a broadcastable event, broadcast on a channel scoped to the invoice's check.
The UI MUST update from `Processing` to `issued` upon receiving the event — without a full page reload.
A polling fallback MUST exist at `GET /checks/{check}/fel/status` returning the current invoice status.

#### Scenario: Event broadcast after issued

- GIVEN an invoice transitions to `issued` via callback
- WHEN `FelInvoiceIssued` is fired
- THEN the event is broadcast on the correct channel (e.g., `check.{check_id}`)
- AND the event payload includes `invoice_id` and `status = 'issued'`

#### Scenario: Polling fallback returns current status

- GIVEN an authenticated user and a check with an existing `FelInvoice`
- WHEN `GET /checks/{check}/fel/status` is called
- THEN response is `200 OK` with `{ status: 'Processing' | 'issued' | 'failed' | 'pending' }`

#### Scenario: Polling fallback — no invoice

- GIVEN an authenticated user and a check with no `FelInvoice`
- WHEN `GET /checks/{check}/fel/status` is called
- THEN response is `200 OK` with `{ status: null }`

---

### Requirement: Feature flag gating

When `FEL_ENABLED=false`, the routes `POST /checks/{check}/fel/request`, `POST /fel/rpa/payload`, `POST /fel/rpa/callback`, and `GET /fel/invoices/{invoice}/pdf` MUST NOT be accessible (return `404` or not be registered).
`FEL_ADAPTER=rpa` is required for the on-demand endpoint; other adapter values MUST be rejected at that endpoint.

#### Scenario: All RPA routes unavailable when FEL disabled

- GIVEN `FEL_ENABLED=false`
- WHEN any FEL RPA route is called
- THEN response is `404 Not Found`

#### Scenario: Non-rpa adapter rejected at on-demand endpoint

- GIVEN `FEL_ENABLED=true` and `FEL_ADAPTER=null`
- WHEN `POST /checks/{check}/fel/request` is called
- THEN response is `422 Unprocessable Entity`
