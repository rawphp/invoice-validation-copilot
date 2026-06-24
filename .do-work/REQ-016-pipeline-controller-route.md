# REQ-016: Pipeline orchestrator + InvoiceController + routes

**UR:** UR-001
**Status:** backlog
**Created:** 2026-06-24
**Layer:** backend
**Entry point:**
**Terminal state:**
**Parent:** REQ-003
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** L
**Files:** app/Services/Invoice/InvoicePipeline.php, app/DTO/InvoiceResult.php, app/Http/Controllers/InvoiceController.php, routes/web.php, tests/Feature/ValidateInvoiceTest.php
**Depends on:** REQ-007, REQ-008, REQ-009, REQ-010, REQ-011, REQ-012, REQ-013, REQ-014, REQ-015

## Task

Wire the pipeline. `InvoicePipeline` runs the ordered flow — FileIntake → Extraction → Validation (required + ABN + arithmetic + date) → ConfidenceScorer → ExplanationService — recording each step into `AuditLog`, and returns an `InvoiceResult` aggregate (extracted invoice, validation errors, overall + per-field confidence, category, explanation, audit entries). `InvoiceController` exposes GET `/` (upload page) and POST `/validate` (run pipeline, Inertia-render the result). Handle Claude API / extraction failure by rendering a friendly error result, never a stack trace.

## Context

Brief's five-step pipeline as one orchestrated request returning the full result payload. Ideate: ordering must be extract → validate → score → explain; graceful API-failure path is the highest-impact runtime safeguard given no mock fallback.

## Acceptance Criteria

- [ ] GET `/` returns an Inertia upload page (HTTP 200).
- [ ] POST `/validate` with a fixture invoice (Claude client faked) returns an Inertia result payload containing extracted fields, per-field + overall confidence, category, validation errors, explanation, and audit entries.
- [ ] The pipeline runs steps in order extract → validate → score → explain and appends an audit entry per step.
- [ ] A thrown Claude/API error is caught and rendered as a friendly error result (assert no 500/stack trace; a user-facing error message is present).
- [ ] Validators are all registered so a seeded bad invoice surfaces ABN, arithmetic, and date errors together.

## Verification Steps

1. **test** `./vendor/bin/pest --filter=ValidateInvoice` — Expected: green; happy path returns full payload, forced client exception yields a friendly error payload (no 500).
2. **runtime** `php artisan serve`; `curl -I http://localhost:8000/` — Expected: HTTP 200 upload page.

## Integration

**Reachability:** Routes in `routes/web.php` (`GET /`, `POST /validate`) reached from the upload page form (REQ-004) and the mobile QR (REQ-005). Renders the result page (REQ-017).

**Data dependencies:** Orchestrates `FileIntake` (REQ-007), `ExtractionService` (REQ-008), `ValidationService` + validators (REQ-009–012), `ConfidenceScorer` (REQ-013), `ExplanationService` (REQ-014), `AuditLog` (REQ-015); produces `InvoiceResult`.

**Service dependencies:** Constructor-injects all of the above services from the container.

## Assets

- (none)
