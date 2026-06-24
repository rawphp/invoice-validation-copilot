# REQ-019: Accept GST-inclusive line items in subtotal check

**UR:** UR-002
**Status:** backlog
**Created:** 2026-06-24
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** S
**Files:** app/Services/Validation/ArithmeticValidator.php, tests/Unit/Validation/ArithmeticValidatorTest.php
**Depends on:**

## Task

Fix `ArithmeticValidator` check (a) so it stops flagging valid GST-inclusive invoices. Currently the check errors whenever line items do not sum to the **subtotal** (`ArithmeticValidator.php:63`), which assumes line items are GST-exclusive. Change it to pass when line items reconcile against **either** the subtotal (GST-exclusive) **or** the total (GST-inclusive), and only emit the `subtotal` error when neither holds. Reuse the existing `TOLERANCE_AUD` for the new line-items-vs-total comparison.

## Context

From the brief: an invoice (Vista Lawn Care) was wrongly flagged with a critical SUBTOTAL error — "Line items sum to 80.00 but subtotal is 72.73 (difference: 7.27)." The line items are GST-inclusive: they sum to the **total** ($80.00), GST of $7.27 is exactly 80 ÷ 11 (the AU GST-inclusive formula), and the ex-GST subtotal of $72.73 reconciles (72.73 + 7.27 = 80.00). The invoice is internally consistent; the validator's check (a) is wrong.

The bug is self-contradictory in the current code: checks (b) `GST = 10% of subtotal` and (c) `subtotal + GST = total` both pass for this invoice, while check (a) fails — the three checks make inconsistent assumptions about whether line items include GST.

Clarification (UR-002): distinguish inclusive vs exclusive **arithmetically** — no new extraction field, no prompt/tool-schema change. This is safe precisely because checks (b) and (c) corroborate the inclusive interpretation. Mixed-rate invoices (some GST-free + some standard-rated line items) are explicitly **out of scope** for this REQ.

Connector note (ideate): `ExplanationService` consumes the `ValidationError[]`, so removing the false subtotal error upstream automatically corrects the user-facing explanation that wrongly told the supplier to fix the invoice — no separate explanation change is required, only verification.

## Acceptance Criteria

- [ ] The brief's GST-inclusive invoice (line items summing to 80.00; subtotal 72.73; GST 7.27; total 80.00) produces **zero** `subtotal`-field validation errors.
- [ ] A GST-exclusive invoice (line items sum equals the subtotal, e.g. lineSum 80.00 / subtotal 80.00 / GST 8.00 / total 88.00) still passes check (a) with no `subtotal` error — no regression to the existing exclusive case.
- [ ] An invoice whose line items reconcile against **neither** the subtotal nor the total (e.g. lineSum 90.00 / subtotal 72.73 / total 80.00) still emits exactly one `subtotal` error.
- [ ] The line-items-vs-total comparison uses the existing `TOLERANCE_AUD` (0.02) so cent-rounding from the inclusive arithmetic (80 ÷ 11 = 7.2727) does not false-flag at the boundary.

## Verification Steps

> Execute these after implementation to confirm the fix works. Each must pass before committing.

1. **test** Reproduce the reported bug: add/run a Pest case asserting the brief's GST-inclusive invoice yields no `subtotal` error — `./vendor/bin/pest --filter=ArithmeticValidator`
   - Expected: the inclusive-invoice assertion passes (zero `subtotal` errors); this is the case that was previously failing.
2. **test** Run the full validator suite to confirm no regression on the GST-exclusive path and that the "reconciles against neither" case still errors — `./vendor/bin/pest --filter=ArithmeticValidator`
   - Expected: all `ArithmeticValidatorTest` cases pass, including the pre-existing exclusive-case assertions and the new inclusive + neither cases.
