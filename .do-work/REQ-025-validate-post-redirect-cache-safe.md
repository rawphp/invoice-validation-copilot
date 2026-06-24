# REQ-025: POST /validate must reach controller regardless of route cache

**UR:** UR-005
**Status:** backlog
**Created:** 2026-06-25
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

Fix `/validate` routing so a POST always reaches `InvoiceController@validateInvoice` while GET (and other non-POST verbs) redirect to `/` — and make this hold **regardless of route-cache state**.

Root cause (reproduced): [routes/web.php](routes/web.php#L7-L8) registers `Route::redirect('/validate', '/')` *before* `Route::post('/validate', ...)`. `Route::redirect` registers an `ANY`-method route (GET, HEAD, POST, PUT, PATCH, DELETE, OPTIONS). Without route caching, Laravel's `RouteCollection` resolves a POST to the controller via its method+URI override, so the local test suite passes. **With route caching on (production / `php artisan route:cache`), the compiled matcher returns the first registered route matching POST — the `ANY` redirect — so POST 302s to `/`** and the user never sees the Result page.

Fix: register the redirect so it can **never** match POST, in a way that does not depend on registration order. Keep redirecting all non-POST verbs to `/` (per UR-005 clarification — only POST must reach the controller). Recommended:

```php
Route::get('/', [InvoiceController::class, 'show']);
Route::post('/validate', [InvoiceController::class, 'validateInvoice']);
Route::match(['get', 'head', 'put', 'patch', 'delete', 'options'], '/validate', fn () => redirect('/'));
```

(or any equivalent where the `/validate` redirect route's HTTP-method list excludes POST). Do not leave a `Route::redirect('/validate', ...)` `ANY` route in place.

## Context

From UR-005 brief: "/validate redirects for both POST and GET, should only redirect for GET requests."

Clarifications (UR-005/input.md):
- Real-world symptom: clicking "Validate document" POSTs to `/validate` and gets a 302 back to `/`, so the Result page is never shown. User-supplied network capture confirms `validate` → 302 → root 200.
- GET `/validate` (e.g. browser refresh of the result URL) redirecting to `/` is correct — there is no GET route that renders a result.
- All non-POST verbs should keep redirecting to `/`; only POST must reach the controller.

> ⚠️ The existing no-cache feature tests (6 passing) do NOT catch this bug — they resolve POST→controller because routes aren't cached. The regression guard must be cache-independent: assert the `/validate` redirect route's HTTP-method list excludes POST, and reproduce under `php artisan route:cache`.

## Acceptance Criteria

- [ ] POST `/validate` reaches `InvoiceController@validateInvoice` and returns a 200 Inertia `Result` response **with routes cached** (`php artisan route:cache`) — the previously-failing condition.
- [ ] GET `/validate` returns 302 and redirects to `/`.
- [ ] A non-POST, non-GET verb (PUT `/validate`) returns 302 and redirects to `/` (not 405) — confirms other verbs still redirect.
- [ ] The registered `/validate` redirect route's HTTP-method list does NOT contain `POST` — a deterministic, cache-independent regression guard added to `tests/Feature/ValidateInvoiceTest.php`.
- [ ] The full Pest suite passes both without route cache and with route cache; `php artisan route:clear` is run afterward so no stale cache is left behind.

## Verification Steps

> Execute in order. Each must pass before committing.

1. **test** Add a failing-first regression test to `tests/Feature/ValidateInvoiceTest.php` asserting the `/validate` redirect route excludes POST (e.g. find the route via `app('router')->getRoutes()` whose URI is `validate` and action is `Illuminate\Routing\RedirectController`, and assert `POST` is not in its `methods()`), plus a `put('/validate')->assertStatus(302)->assertRedirect('/')` case. Run `./vendor/bin/pest tests/Feature/ValidateInvoiceTest.php --compact`.
   - Expected: the new assertions FAIL against the current `routes/web.php` (proving the bug), then PASS after the route fix.
2. **runtime** Reproduce the production condition: `php artisan route:cache && ./vendor/bin/pest tests/Feature/ValidateInvoiceTest.php --compact; php artisan route:clear`.
   - Expected: with the fix, every test passes under cached routes (POST `/validate` returns 200, not 302). Before the fix, the POST pipeline test fails with `Expected 200 but received 302`. Route cache is cleared at the end.
3. **test** Run the full suite `./vendor/bin/pest --compact`.
   - Expected: all green, no regressions to the existing 6 `/validate` tests.

## Assets

(none)
