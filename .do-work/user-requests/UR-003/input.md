---
ur: UR-003
received: 2026-06-24
status: captured
classification: bug-fix
layers_in_scope: []
layer_decisions: {}
reqs:
  - { id: REQ-020, layer: none, integration_confidence: n/a }
acknowledged_partials: []
---

<!-- capture-summary-start -->
## Capture summary (2026-06-24)

| Item | Value |
|---|---|
| Classification | bug-fix |
| Layers in scope | (none — bug-fix) |
| Layer decisions | (none — all covered) |
| REQs generated | 1 |

| REQ | Layer | Integration confidence |
|---|---|---|
| REQ-020 | none | n/a |
<!-- capture-summary-end -->

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
