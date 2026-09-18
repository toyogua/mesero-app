# Exploration: fase-7-fel-rpa-sat

## Current FEL Flow

```
POST /checks/{check}/close
  → CheckController::close()
      → if config('restaurant.fel.enabled'):
          FelService::createPending(check, nit, name) → FelInvoice [status=pending]
          IssueFelInvoice::dispatch(invoice)  ← async job
  → redirect /floor

IssueFelInvoice::handle(FelService)
  → FelService::issue(invoice)
      → DteXmlBuilder::build(check, invoice) → xml string
      → invoice->forceFill(['xml_request' => $xml])->save()
      → adapter->submit($xml, emisor_nit) → FelResult
      → if ok:  invoice->markIssued(uuid, serie, numero, xmlAuthorized)
      → if !ok: invoice->markFailed(error)  →  throws  →  job retries
```

## Key Findings

### Q1 — What does `FelAdapterInterface::submit()` expect?
- `$xml`: DTE XML string (UTF-8, SAT namespace 0.2.0) built by `DteXmlBuilder`
- `$nit`: emisor NIT from config
- Returns `FelResult::success(uuid, serie, numero, xmlAuthorized)` or `FelResult::failure(error)`
- Synchronous, no exceptions — caller checks `$result->ok`

### Q2 — Check close → FEL flow
Automatic on close. `createPending()` is synchronous (creates DB record in controller). `issue()` runs async via queued job with `$tries=3` and `$backoff=[60,300,600]`.

### Q3 — FelInvoice fields
`check_id` (unique), `status` (pending|issued|failed|cancelled), `receptor_nit`, `receptor_name`, `uuid`, `serie`, `numero`, `issued_at`, `xml_request`, `xml_authorized`, `error_message`, `retries`, `cancel_reason`, `cancelled_at`. PKs: ULIDs.

### Q4 — On-demand trigger?
**No.** Purely automatic on check close. Only manual action is admin retry (`POST /admin/fel-invoices/{invoice}/retry`) for failed invoices.

### Q5 — Feature flag pattern
`config('restaurant.fel.enabled')` → `env('FEL_ENABLED', false)`. Checked in `CheckController::close()`. Adapter: `config('restaurant.fel.adapter')` → `env('FEL_ADAPTER', 'null')` resolved via `match` in `FelService::resolveAdapter()`. **Adding `'rpa'` case is the sole FelService change needed.**

### Q6 — Data flow Laravel ↔ Python ↔ SAT
- **Laravel → GitHub Actions**: solo FelInvoice ULID opaco mediante `workflow_dispatch`
- **Laravel → Python**: DTE XML y NIT mediante endpoint HMAC autenticado; nunca como inputs de Actions
- **Python → SAT**: authenticates to Agencia Virtual SAT GT, submits XML, receives authorized XML
- **SAT → Python → Laravel**: UUID, serie, numero, xml_authorized (on success) or error string (on failure)

### Q7 — Webhook callback pattern
**None exists.** No `routes/api.php`. All routes are web/Inertia. New CSRF-exempt route needed at `POST /fel/rpa/callback` — new pattern for this project.

## Affected Files

| File | Change |
|------|--------|
| `app/Services/Fel/Adapters/RpaAdapter.php` | CREATE |
| `app/Services/Fel/FelService.php` | Add `'rpa'` to `resolveAdapter()` match |
| `config/restaurant.php` | Add rpa config keys |
| `routes/web.php` | Add `POST /fel/rpa/callback` (CSRF-exempt) |
| `app/Http/Controllers/FelRpaCallbackController.php` | CREATE |
| `app/Http/Middleware/VerifyCsrfToken.php` | Add callback path to `$except` |
| `tests/Feature/FelTest.php` | Add RPA adapter tests |

## Approach Options

| # | Approach | Pros | Cons |
|---|----------|------|------|
| A | **Sync proc_open inside job** | Zero contract changes, fits retry logic, simplest | Blocks worker thread 10–60s; problematic on DreamHost sync queue |
| B | **Async HTTP + GitHub Actions + callback** | Non-blocking, no Python needed on server | ~1–2 min delay; new callback pattern; more complex |
| C | **Sync + DreamHost guard** | Works on both envs, MVP-ready | Admin must retry on DreamHost; still blocks on Docker |

## Recommendation
**Approach B (GitHub Actions RPA)** — matches the user's explicit requirement (shared hosting, no Python server, occasional use). The 1–2 min delay is acceptable for on-demand use. Callback needs HMAC verification.

## Risks
1. DreamHost sync queue: RPA result arrives async — cannot block HTTP response
2. No callback pattern exists — HMAC security must be implemented correctly
3. SAT portal session — script must re-authenticate on each invocation
4. GitHub Actions startup ~60–120s delay
5. `FEL_ENABLED` flag must gate the on-demand UI button and the callback endpoint
