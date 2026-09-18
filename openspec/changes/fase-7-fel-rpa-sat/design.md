# Design: Fase 7 — FEL on-demand vía RPA en SAT Guatemala

## Technical Approach

Turn the synchronous `FelAdapterInterface::submit()` contract into one that can return a **pending** outcome without breaking `infile`/`null`. The `RpaAdapter` fires a GitHub Actions `workflow_dispatch` over HTTP and returns immediately; the workflow runs Playwright against the SAT portal (~2 min) and POSTs an HMAC-signed callback that finalizes the invoice and broadcasts `FelInvoiceIssued`. The mesero triggers issuance on-demand from a closed check; the UI moves through a `processing → issued` state machine driven by realtime (Ably/Reverb) with a 15s polling fallback.

The three sync adapters keep their binary ok/failure behavior. Only `FelService::issue()` learns a third branch (`pending → markProcessing`, no throw).

## Architecture Decisions

| Decision | Choice | Alternatives rejected | Rationale |
|----------|--------|-----------------------|-----------|
| Async signaling | `submit()` may return `FelResult::pending()`; `issue()` adds a non-throwing branch | New `submitAsync()` method on interface | Keeps one method; sync adapters unaffected; avoids interface fork |
| Trigger | `workflow_dispatch` via GitHub REST API with opaque `invoice_id` only | `repository_dispatch`; XML/NIT as workflow inputs | Run is queryable for tracing without exposing fiscal data in a public repository |
| Correlation ID | App generates a ULID `correlation_id` **before** dispatch, stored in `rpa_job_id` | Use GitHub run_id from dispatch response | Dispatch returns `204 No Content` with NO run_id; app must own the ID |
| Callback auth | HMAC-SHA256 over raw body, `hash_equals` against `X-FEL-Signature` | IP allowlist, bearer token | GitHub runner IPs rotate; shared secret is portable and deterministic |
| PDF storage | `storage/app/fel/invoices/{invoice_id}.pdf` served via authed route | `public/` disk | Invoices are private fiscal docs; must gate behind auth |
| Realtime | `FelInvoiceIssued implements ShouldBroadcastNow` on `PrivateChannel('check.{id}')` | Queued broadcast | DreamHost runs sync; `ShouldBroadcastNow` matches existing `CheckUpdated` pattern |
| CSRF on callback | Exempt `fel/rpa/callback` in `bootstrap/app.php` | Disable web CSRF globally | External POST has no session token; scope the exemption |

## Data Flow

```
Mesero ──POST /checks/{check}/fel/request──> CheckFelController@request
   │                                              │ createPending() + issue()
   │                                              ▼
   │                                    FelService::issue() → RpaAdapter::submit()
   │                                              │ generate correlation ULID
   │                                              │ POST workflows/{file}/dispatches (204)
   │                                              ▼
   │                                    markProcessing(correlationId)  ──202 {processing}──┐
   ▼                                                                                        │
UI: "Factura en proceso..." <── polling GET /fel/status (15s)  +  Ably/Reverb listener ◄───┘
                                                                          ▲
GitHub Actions runner: HMAC payload fetch → playwright SAT → download PDF│
   │                                                                      │
   └──POST /fel/rpa/callback (HMAC)──> FelRpaCallbackController@handle ───┘
          success: markIssued + store PDF + broadcast FelInvoiceIssued
          failure: markFailed(error)
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Enums/FelStatus.php` | Modify | Add `Processing`; add `isRetryable()`; `isFinal()` excludes Processing |
| `app/Services/Fel/FelResult.php` | Modify | Add `pending()`, `status` flag, `isPending()/isOk()/isFailure()` mutually exclusive |
| `app/Services/Fel/FelService.php` | Modify | `resolveAdapter()` add `'rpa'`; `issue()` add pending branch |
| `app/Services/Fel/Adapters/RpaAdapter.php` | Create | Dispatches workflow, returns pending |
| `app/Models/FelInvoice.php` | Modify | Add `pdf_path`, `rpa_job_id` to fillable; add `markProcessing()` |
| `database/migrations/xxxx_add_rpa_columns_to_fel_invoices.php` | Create | Nullable `pdf_path`, `rpa_job_id` |
| `app/Http/Controllers/Fel/CheckFelController.php` | Create | `request()` + `status()` |
| `app/Http/Controllers/Fel/FelRpaCallbackController.php` | Create | HMAC callback, idempotent |
| `app/Http/Controllers/Fel/FelRpaPayloadController.php` | Create | HMAC + timestamp protected XML/NIT delivery |
| `app/Http/Controllers/Fel/FelPdfController.php` | Create | `show()` streams private PDF |
| `app/Events/FelInvoiceIssued.php` | Create | Broadcast on `check.{id}` |
| `.github/workflows/fel-rpa.yml` | Create | `workflow_dispatch` + Playwright |
| `scripts/fel_sat_rpa.py` | Create | Playwright automation + callback |
| `config/restaurant.php` | Modify | Add `fel.rpa` block |
| `bootstrap/app.php` | Modify | CSRF-exempt `fel/rpa/callback` |
| `routes/web.php` | Modify | 4 routes |
| `resources/js/Pages/.../CheckClosed*.vue` | Modify | Issue button + state machine |

## Interfaces / Contracts

```php
// FelStatus
case Processing = 'processing';
public function isFinal(): bool      { return in_array($this, [self::Issued, self::Cancelled], true); }
public function isRetryable(): bool  { return in_array($this, [self::Failed, self::Processing], true); }
// Transitions: Pending→Processing→{Issued|Failed}; Failed→Pending (retry)

// FelResult — add discriminated state, keep readonly factories
private function __construct(public readonly bool $ok, /*...*/, public readonly bool $pending = false, public readonly string $rpaJobId = '') {}
public static function pending(string $rpaJobId = ''): self { return new self(ok: false, pending: true, rpaJobId: $rpaJobId); }
public function isOk(): bool      { return $this->ok; }
public function isPending(): bool { return $this->pending; }
public function isFailure(): bool { return ! $this->ok && ! $this->pending; }

// FelService::issue() new branch
$result = $this->adapter->submit($xml, $emisorNit);
if ($result->isPending())      { $invoice->markProcessing($result->rpaJobId); return; }
if ($result->isOk())           { $invoice->markIssued(...); return; }
$invoice->markFailed($result->error); throw new RuntimeException(...);

// FelInvoice
public function markProcessing(string $rpaJobId): void
{ $this->forceFill(['status' => FelStatus::Processing, 'rpa_job_id' => $rpaJobId, 'error_message' => null])->save(); }

// RpaAdapter
public function submit(string $xml, string $nit): FelResult {
    $correlationId = (string) Str::ulid();
    // The public workflow receives only an opaque identifier. XML, NIT,
    // callback URL and shared secrets never travel as dispatch inputs.
    $resp = Http::withToken($cfg['github_token'])->acceptJson()
        ->post("https://api.github.com/repos/{$owner}/{$repo}/actions/workflows/{$file}/dispatches", [
            'ref' => 'main',
            'inputs' => [
                'invoice_id' => $correlationId, // == FelInvoice id passed in by caller
            ],
        ]);
    return $resp->status() === 204
        ? FelResult::pending($correlationId)
        : FelResult::failure("GitHub dispatch HTTP {$resp->status()}: {$resp->body()}");
}
public function cancel(string $uuid, string $nit, string $reason): FelResult
{ throw new RuntimeException('RPA adapter does not support cancellation'); }
```

**Note on `invoice_id`**: the adapter does not know the FelInvoice id. Resolve by having `FelService::issue()` pass the live `$invoice->id` into the workflow input (extend the dispatch with the invoice id, OR set `rpa_job_id = $invoice->id` and use that as `invoice_id`). Recommended: workflow `invoice_id` input = `$invoice->id`; `rpa_job_id` stores the value for tracing/idempotency. The adapter needs the invoice id, so pass it through `submit()` context or move dispatch into a dedicated method called from `issue()` with the invoice in scope.

### Routes
```php
Route::middleware('auth')->group(function () {
    Route::post('/checks/{check}/fel/request', [CheckFelController::class, 'request'])->name('fel.request');
    Route::get('/checks/{check}/fel/status', [CheckFelController::class, 'status'])->name('fel.status');
    Route::get('/fel/invoices/{invoice}/pdf', [FelPdfController::class, 'show'])->name('fel.pdf');
});
Route::post('/fel/rpa/callback', [FelRpaCallbackController::class, 'handle'])->name('fel.rpa.callback'); // CSRF-exempt
Route::post('/fel/rpa/payload', [FelRpaPayloadController::class, 'show'])->name('fel.rpa.payload'); // CSRF-exempt, HMAC + timestamp
```

### Callback controller contract
```php
public function handle(Request $req) {
    $raw = $req->getContent();
    $sig = hash_hmac('sha256', $raw, (string) config('restaurant.fel.rpa.hmac_secret'));
    abort_unless(hash_equals($sig, (string) $req->header('X-FEL-Signature')), 403);
    $data = $req->json();
    $invoice = FelInvoice::findOrFail($data->get('invoice_id'));
    if ($invoice->status === FelStatus::Issued) return response()->noContent(200); // idempotent
    if ($error = $data->get('error')) { $invoice->markFailed($error); return response()->noContent(200); }
    if ($pdf = $data->get('pdf_base64')) Storage::disk('local')->put("fel/invoices/{$invoice->id}.pdf", base64_decode($pdf)) && $invoice->forceFill(['pdf_path' => "fel/invoices/{$invoice->id}.pdf"])->save();
    $invoice->markIssued($data->get('uuid'), $data->get('serie'), $data->get('numero'), $data->get('xml_authorized'));
    FelInvoiceIssued::dispatch($invoice->fresh());
    return response()->noContent(200);
}
```

### CheckFelController validation (request)
- `abort_unless(in_array($check->status, ['closed','paid']), 422)`
- `abort_if($check->felInvoice()->exists(), 409)`
- gate: `abort_unless(config('restaurant.fel.enabled') && config('restaurant.fel.adapter') === 'rpa', 404)`
- body: `nit` nullable string default `CF`; `name` nullable string default `Consumidor Final`
- `createPending()` + dispatch `IssueFelInvoice` (or call `issue()` inline) → `response()->json(['invoice_id'=>$id,'status'=>'processing'], 202)`

### status() response
`{status, invoice_id, pdf_url}` when invoice exists, else `{status:'none'}`.

### Migration schema
```php
Schema::table('fel_invoices', function (Blueprint $t) {
    $t->string('pdf_path')->nullable()->after('xml_authorized');
    $t->string('rpa_job_id')->nullable()->after('pdf_path');
});
```

### Config block
```php
'rpa' => [
    'github_token'      => env('FEL_RPA_GITHUB_TOKEN'),
    'github_repo_owner' => env('FEL_RPA_GITHUB_REPO_OWNER'),
    'github_repo_name'  => env('FEL_RPA_GITHUB_REPO_NAME'),
    'workflow_filename' => env('FEL_RPA_WORKFLOW_FILE', 'fel-rpa.yml'),
    'hmac_secret'       => env('FEL_RPA_HMAC_SECRET'),
],
```

### Workflow skeleton `.github/workflows/fel-rpa.yml`
```yaml
name: FEL RPA SAT
on:
  workflow_dispatch:
    inputs:
      invoice_id:   { description: 'FelInvoice id', required: true, type: string }
jobs:
  rpa:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-python@v5
        with: { python-version: '3.12' }
      - run: pip install playwright requests && playwright install --with-deps chromium
      - name: Run SAT RPA
        env:
          SAT_USUARIO:     ${{ secrets.SAT_USUARIO }}
          SAT_PASSWORD:    ${{ secrets.SAT_PASSWORD }}
          FEL_HMAC_SECRET: ${{ secrets.FEL_HMAC_SECRET }}
          FEL_APP_URL:     ${{ secrets.FEL_APP_URL }}
        run: |
          python scripts/fel_sat_rpa.py \
            --invoice-id "${{ inputs.invoice_id }}" \
            --app-url "$FEL_APP_URL"
```

### Python skeleton `scripts/fel_sat_rpa.py`
```python
# args: --invoice-id --app-url
# env:  SAT_USUARIO, SAT_PASSWORD, FEL_HMAC_SECRET
import os, json, hmac, hashlib, base64, argparse, requests
from playwright.sync_api import sync_playwright

def post_callback(url, secret, payload):
    body = json.dumps(payload).encode()
    sig  = hmac.new(secret.encode(), body, hashlib.sha256).hexdigest()
    requests.post(url, data=body, headers={'X-FEL-Signature': sig, 'Content-Type': 'application/json'})

def main(a):
    secret = os.environ['FEL_HMAC_SECRET']
    try:
        # POST invoice_id + timestamp to /fel/rpa/payload, signing
        # "{timestamp}.{raw_body}". Reject responses unless HTTPS succeeds.
        # The response supplies xml_base64, emisor_nit and callback_url.
        with sync_playwright() as p:
            b = p.chromium.launch(headless=True); pg = b.new_page()
            # TODO: pg.goto(SAT_PORTAL_LOGIN_URL)
            # TODO: fill SAT_USUARIO / SAT_PASSWORD, submit
            # TODO: navigate to "emitir DTE", upload base64 XML / paste fields
            # TODO: confirm emission, capture uuid/serie/numero
            # TODO: download authorized PDF -> pdf_bytes
            uuid=serie=numero=xml_auth=''; pdf_bytes=b''  # TODO map from portal
            post_callback(a.callback_url, secret, {
                'invoice_id': a.invoice_id, 'uuid': uuid, 'serie': serie, 'numero': numero,
                'xml_authorized': xml_auth, 'pdf_base64': base64.b64encode(pdf_bytes).decode()})
    except Exception as e:
        post_callback(a.callback_url, secret, {'invoice_id': a.invoice_id, 'error': str(e)})
    return 0  # always 0 — business result travels over the callback
```

### Vue state machine
```
none ──click "Generar Factura"──> processing
processing ──FelInvoiceIssued event | poll status=issued──> issued (download PDF)
processing ──poll status=failed──> failed (botón Reintentar)
```
- Listen on `Echo.private('check.'+checkId).listen('.FelInvoiceIssued', …)`; `broadcastAs()` returns `FelInvoiceIssued`.
- Poll `GET /checks/{check}/fel/status` every 15s while `processing`; clear interval on `issued/failed` or on event arrival.

## Testing Strategy

| Layer | What to Test | Approach |
|-------|--------------|----------|
| Unit | `FelResult` mutual exclusivity (ok/pending/failure); `FelStatus::isFinal/isRetryable` | Plain PHPUnit assertions |
| Unit | `RpaAdapter::submit()` returns pending on 204, failure otherwise; `cancel()` throws | `Http::fake()` |
| Unit | `FelService::issue()` pending branch calls `markProcessing`, no throw | Fake adapter returning pending |
| Feature | `CheckFelController@request`: 202 on closed check, 409 if invoice exists, 404 when adapter≠rpa | `RefreshDatabase`, `Http::fake()` |
| Feature | Callback: 403 on bad HMAC, 200 issued on valid, idempotent on re-post, failure marks Failed | Sign body with secret |
| Feature | Payload: valid timestamped HMAC returns XML/NIT; invalid or stale signature returns 403 with no fiscal data | Sign `{timestamp}.{raw_body}` |
| Feature | `FelPdfController@show`: 200 for authed owner, streams stored PDF; 404 if no `pdf_path` | `Storage::fake('local')` |
| Feature | `FelInvoiceIssued` broadcast on callback success | `Event::fake()` |

Strict TDD: write each test RED first, then implement to GREEN. SQLite `:memory:` + `RefreshDatabase`.

## Migration / Rollout

Additive nullable migration — reversible. `Processing` case does not touch existing invoices. Rollback = set `FEL_ADAPTER=infile|null` or `FEL_ENABLED=false`; new routes self-gate via the adapter check. No data backfill.

## Open Questions

- [ ] `submit()` signature carries no invoice id — confirm whether to pass `$invoice->id` as an extra param to a dedicated dispatch method or set `rpa_job_id = $invoice->id` before dispatch so the workflow `invoice_id` matches the real record.
- [ ] Stale `Processing` invoices when the callback never arrives — proposal mentions a sweeper job (out of scope here); confirm if a scheduled timeout→Failed task lands in this phase or later.
- [ ] SAT portal selectors are unknown — Python script ships with TODO markers; mapping is a manual developer task and an accepted operational risk.
