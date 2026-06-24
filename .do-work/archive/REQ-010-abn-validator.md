# REQ-010: AbnValidator (ATO checksum)

**UR:** UR-001
**Status:** done
**Created:** 2026-06-24
**Layer:** backend
**Entry point:**
**Terminal state:**
**Parent:** REQ-003
**Closure proof:** checkpoint_log:passed commit:aa43f37
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** S
**Files:** app/Services/Validation/AbnValidator.php, tests/Unit/Validation/AbnValidatorTest.php
**Depends on:** REQ-009

## Task

Implement `AbnValidator` (a `Validator`): verify the extracted ABN is 11 digits and passes the official ATO modulus-89 weighted checksum (subtract 1 from the first digit; weights 10,1,3,5,7,9,11,13,15,17,19; sum; valid iff sum mod 89 == 0). Emit a `ValidationError` on the `abn` field when the format or checksum fails.

## Context

Ideate Connector: the ABN checksum is a fixed, well-specified deterministic algorithm with published test vectors — a cheap, high-credibility, unit-tested win. AU-only per clarification.

## Acceptance Criteria

- [x] A known-valid ABN (e.g. 51 824 753 556) passes with no error.
- [x] A checksum-failing 11-digit number yields one `error`-severity `ValidationError` on field `abn`.
- [x] A non-11-digit value yields a format error.
- [x] Whitespace/spacing in the ABN string is tolerated (normalized before checking).

## Verification Steps

1. **test** `./vendor/bin/pest --filter=AbnValidator` — Expected: green; valid vector passes, mutated digit fails, 10-digit value flagged as format error.

## Integration

**Reachability:** Registered into `ValidationService` (REQ-009) and run by the orchestrator (REQ-016).

**Data dependencies:** Reads `ExtractedInvoice.abn` (REQ-008); emits `ValidationError` (REQ-009).

**Service dependencies:** Implements the `Validator` contract from REQ-009.

## Assets

- (none)

## Outputs

- app/Services/Validation/AbnValidator.php — ATO modulus-89 ABN checksum validator (Validator contract)
- tests/Unit/Validation/AbnValidatorTest.php — 11 Pest tests (valid vector, checksum/format failures, whitespace)
