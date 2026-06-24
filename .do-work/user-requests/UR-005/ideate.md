# Ideate — UR-005

**Reviewed:** 2026-06-25

## Explorer — Assumptions & Perspectives

- The brief assumes POST `/validate` is currently being redirected, but the existing feature tests prove otherwise: `tests/Feature/ValidateInvoiceTest.php` POSTs to `/validate` and asserts a 200 Inertia `Result` response, and all 6 tests pass. Laravel keys routes by method+URI, so the explicit `Route::post('/validate', ...)` on line 8 overwrites the POST entry that `Route::redirect` (registered as ANY) created on line 7. So the user-facing POST path works today — what is actually wrong is that the redirect is registered for *every* HTTP method (`route:list` shows `ANY validate → RedirectController`), which is the real target of "should only redirect for GET requests".
- The brief only names GET and POST, but `Route::redirect` registers ANY — meaning PUT/PATCH/DELETE/OPTIONS to `/validate` currently 302-redirect to `/` instead of returning 405 Method Not Allowed. Scoping the redirect to GET changes those methods' behaviour too (they would 405). Worth confirming this is the desired/acceptable side effect, though it is strictly more correct.

## Challenger — Risks & Edge Cases

- The fix is fragile-by-ordering today: because route resolution is last-write-wins per method+URI, the only reason POST works is that line 8 comes after line 7. If someone reorders or adds another method later, the ANY redirect silently reclaims it. A GET-only redirect removes this footgun entirely — the redirect can no longer collide with the POST route regardless of order.
- HEAD: a GET-only redirect in Laravel also answers HEAD (GET implies HEAD). The existing GET redirect test (line 154) asserts 302→`/`; HEAD behaviour is implied and unlikely to matter, but the terminal state should be "GET (and HEAD) redirect; POST hits the controller; other verbs 405".

## Connector — Links & Reuse

- Existing test coverage is the natural home for the regression guard: `tests/Feature/ValidateInvoiceTest.php` already has `it('redirects GET /validate to / with a 302 response')` (line 154) and POST tests asserting 200. The new work is a single-line route change in `routes/web.php` plus one test asserting POST is NOT redirected (and optionally that a non-GET verb like PUT returns 405, not 302).
- No frontend change: the Upload page POSTs to `/validate` via Inertia; that path is unaffected. This is a backend routing-only fix.

## Summary

This is a small, backend-only routing fix: replace `Route::redirect('/validate', '/')` (which registers ANY) with a GET-only redirect so `route:list` shows GET|HEAD instead of ANY. The functional POST path already works due to Laravel's method+URI override, so the real value is removing fragility and making intent explicit — the decomposition should pair the one-line route change with a regression test proving POST reaches the controller (not a redirect) and that the redirect no longer matches non-GET verbs.
