# REQ-020: Redirect GET /validate to home

**UR:** UR-003
**Status:** backlog
**Created:** 2026-06-24
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** S
**Files:** routes/web.php, tests/Feature/ValidateInvoiceTest.php
**Depends on:**

## Task

Add a `GET /validate` route that redirects to `/` (HTTP 302). Currently only `POST /validate` exists, so when the browser reloads the Result page — whose URL is `/validate` because Inertia renders the result inline against the POST URL — the resulting `GET /validate` hits no route and returns `405 Method Not Allowed`. The redirect replaces that error with a clean landing on the upload page.

## Context

From UR-003: "when /validate page is reloaded, it should redirect to /". Clarified: a reload of `/validate` currently shows an error (no GET route → 405), and redirecting to `/` fixes it.

`routes/web.php` defines:

```php
Route::get('/', [InvoiceController::class, 'show']);
Route::post('/validate', [InvoiceController::class, 'validateInvoice']);
```

The reload-loses-result trade-off is accepted: the `InvoiceResult` is request-scoped (never persisted), so landing back on the empty upload page and re-uploading is the intended behaviour. The redirect must cover every `GET /validate` (reload, bookmark, shared link, typed URL) — the server cannot distinguish a reload from any other GET. `POST /validate` must remain unchanged.

## Acceptance Criteria

- [ ] A `GET /validate` request returns a 302 redirect to `/` (was: 405 Method Not Allowed)
- [ ] `POST /validate` still renders the `Result` page unchanged (regression guard)
- [ ] `GET /` still renders the `Upload` page unchanged (regression guard)

## Verification Steps

> Execute these after implementation to confirm the fix works at runtime. Each must pass before committing.

1. **test** `./vendor/bin/pest --filter=ValidateInvoiceTest` — Expected: a new test asserting `GET /validate` returns a 302 redirect to `/` passes, and the existing POST `/validate` test still passes.
2. **runtime** Run `php artisan route:list --path=validate` — Expected: both a `GET|HEAD validate` entry and a `POST validate` entry are listed (the GET no longer absent).
3. **test** `./vendor/bin/pest` — Expected: full suite green, no regressions in HomePageTest or ValidateInvoiceTest.

## Assets

- (none)
