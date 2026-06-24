# REQ-012: DateValidator (AU locale, sanity)

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
**Size:** M
**Files:** app/Services/Validation/DateValidator.php, tests/Unit/Validation/DateValidatorTest.php
**Depends on:** REQ-009

## Task

Implement `DateValidator` (a `Validator`): parse the invoice date and due date as AU-locale DD/MM/YYYY, and assert (a) both parse, (b) the invoice date is not in the future (relative to an injectable "now"), and (c) the due date is on or after the invoice date. Emit a `ValidationError` per failed check.

## Context

Clarification: Australian only → DD/MM dates. Ideate Challenger: DD/MM vs MM/DD ambiguity can silently corrupt the future-date check; pinning AU locale resolves it. Use an injectable clock so the "not future" test is deterministic.

## Acceptance Criteria

- [ ] "24/10/2023" parses as 24 October 2023 (DD/MM), not 10 February.
- [ ] An invoice date after the injected "now" yields one `error` on `invoice_date`.
- [ ] A due date before the invoice date yields one `error` on `due_date`.
- [ ] An unparseable date string yields a format error rather than throwing.

## Verification Steps

1. **test** `./vendor/bin/pest --filter=DateValidator` — Expected: green; DD/MM parsed correctly, future invoice date flagged against injected clock, due<invoice flagged.

## Integration

**Reachability:** Registered into `ValidationService` (REQ-009) and run by the orchestrator (REQ-016).

**Data dependencies:** Reads `ExtractedInvoice.invoiceDate` / `dueDate` (REQ-008); emits `ValidationError` (REQ-009).

**Service dependencies:** Implements the `Validator` contract from REQ-009; depends on an injectable clock (Carbon test-now).

## Assets

- (none)
