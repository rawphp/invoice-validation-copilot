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
