# REQ-021: Corroborate Total via line items for payment-allocated invoices

**UR:** UR-004
**Status:** backlog
**Created:** 2026-06-24
**Layer:** backend
**Entry point:**
**Terminal state:**
**Parent:** REQ-024
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** app/Services/Validation/ArithmeticValidator.php, tests/Unit/Validation/ArithmeticValidatorTest.php
**Depends on:**

## Task

Fix `ArithmeticValidator` check (c) so it stops flagging payment-allocated invoices as critical TOTAL errors, and surface an informational note instead. Currently check (c) emits an `error`-severity finding whenever `subtotal + GST != total` (`ArithmeticValidator.php:99`), which assumes the Total is the charge total. On an invoice with payment/credit/adjustment line items, the Total is the **net balance due**, and the line items (not `subtotal + GST`) corroborate it.

Change check (c) so that:
1. It **passes** (no `total` error) when the line items reconcile to the Total within the existing `TOLERANCE_AUD` — reuse the already-computed `$reconcilesToTotal` flag from check (a). This is independent corroboration: the Total is internally consistent with everything on the invoice even though `subtotal + GST` describes only the charge subset.
2. It still **emits the `total` error** when the Total reconciles to **neither** `subtotal + GST` **nor** the line-item sum (a genuine Total typo, where the line items would not sum to the Total either).
3. When the error is suppressed *because* line items corroborate the Total **and** `subtotal + GST` differs from the Total (i.e. a payment/credit was applied), emit exactly one `info`-severity `ValidationError` on the `total` field explaining that the Total reflects payments/credits already applied (e.g. "Total of 18.00 reflects payments/credits already applied; charges before payment were 393.75 (subtotal + GST).").

Do not add a new extraction field, prompt change, or tool-schema change — this is an arithmetic-only fix consistent with the REQ-019 decision. The `info` severity already exists in the `ValidationError` model and the frontend `Severity` type.

## Context

From the brief (UR-004): a Vista Lawn Care invoice was wrongly flagged with a critical TOTAL error — "Total is 18.00 but subtotal + GST = 393.75." The invoice has payment-allocated line items (Payment received −$393.75, Late Payment Credit −$6.00, four late fees +$24.00). The line items sum exactly to the Total ($18.00 net balance due), GST ($35.79) is 10% of subtotal ($357.96), and check (a) already passes because line items reconcile to the Total. Only check (c) fails, because it hard-assumes `subtotal + GST = total`.

Connector (ideate): this is the same root cause as the already-fixed REQ-019 (UR-002) — check (a) was taught that line items can reconcile to the Total, but check (c) still assumes a pure-charge invoice. The two checks contradict each other on what "total" means. The recorded decision (`.do-work/decisions.md`, 2026-06-24) is to distinguish cases **arithmetically — no new extraction field**. Reuse `$reconcilesToTotal` and `TOLERANCE_AUD`.

Challenger (ideate): the suppression must be gated on the line items reconciling to the Total. A blanket skip would let a genuine Total typo slip through. Where there are no line items, check (c) must still run on `subtotal + GST` only (no corroboration available).

## Acceptance Criteria

- [ ] The brief's payment-allocated invoice (line items summing to 18.00; subtotal 357.96; GST 35.79; total 18.00) produces **zero** `error`-severity `total` validation errors.
- [ ] That same invoice produces exactly **one** `info`-severity finding on the `total` field whose message references the payments/credits already applied.
- [ ] A genuine Total typo — line items reconcile to neither `subtotal + GST` nor the Total (e.g. lineSum 50.00 / subtotal 357.96 / GST 35.79 / total 18.00) — still emits exactly one `error`-severity `total` error and **no** `info` finding.
- [ ] A standard charge-only invoice where `subtotal + GST == total` (e.g. subtotal 80.00 / GST 8.00 / total 88.00, line items summing to 88.00) still passes check (c) with **no** `total` error and **no** `info` finding (no regression; no spurious info note when there is no payment).
- [ ] An invoice with **no** line items where `subtotal + GST != total` still emits the `error`-severity `total` error (no corroboration available, so the check falls back to the subtotal+GST assertion).

## Verification Steps

> Execute these after implementation to confirm the fix works. Each must pass before committing.

1. **test** Reproduce the reported bug: add and run a Pest case asserting the brief's payment-allocated invoice yields zero `error`-severity `total` errors and exactly one `info`-severity `total` finding — `./vendor/bin/pest --filter=ArithmeticValidator`
   - Expected: the new payment-allocated assertion passes; the previously-failing critical TOTAL error is gone and a single info finding is present.
2. **test** Run the full validator suite to confirm no regression on the charge-only path, the genuine-typo path still errors, and the no-line-items fallback still errors — `./vendor/bin/pest --filter=ArithmeticValidator`
   - Expected: all `ArithmeticValidatorTest` cases pass, including the pre-existing checks (a)/(b)/(c) cases and the new payment-allocated, typo, charge-only, and no-line-items cases.

## Integration

**Reachability:** `ArithmeticValidator::validate()` is invoked by `App\Services\Validation\ValidationService::run()` (registered in the validator list), which the `App\Services\Invoice\InvoicePipeline` calls after extraction (`InvoicePipeline.php:75`). The `info` finding flows out in the same `ValidationError[]` consumed downstream.

**Data dependencies:** Reads `ExtractedInvoice` (`$subtotal`, `$gst`, `$total`, `$lineItems` of `App\DTO\LineItem`). Emits `App\DTO\ValidationError` with the existing `info` severity. No new fields.

**Service dependencies:** Extends the existing `App\Services\Validation\ArithmeticValidator` only; reuses the `TOLERANCE_AUD` constant and the `$reconcilesToTotal` computation already present in check (a).
