# Ideate — UR-003

**Reviewed:** 2026-06-24

## Explorer — Assumptions & Perspectives

- The `/validate` URL is only ever visible because Inertia renders the `Result` page in response to `POST /validate` while leaving the browser URL at `/validate`. The brief assumes "reload" but the same broken state is hit by any `GET /validate` — a manual address-bar visit, a bookmark, or a shared link. The fix should cover all GET arrivals at `/validate`, not just F5. (Triggered by: "when /validate page is reloaded".)
- The affected user is anyone who finishes a validation and then refreshes — today they get a bare `405 Method Not Allowed` (no GET route exists for `/validate` in `routes/web.php`), which reads as a broken app rather than an expired result. (Triggered by: the reload scenario in the brief.)

## Challenger — Risks & Edge Cases

- Redirecting to `/` silently discards the just-computed `InvoiceResult` — the user must re-upload to see it again. This is the correct trade-off (the result is request-scoped, not persisted), but it means "redirect to /" is a deliberate data-loss decision, not a no-op. Worth stating so it isn't later mistaken for a bug. (Triggered by: "redirect to /".)
- The redirect must be a GET-only route. `POST /validate` MUST keep working unchanged — if the new route accidentally captures POST too, the entire validation flow breaks. (Triggered by: adding a route for `/validate`.)
- Status code matters for Inertia: a plain `Route::redirect` returns 302, which a browser reload handles fine. No Inertia-specific external redirect (409) is needed because this is a top-level browser GET, not an Inertia XHR visit. (Triggered by: "redirect".)

## Connector — Links & Reuse

- Pure routing change. `routes/web.php` already pairs `GET /` (Upload) and `POST /validate`; this adds `GET /validate → redirect('/')` alongside them. No controller method, DTO, or pipeline change needed — likely a one-line `Route::redirect('/validate', '/')` or `Route::get` closure.
- No conflict with recorded decisions (REQ-006, REQ-008 concern the Claude/extraction layer, untouched here).

## Summary

This is a one-line routing fix: add a `GET /validate` route that 302-redirects to `/`, leaving `POST /validate` untouched. The only judgement call is accepting that the result is discarded on reload (correct — it's request-scoped). A feature test asserting `GET /validate` redirects to `/` while `POST /validate` still renders Result is the natural acceptance check.
