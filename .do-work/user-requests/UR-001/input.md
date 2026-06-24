---
ur: UR-001
received: 2026-06-24
status: intake
---

# UR-001: User Request

## Request

...demo: invoice validation copilot

Build a small prototype that:

1. Uploads an invoice PDF/image.
2. Extracts supplier, ABN, dates, line items, rates, GST, total.
3. Classifies service category.
4. Checks required fields.
5. Produces:
   - structured JSON
   - confidence score
   - validation errors
   - supplier-friendly explanation
   - audit log

---

## Agreed design (brainstormed 2026-06-24)

The following decisions were agreed during a brainstorming pass and form the scope of this UR.

**Purpose:** Job/client showcase. Must run cleanly end-to-end and look credible; throwaway, not production architecture.

**Stack:** Laravel 13 + Vue 3 + Inertia + Tailwind. **No database** — each upload runs the pipeline in-memory and renders a result page. No migrations, no history view.

**Flow:** Upload (drag/drop PDF/PNG/JPG) → `POST /validate` → pipeline → Inertia renders a single result page containing all outputs.

**Backend pipeline (isolated, individually testable units):**

1. `ExtractionService` — sends the file to Claude (`claude-opus-4-8`) vision with a **forced JSON schema via tool use**. Returns a typed `ExtractedInvoice` DTO: supplier, ABN, invoice date, due date, line items (description, qty, rate, amount), subtotal, GST, total — **plus per-field confidence (0–1)** and **service category**. Extraction and classification happen in this single call (one round-trip).
2. `ValidationService` — runs four **deterministic** validators (no LLM) over the DTO, each returning `ValidationError{field, severity, message}`:
   - `RequiredFieldsValidator` — missing mandatory fields
   - `AbnValidator` — 11 digits + official ATO weighted checksum
   - `ArithmeticValidator` — line items → subtotal, GST ≈ 10% of taxable amount, subtotal + GST = total
   - `DateValidator` — parses, not future-dated, due date ≥ invoice date
3. `ExplanationService` — a **second Claude call** (text only) that takes the validation results and writes the warm, supplier-friendly explanation. It runs *after* validation because the explanation must reflect deterministic checks (e.g. ABN checksum) that the model cannot compute reliably. Two LLM calls total.
4. `AuditLog` — accumulated through every step: timestamped entries (step, status, duration, model + token counts for LLM steps, error count). Returned in the payload, rendered as a timeline.

**Confidence:** per-field confidence from Claude; **overall** = average of field confidences, penalized for each hard validation failure. Both displayed.

**Outputs (all on the result page):** overall confidence + pass/fail badge · extracted fields with per-field confidence chips · line-items table · grouped validation errors/warnings · supplier-friendly explanation card · collapsible, copyable **structured JSON** · audit-log timeline.

**Service category taxonomy (initial):** Construction & Trades · Professional Services · IT & Software · Cleaning & Maintenance · Logistics & Freight · Utilities · Marketing & Media · Equipment & Supplies · Other.

**Claude API:** live call wired from `ANTHROPIC_API_KEY` in `.env`. No mock fallback in initial scope (re-raised as a demo-safety risk; deferred unless requested).

**Testing:** Pest unit tests on all four deterministic validators (known valid/invalid ABNs, arithmetic, dates). Feature test on the pipeline with the **Claude client mocked behind an interface** so tests don't hit the network. Frontend kept thin.

**Error handling:** file type/size guard · Claude API failure → friendly error, no crash · non-invoice image → low confidence + "couldn't extract a valid invoice" flag.
