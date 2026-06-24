# REQ-003: [PATH] Validate an invoice end-to-end

**UR:** UR-001
**Status:** backlog
**Created:** 2026-06-24
**Layer:** none
**Entry point:** User visits `/`, drags/drops or selects an invoice PDF/PNG/JPG, and submits the form (POST `/validate`). A mobile user reaches the same upload page by scanning the on-page QR code (public `APP_URL`).
**Terminal state:** A single result page renders all outputs for the uploaded invoice: extracted fields (supplier, ABN, dates, line items, rates, GST, total) with per-field confidence, overall confidence + pass/fail badge, service category, validation errors/warnings, an operator-facing supplier-friendly explanation, the structured JSON, and the audit-log timeline.
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 1
**Size:** S
**Files:** .do-work/user-requests/UR-001/input.md
**Depends on:** REQ-004 REQ-005 REQ-006 REQ-007 REQ-008 REQ-009 REQ-010 REQ-011 REQ-012 REQ-013 REQ-014 REQ-015 REQ-016 REQ-017

## Task

Closure unit for the single reachable path of the app: upload an invoice → extract → classify → validate → score → explain → audit → render. This REQ owns the path's closure semantics and depends on all child layer-tasks. No standalone code beyond what the children deliver; it is closed when the full pipeline renders a complete result page for a real Australian invoice.

## Context

The brief's five-step pipeline (upload → extract → classify → check fields → produce JSON/confidence/errors/explanation/audit) is a single user journey. Decomposed into frontend (upload UI, QR, result page) and backend (client seam, intake, extraction, validators, confidence, explanation, audit, orchestrator) children.

## Acceptance Criteria

- [ ] Uploading a valid Australian invoice on `/` and submitting produces a result page showing every output listed in Terminal state.
- [ ] A malformed/non-invoice image yields a graceful low-confidence "couldn't extract a valid invoice" result, not a crash.
- [ ] A Claude API failure renders a friendly error page, not a stack trace.

## Verification Steps

1. **test** `./vendor/bin/pest --filter=ValidateInvoice` — Expected: the end-to-end feature test (Claude client mocked) passes, asserting all result sections are present in the Inertia payload.

## Post-merge validation

- [ ] Upload a real Australian invoice (PDF) on the deployed/served app with a live `ANTHROPIC_API_KEY` — Observable outcome: extracted fields match the document and all result sections render.
- [ ] Scan the on-page QR code with a phone, photograph an invoice, submit — Observable outcome: the phone renders its own complete result page.

## Assets

- .do-work/user-requests/UR-001/assets/ — example screens (vision reference) + Precision Ledger DESIGN.md
