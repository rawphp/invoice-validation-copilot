# REQ-026: Exclude adjustment lines from the subtotal line-item check

**UR:** UR-006
**Status:** done
**Created:** 2026-06-25
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:** checkpoint_log:passed commit:4dc84c1
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** app/Services/Validation/ArithmeticValidator.php, tests/Unit/Validation/ArithmeticValidatorTest.php
**Depends on:**

## Task

Fix the false-positive "SUBTOTAL" critical error that `ArithmeticValidator` check (a) raises on a consistent invoice when payment/credit/fee **adjustment rows** are present.

Today check (a) sums **all** line items into `$lineSum` and accepts the invoice only if that full sum reconciles to the subtotal or the total. On a gross-total invoice (Total = gross charges, with "Payment received" and "Late Fee" shown as memo rows), the full sum is dragged away from both figures even though the **charge lines alone** reconcile exactly to the Total.

Implement **Option A (PHP-side classifier, backend-only)** — chosen over Option B (extraction-tagged line `kind`) to respect the standing REQ-021 "no new extraction field" decision and keep the blast radius to the validator + its test:

1. Add a private classifier in `ArithmeticValidator` that marks a `LineItem` as an **adjustment** (payment / credit / refund / post-subtotal fee) when EITHER:
   - its `amount` is negative (`< 0`), OR
   - its `description` matches (case-insensitive) an adjustment keyword: `payment`, `paid`, `credit`, `refund`, `balance`, `deposit`, `late fee`, `late payment`, `overpayment`.
2. Compute a **charge-only sum** (line items that are NOT adjustments) alongside the existing full sum.
3. Make check (a) **additive** — accept the invoice when ANY of these reconcile within `TOLERANCE_AUD`: full sum vs subtotal, full sum vs total, charge-only sum vs subtotal, charge-only sum vs total. This can only make more invoices pass, never fewer, so it cannot regress a case that passes today.
4. When the full sum does **not** reconcile but the charge-only sum **does** (and at least one adjustment line exists), suppress the error and emit exactly **one `info`-severity** `ValidationError` on `subtotal` explaining the gap (e.g. "Charges total {chargeSum}; after payments/credits the outstanding balance is {fullSum}."). This mirrors the existing check (c) info-note pattern for payment-allocated invoices.
5. When neither the full sum nor the charge-only sum reconciles, keep emitting the existing single `error`-severity subtotal finding (genuine discrepancy — no behaviour change).

## Context

From UR-006: the user flagged the validation result for a Vista Lawn Care invoice as wrong. The screenshot shows a `FAILED` status driven by one critical "SUBTOTAL" error: "Line items sum to 139.50 but subtotal is 257.72 (difference: 118.22)."

The invoice is actually internally consistent:
- Charge lines: Garden Maintenance $270.00 + Fuel Surcharge $13.50 = **$283.50** (GST-inclusive) → reconciles to Total $283.50.
- Subtotal $257.72 + GST $25.78 = **$283.50** (Total) — this is why "Arithmetic total match" and "GST calculation" already pass.
- Adjustment rows: "Late Fee" +$6.00 and "Payment received" −$150.00 are memos that produce the $139.50 balance due; they are not part of the charge subtotal/total.

Root cause (Connector — same class as REQ-019/REQ-021): `ArithmeticValidator.php` lines 58–80 sum every `LineItem` into `$lineSum`. REQ-021 taught **check (c)** about payment-allocated (net-Total) invoices, but **check (a)** was never made adjustment-aware, so the opposite (gross-Total) orientation still false-positives.

Reuse: `info` severity is already plumbed end-to-end (REQ-022 — `ExplanationService` all-clear framing, `ValidationPanel` Notes section, `ConfidenceScorer` ignores info findings), so emitting one info note needs **zero frontend/DTO work**, and removing the error-severity finding is sufficient for the overall result to stop reading as `FAILED`.

Challenger edge (must not regress): a sign-only rule is insufficient because the late fee is a **positive** adjustment — the keyword list is required to exclude it. The classifier must stay conservative so genuine charge typos still fail (REQ-019 AC3, REQ-021 AC3).

## Acceptance Criteria

- [x] The UR-006 invoice fixture (lines: Garden Maintenance $270.00, Fuel Surcharge $13.50, Late Fee +$6.00, Payment received −$150.00; subtotal $257.72, GST $25.78, total $283.50) produces **zero `error`-severity `subtotal` findings**.
- [x] That same UR-006 fixture produces **exactly one `info`-severity `subtotal` finding** whose message references the outstanding balance / payments (the charge total reconciles to the Total while the full line sum does not).
- [x] REQ-021's `paymentAllocatedInvoice` (net-Total orientation, full line sum 18.00 == total 18.00) still produces **zero `error`-severity `subtotal` findings** (no regression).
- [x] REQ-019's `gstInclusiveInvoiceWithWrongLineItems` (genuine typo, lineSum 90.00 reconciles to neither subtotal 72.73 nor total 80.00, and no adjustment lines) still emits **exactly one `error`-severity `subtotal` finding**.
- [x] `consistentInvoice` (GST-exclusive charge-only) and `gstInclusiveInvoice` (GST-inclusive charge-only) still produce **zero `subtotal` findings** of any severity (no regression).
- [x] A line with a positive amount whose description contains an adjustment keyword (e.g. "Late Fee" +$6.00) is excluded from the charge-only sum, and a line with a negative amount (e.g. "Payment received" −$150.00) is excluded regardless of description.

## Verification Steps

> Execute these after implementation to confirm the fix actually works. Each must pass before committing.

1. **test** Add a failing test first that reproduces the bug: a fixture matching the UR-006 screenshot invoice, asserting `array_filter($errors, fn ($e) => $e->field === 'subtotal' && $e->severity === 'error')` is empty. Run `./vendor/bin/pest --filter=ArithmeticValidator` and confirm it FAILS against current `ArithmeticValidator` (proves the bug reproduces).
   - Expected: the new test fails before the fix — the current validator emits an error-severity subtotal finding.
2. **test** Implement the classifier + additive reconciliation + info note, then run `./vendor/bin/pest --filter=ArithmeticValidator`.
   - Expected: all ArithmeticValidator tests pass, including the new UR-006 no-error and one-info assertions and every pre-existing REQ-019 / REQ-021 case (genuine-typo cases still error).
3. **test** Run the full suite `./vendor/bin/pest` to confirm no cross-cutting regression (ValidationService, ExplanationService, ConfidenceScorer).
   - Expected: full suite green; the overall result for the UR-006-shaped invoice no longer carries a critical error.

## Assets

- .do-work/user-requests/UR-006/assets/validation-screenshot.png — the reported FAILED validation that motivates this fix

## Outputs

- app/Services/Validation/ArithmeticValidator.php — Added a charge-vs-adjustment line classifier (negative amount OR payment/credit/refund/fee description keyword); made check (a) reconciliation additive (full sum OR charge-only sum vs subtotal/total); emits one info-severity subtotal note when charges reconcile but the full line sum does not.
- tests/Unit/Validation/ArithmeticValidatorTest.php — 6 new test cases covering the UR-006 false-positive (zero error + one info finding), classifier exclusion of positive/negative adjustment lines, and REQ-019/REQ-021 no-regression cases.
