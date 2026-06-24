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
