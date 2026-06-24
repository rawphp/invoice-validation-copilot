# REQ-024: Payment-allocated invoice validates cleanly end-to-end

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.44067
**Claimed at:** 2026-06-24T22:44:17Z
**Heartbeat:** 2026-06-24T22:44:17Z
<!-- claimed-end -->

**UR:** UR-004
**Status:** in-progress
**Created:** 2026-06-24
**Layer:** none
**Entry point:** AP operator uploads a payment-allocated invoice on the upload page → POST /validate → InvoicePipeline (extraction → validation → confidence → explanation) → result page.
**Terminal state:** The result page shows no critical TOTAL error for a payment-allocated invoice; a non-blocking note states the Total reflects payments/credits already applied; the explanation does not tell the operator to ask the supplier for a corrected invoice; the issue badge reads 0 issues.
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 1
**Size:** M
**Files:** tests/Feature/ValidateInvoiceTest.php
**Depends on:** REQ-021, REQ-022, REQ-023

## Task

Close the UR-004 path with an end-to-end feature test proving a payment-allocated invoice flows through the full pipeline without a false critical error and with a correctly-framed explanation. Add a Pest feature test in `tests/Feature/ValidateInvoiceTest.php` that drives the validation pipeline (using the existing fake Claude client in `tests/Fakes/` to return the brief's extraction: subtotal 357.96, GST 35.79, total 18.00, and the payment-allocated line items) and asserts the corrected behaviour across validation + explanation.

This REQ owns closure of the reachable path; the layer changes live in its children (REQ-021 validator, REQ-022 explanation, REQ-023 panel). It adds no production code of its own beyond the test.

## Context

From the brief (UR-004): the reported failure is the end-to-end symptom — an operator uploaded a real, internally-consistent Vista Lawn Care invoice with a payment applied and the tool blocked it with a critical TOTAL error plus advice to chase a non-existent supplier error. The unit-level fixes (REQ-021/022/023) must compose into a clean pass for the actual reported scenario; this feature test is the regression guard that the whole path closed.

## Acceptance Criteria

- [ ] A feature test feeds the brief's payment-allocated invoice through the pipeline and asserts the resulting `errors` array contains **no** `error`-severity finding on the `total` field.
- [ ] The same test asserts the `errors` array contains exactly one `info`-severity finding referencing the payments/credits applied.
- [ ] The same test asserts the generated `explanation` does **not** contain supplier-fix framing for the total (e.g. asserts absence of "corrected invoice" / "subtotal plus GST" advice) — proving REQ-022 composed correctly.
- [ ] The confidence verdict for this invoice is not failed *on account of* the info note (the info finding does not count as a hard error) — assert the confidence `passed` is not dragged down by the info finding.

## Verification Steps

> Execute these after implementation to confirm the path closed. Each must pass before committing.

1. **test** Run the new end-to-end feature test for the payment-allocated invoice — `./vendor/bin/pest --filter=ValidateInvoice`
   - Expected: the payment-allocated scenario passes all assertions (no total error, one info finding, explanation free of supplier-fix framing, confidence not failed by the info note). This crosses the validate → explain → result-payload handoff.
2. **test** Run the full backend suite to confirm no regression across validators, explanation, and pipeline — `./vendor/bin/pest`
   - Expected: the entire Pest suite passes green.
3. **ui** With the app served by Herd, upload the payment-allocated invoice scenario, reach the result page, and take a Playwright snapshot.
   - Expected: the validation panel shows no red TOTAL critical card, a Notes section with the payment/credit message, "0 issues" badge, and an explanation card that does not ask for a corrected invoice. This is the upload → result render closure for the path.

## Post-merge validation

- [ ] Operator re-uploads the original Vista Lawn Care invoice from the UR-004 screenshot — Observable outcome: the result matches the corrected expectation (no critical TOTAL error, a payment note instead, and an explanation that does not tell them to ask the supplier for a corrected total).
