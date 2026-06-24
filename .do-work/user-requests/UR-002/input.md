---
ur: UR-002
received: 2026-06-24
status: intake
---

# UR-002: User Request

## Request

why is the subtotal wrong here? it is the total minus the GST. why is that wrong?

[Image attached: invoice validation result screenshot — saved to assets/invoice-screenshot.png]

### Image content (transcribed for the record)

Invoice fields:
- Supplier: Vista Lawn Care
- ABN: 77651564117
- Invoice date: 2026-02-09
- Due date: 2026-02-09
- Subtotal: $72.73
- GST: $7.27
- Total: $80.00

Line items:
- Mowing — qty 1 — rate $55.00 — amount $55.00
- Edging — qty 1 — rate $25.00 — amount $25.00
- Green Waste Removal — qty 1 — rate $0.00 — amount $0.00

Critical error flagged:
- SUBTOTAL: "Line items sum to 80.00 but subtotal is 72.73 (difference: 7.27)."

Passing checks (6): ABN format, Invoice date present, Due date present, Arithmetic total match, GST calculation, Line items present.

Explanation shown to user told the supplier to correct the subtotal so it matches the sum of line items.

### Context (from the conversation that produced this request)

The user is correct, and this exposes a validation bug. The line items are GST-inclusive: their sum ($80.00) equals the Total, and GST of $7.27 is exactly 80 ÷ 11 (the AU GST-inclusive formula), giving an ex-GST subtotal of $72.73. The invoice reconciles. The subtotal check incorrectly assumes line items are GST-exclusive (sum should equal subtotal), which directly contradicts the GST-calculation check that passed (subtotal + GST = total). The validator should reconcile line items against the total when they are GST-inclusive, rather than flagging a valid invoice.

Confirmed root cause: `app/Services/Validation/ArithmeticValidator.php:63` — check (a) errors whenever `|lineSum - subtotal| > TOLERANCE_AUD`, accepting only GST-exclusive line items.

## Clarifications

**Q:** You said the subtotal is "the total minus the GST" (line items are GST-inclusive). There's no `gstInclusive` flag anywhere in `app/` — how should the validator distinguish GST-inclusive from GST-exclusive line items?
**A:** Derive it arithmetically. Check (a) should pass when line items reconcile against EITHER the subtotal (`|lineSum - subtotal| <= TOLERANCE_AUD`, exclusive) OR the total (`|lineSum - total| <= TOLERANCE_AUD`, inclusive), and only error when neither holds. No new extraction field, no prompt/tool-schema change — a single-validator fix. This is safe because checks (b) and (c) already confirm `subtotal + GST = total` and `GST ≈ total ÷ 11` for the inclusive case.

**Q:** Should this fix also handle mixed-rate invoices (some GST-free + some standard-rated line items), where a single inclusive/exclusive rule can't cleanly reconcile?
**A:** Out of scope. Fix only the uniform-rate inclusive/exclusive case from the brief. Mixed-rate handling is deferred — keep this a tight single-validator change.
