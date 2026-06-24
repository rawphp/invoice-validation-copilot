# REQ-011: ArithmeticValidator (GST + totals, with tolerance)

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.dowork-ur001
**Claimed at:** 2026-06-24T09:30:00Z
**Heartbeat:** 2026-06-24T09:30:00Z
<!-- claimed-end -->

**UR:** UR-001
**Status:** in-progress
**Created:** 2026-06-24
**Layer:** backend
**Entry point:**
**Terminal state:**
**Parent:** REQ-003
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** app/Services/Validation/ArithmeticValidator.php, tests/Unit/Validation/ArithmeticValidatorTest.php
**Depends on:** REQ-009

## Task

Implement `ArithmeticValidator` (a `Validator`): assuming standard-rated lines, check that (a) line items sum to the subtotal, (b) GST = 10% of the subtotal, and (c) subtotal + GST = total — each within a small money tolerance (a few cents) to absorb legitimate per-line cent rounding. Emit a `ValidationError` per failed check naming the offending figure.

## Context

Clarification: "Standard-rated, small tolerance — assume all lines are 10% GST; check within a few-cents tolerance." Ideate Challenger: strict equality false-positives on rounding, so a tolerance is required.

## Acceptance Criteria

- [ ] An internally consistent standard-rated invoice (lines→subtotal, GST=10%, subtotal+GST=total) yields no errors.
- [ ] An invoice where GST ≠ 10% of subtotal (beyond tolerance) yields one `error` on the `gst` figure.
- [ ] An invoice where subtotal + GST ≠ total (beyond tolerance) yields one `error` on the `total` figure.
- [ ] A discrepancy within the cent tolerance (e.g. ±$0.02 rounding) yields no error.

## Verification Steps

1. **test** `./vendor/bin/pest --filter=ArithmeticValidator` — Expected: green; consistent invoice clean, off-by-$5 GST flagged, ±1c rounding passes.

## Integration

**Reachability:** Registered into `ValidationService` (REQ-009) and run by the orchestrator (REQ-016).

**Data dependencies:** Reads `ExtractedInvoice` line items, subtotal, GST, total (REQ-008); emits `ValidationError` (REQ-009).

**Service dependencies:** Implements the `Validator` contract from REQ-009.

## Assets

- (none)
