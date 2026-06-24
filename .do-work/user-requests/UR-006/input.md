---
ur: UR-006
received: 2026-06-25
status: intake
---

# UR-006: User Request

## Request

this analysis seems to be wrong: [screenshot]

(Screenshot attached at `assets/validation-screenshot.png`. Faithful transcription of its contents below — the screenshot IS the brief.)

### Screenshot contents

**Overall confidence:** 86% — Cleaning & Maintenance — status: **FAILED**

**Extracted fields:**

| Field | Value | Confidence |
|---|---|---|
| Supplier | Vista Lawn Care | 99% |
| ABN | 77651564117 | 99% |
| Invoice date | 2026-04-24 | 95% |
| Due date | 2026-05-05 | 98% |
| Subtotal | $257.72 | 98% |
| GST | $25.78 | 98% |
| Total | $283.50 | 85% |

**Validation:** 1 ISSUE

**Critical errors:**
- **SUBTOTAL** — "Line items sum to 139.50 but subtotal is 257.72 (difference: 118.22)."

**Passing checks (6):**
- ABN format — MATCHED
- Invoice date present — MATCHED
- Due date present — MATCHED
- Arithmetic total match — MATCHED
- GST calculation — MATCHED
- Line items present — MATCHED

**Line items:**

| Description | Qty | Rate | Amount |
|---|---|---|---|
| Garden Maintenance - Regular upkeep to keep your gardens neat, healthy, and well-presented. This includes trimming, hedging, light weeding, and general tidy-up work to maintain a clean, attractive outdoor space. | 1 | $270.00 | $270.00 |
| Fuel Surcharge - Temporary Fuel Fee | 1 | $13.50 | $13.50 |
| Late Fee - Late Payment Fee (12/05/2026) | – | – | $6.00 |
| Payment received (15/05/2026) | – | – | -$150.00 |

**Explanation:**

> Here's what you can pass along to Vista Lawn Care regarding their invoice: There's a discrepancy in the **subtotal** that needs to be sorted out before we can approve payment. The individual line items on the invoice add up to AUD 139.50, but the stated subtotal is AUD 257.72 — a difference of AUD 118.22. This usually means a line item is missing, a price was entered incorrectly, or the subtotal figure simply doesn't match the work listed. Could you ask the supplier to review their invoice and either correct the subtotal so it matches the line items, or add any missing line items that account for the difference? Once the line items and subtotal line up, we'll be able to move forward with approval.
