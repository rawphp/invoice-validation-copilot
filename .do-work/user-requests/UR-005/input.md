---
ur: UR-005
received: 2026-06-25
status: intake
---

# UR-005: User Request

## Request

/validate redirects for both POST and GET, should only redirect for GET requests

## Clarifications

**Q:** Scoping the redirect changes non-GET verbs. After the fix, should PUT/PATCH/DELETE/OPTIONS to `/validate` keep redirecting to `/`, or return 405?
**A:** Keep redirecting all non-POST verbs to `/`. Only POST must reach the controller. (So the requirement is "redirect every verb except POST", not strictly "GET only".)

**Q:** The app already resolves POST→controller and GET→redirect locally, and all 6 tests pass — what's the actual problem you're seeing?
**A:** In the real app, clicking "Validate document" POSTs to `/validate` and gets redirected (302) back to the root route, so the user never sees the Result page. (User-supplied network capture confirms `validate` → 302 → root 200.) GET `/validate` (e.g. a browser refresh of the result URL) redirecting to root IS correct, because there is no GET route to render a result — only POST produces one.

**Q:** Why do local tests pass while production redirects POST? (Reproduced root cause.)
**A:** `Route::redirect('/validate', '/')` registers an `ANY`-method route *before* `Route::post('/validate', ...)`. With route caching on (production / `php artisan route:cache`), the compiled matcher returns the first registered route matching POST — the `ANY` redirect — so POST 302s to `/`. Without caching, `RouteCollection` resolves POST to the controller via method+URI override, so the no-cache test suite passes. Verified: caching routes and re-running the "runs the pipeline" POST test makes it fail with `Expected 200 but received 302`. **Implication for the fix:** a normal (no-cache) feature test does NOT catch this — the regression test must exercise the cached-route path (or the route definition must be made unambiguous so POST can never match the redirect in any mode). The fix must be cache-safe / registration-order-independent.
