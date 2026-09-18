# Tasks: Fase 7 — FEL on-demand vía RPA en SAT Guatemala

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 650–800 |
| 400-line budget risk | High |
| Chained PRs recommended | No |
| Suggested split | single PR (delivery strategy: single-pr) |
| Delivery strategy | single-pr |
| Chain strategy | size-exception |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: size-exception
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | All 25 tasks | PR 1 | Single PR with maintainer-approved size:exception. Delivery strategy = single-pr; exception already accepted. |

---

## Phase 1 — Domain Layer (Foundation)

- [ ] 1.1 **feat(fel): extend FelAdapterInterface::submit() with optional $invoiceId param**
  - Files: `app/Services/Fel/Contracts/FelAdapterInterface.php`
  - Add `string $invoiceId = ''` as third parameter to `submit()`. FIRST task — all adapters must update after this.
  - Satisfies: RPA adapter correlation ID design decision; `workflow_dispatch inputs.invoice_id` requirement.

- [ ] 1.2 **feat(fel): add FelStatus::Processing case + isRetryable()**
  - Files: `app/Enums/FelStatus.php`
  - Add `case Processing = 'processing'`. Update `isFinal()` to exclude Processing. Add `isRetryable()` returning `true` for `Failed` and `Processing`.
  - Satisfies: spec "FelStatus States" — `pending→Processing→issued/failed` transition table.

- [ ] 1.3 **feat(fel): add FelResult::pending() factory + isPending()/isOk()/isFailure() helpers**
  - Files: `app/Services/Fel/FelResult.php`
  - Add `readonly bool $pending = false` and `readonly string $rpaJobId = ''` to constructor. Add `pending()`, `isPending()`, `isOk()`, `isFailure()` methods.
  - Satisfies: spec "FelResult Contract" — three mutually exclusive states.

- [ ] 1.4 **feat(fel): migration — add pdf_path and rpa_job_id to fel_invoices**
  - Files: `database/migrations/xxxx_add_rpa_columns_to_fel_invoices.php`
  - Nullable `string pdf_path` after `xml_authorized`; nullable `string rpa_job_id` after `pdf_path`. Reversible `down()`.
  - Satisfies: spec "FelInvoice model — pdf_path and rpa_job_id".

- [ ] 1.5 **feat(fel): add FelInvoice::markProcessing() + fillable columns**
  - Files: `app/Models/FelInvoice.php`
  - Add `pdf_path`, `rpa_job_id` to `$fillable`. Add `markProcessing(string $rpaJobId): void` using `forceFill`.
  - Satisfies: spec scenario "markProcessing persists run ID".

- [ ] 1.6 **feat(fel): update NullAdapter and InfileAdapter — accept $invoiceId, ignore it**
  - Files: `app/Services/Fel/Adapters/NullAdapter.php`, `app/Services/Fel/Adapters/InfileAdapter.php`
  - Add `string $invoiceId = ''` third param to `submit()`. No behavior change.
  - Satisfies: interface contract update from 1.1; keeps sync adapters unaffected.

- [ ] 1.7 **feat(fel): create RpaAdapter — dispatch workflow_dispatch, return pending**
  - Files: `app/Services/Fel/Adapters/RpaAdapter.php`
  - Read config `restaurant.fel.rpa.*`. POST to GitHub Actions API with only the opaque `invoice_id` input. Return `FelResult::pending($invoiceId)` on 204, `FelResult::failure(...)` on non-204. `cancel()` throws `RuntimeException`.
  - Satisfies: spec "GitHub Actions workflow dispatch" — both dispatch and failure scenarios.

- [ ] 1.8 **feat(fel): update FelService::issue() — handle pending result branch**
  - Files: `app/Services/Fel/FelService.php`
  - After adapter `submit()`, add `if ($result->isPending()) { $invoice->markProcessing($result->rpaJobId); return; }` before existing ok/failure branches. Add `'rpa'` case to `resolveAdapter()`.
  - Satisfies: spec "FelService::issue() handles pending result" — all three scenarios.

- [ ] 1.9 **feat(fel): create FelInvoiceIssued broadcast event**
  - Files: `app/Events/FelInvoiceIssued.php`
  - Implements `ShouldBroadcastNow`. Broadcasts on `PrivateChannel('check.'.$invoice->check_id)`. `broadcastAs()` returns `'FelInvoiceIssued'`. Payload: `invoice_id`, `status = 'issued'`.
  - Satisfies: spec "Realtime notification" — broadcast channel and payload scenarios.

---

## Phase 2 — HTTP Layer

- [ ] 2.1 **feat(fel): create CheckFelController — request() and status() actions**
  - Files: `app/Http/Controllers/Fel/CheckFelController.php`
  - `request()`: gate on `fel.enabled` + adapter=rpa (404), check closed (422), no existing invoice (409), validate `nit`/`name` with defaults `CF`/`Consumidor Final`, call `FelService::issue()`, return 202.
  - `status()`: return `{status, invoice_id, pdf_url}` or `{status: null}`. Satisfies all 8 `/request` + 3 `/status` spec scenarios.

- [ ] 2.2 **feat(fel): create FelRpaCallbackController — HMAC-verified callback**
  - Files: `app/Http/Controllers/Fel/FelRpaCallbackController.php`
  - Verify `X-FEL-Signature` with `hash_hmac`/`hash_equals` (403 on mismatch). `findOrFail` invoice. Idempotency guard on `Issued`. Store PDF if `pdf_base64` present. Call `markIssued()`/`markFailed()`. Dispatch `FelInvoiceIssued`.
  - Satisfies: all 6 callback spec scenarios including CSRF exemption (handled in 2.4).

- [ ] 2.2a **feat(fel): create FelRpaPayloadController — private fiscal payload delivery**
  - Files: `app/Http/Controllers/Fel/FelRpaPayloadController.php`
  - Verify HMAC over `{timestamp}.{raw_body}` and reject timestamps outside five minutes. Return XML/NIT/callback URL only for the requested invoice. Never include credentials or secrets.
  - Satisfies: spec "Private RPA payload delivery" and keeps fiscal data out of public Actions inputs.

- [ ] 2.3 **feat(fel): create FelPdfController — stream authenticated private PDF**
  - Files: `app/Http/Controllers/Fel/FelPdfController.php`
  - Gate auth. `abort_if(! $invoice->pdf_path, 404)`. `Storage::disk('local')->download(...)` with `Content-Type: application/pdf`.
  - Satisfies: spec "PDF storage" — authenticated download and unauthenticated blocked scenarios.

- [ ] 2.4 **feat(fel): register routes + CSRF exemption for callback**
  - Files: `routes/web.php`, `bootstrap/app.php`
  - Add 5 routes (3 under `auth` middleware + bare callback/payload routes). Exempt `fel/rpa/callback` and `fel/rpa/payload` in `bootstrap/app.php` `withMiddleware` CSRF except list.
  - Satisfies: spec "Feature flag gating"; spec "CSRF exemption confirmed" scenario.

---

## Phase 3 — Config + Infra

- [ ] 3.1 **feat(fel): add RPA config block to config/restaurant.php**
  - Files: `config/restaurant.php`
  - Add `'rpa' => [github_token, github_repo_owner, github_repo_name, workflow_filename, hmac_secret]` nested under `fel`.
  - Satisfies: adapter config wiring for RpaAdapter and callback HMAC verification.

- [ ] 3.2 **chore(fel): add RPA env vars to .env.example**
  - Files: `.env.example`
  - Document `FEL_RPA_GITHUB_TOKEN`, `FEL_RPA_GITHUB_REPO_OWNER`, `FEL_RPA_GITHUB_REPO_NAME`, `FEL_RPA_WORKFLOW_FILE`, `FEL_RPA_HMAC_SECRET` with empty defaults. Configure `FEL_APP_URL` only as a GitHub Actions secret.
  - Satisfies: developer onboarding for RPA adapter.

- [ ] 3.3 **feat(fel): create .github/workflows/fel-rpa.yml skeleton**
  - Files: `.github/workflows/fel-rpa.yml`
  - `workflow_dispatch` trigger with only the opaque `invoice_id` input. Job secrets supply `FEL_APP_URL`, `FEL_HMAC_SECRET`, and SAT credentials; no fiscal data or secret travels as an input. Job: checkout, setup-python 3.12, install dependencies, run `scripts/fel_sat_rpa.py`.
  - Satisfies: spec "GitHub Actions workflow dispatch" — dispatch inputs contract.

- [ ] 3.4 **feat(fel): create scripts/fel_sat_rpa.py with TODO SAT selectors**
  - Files: `scripts/fel_sat_rpa.py`
  - argparse for `--invoice-id --app-url`. HMAC helpers fetch the private payload (timestamped request) and post the callback. Playwright `sync_playwright` block with `# TODO` markers for SAT portal selectors. Always returns 0 — errors travel via callback.
  - Satisfies: design "Python skeleton" — operational risk accepted, SAT selectors deferred.

---

## Phase 4 — Frontend

- [ ] 4.1 **feat(fel): add "Generar Factura" button + NIT/name form to CheckClosed view**
  - Files: `resources/js/Pages/Checks/CheckClosed.vue` (or equivalent closed-check view)
  - Show button when `felEnabled && felAdapter === 'rpa' && !invoice`. On submit: POST `/checks/{check}/fel/request` via Inertia or axios. Transition component to `processing` state.
  - Satisfies: spec on-demand issuance — waiter trigger flow.

- [ ] 4.2 **feat(fel): add processing state + realtime listener + polling fallback**
  - Files: `resources/js/Pages/Checks/CheckClosed.vue`
  - `Echo.private('check.'+checkId).listen('.FelInvoiceIssued', ...)` transitions to issued. `setInterval` 15s polling to `GET /checks/{check}/fel/status`; clear on issued/failed or event arrival.
  - Satisfies: spec "Realtime notification" — event listener and polling fallback scenarios.

- [ ] 4.3 **feat(fel): add PDF download button when invoice is issued**
  - Files: `resources/js/Pages/Checks/CheckClosed.vue`
  - When state = `issued` and `pdf_url` present, render anchor or button linking to `GET /fel/invoices/{invoice}/pdf`. Show "Reintentar" when state = `failed`.
  - Satisfies: spec "PDF storage — authenticated PDF download" and UX state machine.

---

## Phase 5 — Tests (Strict TDD: write RED first, then implement GREEN)

> Each test task pairs with the implementation task it gates. In TDD order: write the test first (task goes RED), then implement the corresponding Phase 1–4 task to turn it GREEN.

- [ ] 5.1 **test(fel): unit — FelResult mutual exclusivity + FelStatus transitions**
  - Files: `tests/Unit/Fel/FelResultTest.php`, `tests/Unit/Fel/FelStatusTest.php`
  - Assert `pending()` → `ok=false, pending=true, error=null`. Assert `success()` → `pending=false`. Assert `FelStatus::Processing` not final, is retryable. Assert `Issued` is final.
  - Covers: spec "FelResult Contract" — both scenarios; spec "FelStatus States" — isFinal/isRetryable.

- [ ] 5.2 **test(fel): unit — RpaAdapter::submit() mocked Http facade**
  - Files: `tests/Unit/Fel/RpaAdapterTest.php`
  - `Http::fake(['*' => Http::response('', 204)])` → assert returns `FelResult::pending()`. `Http::fake(['*' => Http::response('err', 500)])` → returns `FelResult::failure()`. Verify the dispatch input contains only `invoice_id` and no fiscal data or secrets.
  - Covers: spec "GitHub Actions workflow dispatch" — both scenarios.

- [ ] 5.3 **test(fel): unit — FelService::issue() pending path and no regression on sync path**
  - Files: `tests/Unit/Fel/FelServiceTest.php`
  - Fake adapter returning `pending()` → invoice becomes `Processing`, no throw. Fake adapter returning `success()` → `issued`. Fake adapter returning `failure()` → `failed` + exception thrown.
  - Covers: spec "FelService::issue()" — all three scenarios.

- [ ] 5.4 **test(fel): feature — CheckFelController@request (all 8 spec scenarios)**
  - Files: `tests/Feature/Fel/CheckFelControllerTest.php`
  - Tests: 202 happy path, 422 check not closed, 409 invoice exists, 404 FEL disabled, 422 adapter≠rpa, 401 unauthenticated, NIT/name defaults, custom NIT stored. Use `RefreshDatabase` + `Http::fake()` for dispatch.
  - Covers: spec "On-demand issuance endpoint" — all 8 scenarios.

- [ ] 5.5 **test(fel): feature — CheckFelController@status (3 spec scenarios)**
  - Files: `tests/Feature/Fel/CheckFelControllerTest.php` (extend same file)
  - Tests: 200 with status when invoice exists, 200 `{status:null}` when no invoice, 401 unauthenticated.
  - Covers: spec "Realtime notification — polling fallback" scenarios.

- [ ] 5.6 **test(fel): feature — FelRpaCallbackController (all 6 scenarios)**
  - Files: `tests/Feature/Fel/FelRpaCallbackControllerTest.php`
  - Tests: 403 bad HMAC, 200 issued on valid success callback + `FelInvoiceIssued` fired, 200 failed on failure callback, 404 invoice not found, 200 idempotent re-post, 200 with pdf_base64 stores PDF file. Use `Event::fake()` and `Storage::fake('local')`.
  - Covers: spec "RPA Callback endpoint" — all 6 scenarios.

- [ ] 5.6a **test(fel): feature — FelRpaPayloadController security**
  - Files: `tests/Feature/Fel/FelRpaPayloadControllerTest.php`
  - Tests: valid timestamped HMAC returns XML/NIT; invalid HMAC and timestamp older than five minutes each return 403 without fiscal data.
  - Covers: spec "Private RPA payload delivery".

- [ ] 5.7 **test(fel): feature — FelPdfController (authed 200, unauthed 401, missing pdf 404)**
  - Files: `tests/Feature/Fel/FelPdfControllerTest.php`
  - Use `Storage::fake('local')` to seed a PDF. Assert 200 + `Content-Type: application/pdf`. Assert 401 for guest. Assert 404 when `pdf_path` is null.
  - Covers: spec "PDF storage" — all three download scenarios.
