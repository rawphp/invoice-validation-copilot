---
ur: UR-003
received: 2026-06-24
status: intake
---

# UR-003: User Request

## Request

when /validate page is reloaded, it should redirect to /

## Clarifications

**Q:** Why should /validate redirect to / on reload — what's the actual problem?
**A:** A reload of /validate currently shows an error (no GET route exists for /validate, so the browser GET returns 405). Redirecting to / fixes that.

**Q:** The server can't distinguish a reload from any other GET to /validate (bookmark, shared link, typed URL) — should the redirect cover every GET /validate?
**A:** Yes — every GET /validate redirects to / (the only thing that fires on a browser reload). *(inferred, confirmed)*

**Q:** Redirecting to / discards the just-computed result (it's request-scoped, never persisted) — is losing the result acceptable?
**A:** Yes — the goal is just to stop showing the error on reload; result-loss is acceptable. *(inferred, confirmed)*
