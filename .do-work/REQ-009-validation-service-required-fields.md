# REQ-009: ValidationService + ValidationError DTO + RequiredFieldsValidator

**UR:** UR-001
**Status:** backlog
**Created:** 2026-06-24
**Layer:** backend
**Entry point:**
**Terminal state:**
**Parent:** REQ-003
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** M
**Files:** app/Services/Validation/ValidationService.php, app/Services/Validation/Validator.php, app/Services/Validation/RequiredFieldsValidator.php, app/DTO/ValidationError.php, tests/Unit/Validation/RequiredFieldsValidatorTest.php
**Depends on:** REQ-008

## Task

Build the deterministic validation core: a `ValidationError` DTO (`field`, `severity`, `message`), a `Validator` contract, a `ValidationService` that runs a set of validators over an `ExtractedInvoice` and aggregates their errors, and the first validator — `RequiredFieldsValidator`, which flags any missing mandatory field (supplier, ABN, invoice date, line items, subtotal, GST, total). No LLM.

## Context

Brief step 4 "Checks required fields" + output "validation errors". Establishes the aggregator + DTO that the ABN, arithmetic, and date validators (REQ-010–012) plug into. Ideate Connector: confidence scoring (REQ-013) consumes the aggregated errors.

## Acceptance Criteria

- [ ] `ValidationError` carries `field`, `severity` (e.g. error/warning), and a human-readable `message`.
- [ ] `ValidationService` accepts a list of `Validator`s and returns the merged list of `ValidationError`s for an `ExtractedInvoice`.
- [ ] `RequiredFieldsValidator` returns one error per missing mandatory field and an empty list when all are present.
- [ ] Severity for a missing field is `error`.

## Verification Steps

1. **test** `./vendor/bin/pest --filter=RequiredFieldsValidator` — Expected: green; missing-ABN invoice yields exactly one error on field `abn`; complete invoice yields none.

## Integration

**Reachability:** `ValidationService` is invoked by the pipeline orchestrator (REQ-016) after extraction.

**Data dependencies:** Reads `ExtractedInvoice` (REQ-008); produces `ValidationError[]` consumed by confidence scoring (REQ-013), explanation (REQ-014), and the result page (REQ-017).

**Service dependencies:** Hosts the `Validator` contract that REQ-010/011/012 implement.

## Assets

- (none)
