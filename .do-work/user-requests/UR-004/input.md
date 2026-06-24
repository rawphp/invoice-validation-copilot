---
ur: UR-004
received: 2026-06-24
status: intake
---

# UR-004: User Request

## Request

this invoice with payments allocated to it fails:

[Image: assets/failing-invoice.png]

Faithful transcription of the attached screenshot:

**Extracted fields**
- Supplier: Vista Lawn Care (99%)
- ABN: 77651564117 (98%)
- Invoice date: 2026-04-18 (97%)
- Due date: 2026-04-18 (97%)
- Subtotal: $357.96 (98%)
- GST: $35.79 (99%)
- Total: $18.00 (99%)

**Validation** — 1 ISSUE
- CRITICAL ERRORS:
  - TOTAL: "Total is 18.00 but subtotal + GST = 393.75."
- PASSING CHECKS (5): ABN format MATCHED, Invoice date present MATCHED, Due date present MATCHED, GST calculation MATCHED, Line items present MATCHED

**Line items**
| Description | Qty | Rate | Amount |
|---|---|---|---|
| Ride-on Mowing - Professional ride-on mowing. Includes whipper snipping all boundaries | 1 | $375.00 | $375.00 |
| Fuel Surcharge - Temporary Fuel Fee | 1 | $18.75 | $18.75 |
| Late Payment Fee (25/04/2026) | — | — | $6.00 |
| Late Payment Fee (02/05/2026) | — | — | $6.00 |
| Late Payment Fee (09/05/2026) | — | — | $6.00 |
| Late Fee (16/05/2026) | — | — | $6.00 |
| Late Payment Credit (18/05/2026) | — | — | -$6.00 |
| Payment received (15/05/2026) | — | — | -$393.75 |

**Explanation**
> Here's what you'll want to flag with Vista Lawn Care before this invoice can move forward: The main issue is with the **total** field. The invoice shows a total of AUD 18.00, but when we add the listed subtotal and GST together, the figure comes to AUD 393.75 — so the numbers don't reconcile. This kind of mismatch usually points to a typo in the total, or a subtotal/GST line that wasn't updated correctly. Could you ask the supplier to review their figures and confirm the correct total? They'll need to send through a corrected invoice where the total matches the subtotal plus GST. Once that's sorted and the amount lines up, we'll be able to approve it without further delay.

## Clarifications

**Q:** How should the validator treat a payment-allocated invoice where the extracted Total is the net balance (not `subtotal + GST`)? The reported invoice has payment/credit/late-fee line items, so its line items sum to the Total ($18.00) while `subtotal + GST` = $393.75.
**A:** Corroborate via line items. `ArithmeticValidator` check (c) (`subtotal + GST = total`) should pass when the line items reconcile to the Total (independent corroboration) within the existing `TOLERANCE_AUD`. Only emit the `total` error when the Total reconciles to neither `subtotal + GST` nor the line-item sum — so a genuine Total typo (where line items would NOT sum to the Total) still fires.

**Q:** When check (c) is suppressed because line items corroborate the Total, should the tool surface any signal that a payment/credit was allocated, or pass fully green?
**A:** Add an info note. Suppress the critical error AND surface an informational (non-blocking) note explaining that the Total reflects a payment/credit already applied (e.g. "Total reflects $393.75 payment already applied; $18.00 balance due"). This is a deliberate scope expansion beyond pure suppression, touching the validator, the result-page rendering, and the explanation wording.

**Q:** Confirm the implementation inferences drawn from REQ-019 and the codebase?
**A:** Confirmed, with one correction. Confirmed: (1) arithmetic-only fix — no new extraction field, no prompt/tool-schema change (per the REQ-019 / 2026-06-24 decision); (2) reuse `TOLERANCE_AUD` and the already-computed `$reconcilesToTotal` flag rather than adding payment detection; (3) tests live in `tests/Unit/Validation/ArithmeticValidatorTest.php`. **Correction:** the "ExplanationService auto-corrects, verify only" inference is *superseded* by the info-note decision above — `ExplanationService` now needs a change so an invoice whose only findings are `info`-severity notes is NOT framed as a supplier error (it currently sends any non-empty error list down the "what the supplier must fix" prompt path). *(inferred, confirmed)*

**Q:** (Capture context) Does the `info` severity already exist, and what renders it?
**A:** *(inferred from codebase)* The `Severity` type (`resources/js/types/design.ts`) already includes `'info'`, with a matching `STATUS_TONE_CLASSES.info` tone and `toneForSeverity` mapping. However `ValidationPanel.vue` only renders `error` and `warning` findings — an `info` finding is currently invisible and must get its own render section. The issue-count badge counts all findings (`errors.length`), so an info note would read as "1 issue" unless the count logic is adjusted. `ConfidenceScorer` only penalizes `error` severity, so an `info` note does not affect confidence. *(inferred, confirmed)*
