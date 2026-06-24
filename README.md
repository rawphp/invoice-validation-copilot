# Invoice Validation Copilot

Upload an Australian supplier invoice (PDF or image) and get back, in one pass:
structured data, a confidence score, deterministic validation errors, a
plain-English "what to tell the supplier" explanation, and a full audit trail —
all on a single result page. No database, no history; each upload runs the
pipeline in memory and renders the outcome.

This is a focused prototype built to showcase a credible end-to-end document
pipeline, not a production system.

## What it does

1. **Upload** a PDF / PNG / JPG (drag-and-drop on desktop, or scan a QR code to
   upload straight from a phone camera).
2. **Extract & classify** — one Claude vision call returns the supplier, ABN,
   invoice and due dates, line items, subtotal, GST and total, plus per-field
   confidence and a service category.
3. **Validate** — four deterministic (non-LLM) checks run over the extracted
   data.
4. **Explain** — a second Claude (text) call writes a warm, supplier-friendly
   summary that reflects the deterministic findings.
5. **Render** — the result page shows the confidence badge, extracted fields
   with per-field confidence chips, the line-items table, grouped
   errors/warnings, the explanation card, copyable structured JSON, and the
   audit timeline.

## Stack

- **Laravel 13** (PHP 8.3)
- **Vue 3** with `<script setup lang="ts">` + **Inertia 2**
- **Tailwind CSS 4** — the *Precision Ledger* design tokens (Deep Slate +
  Indigo, Inter + JetBrains Mono) are wired into the Tailwind theme as the
  single source of truth
- **Vite 8** for the frontend build
- **Pest 4** for tests
- **Anthropic Claude** (`claude-opus-4-8`) for vision extraction and the
  explanation step
- **No database** — sessions/cache/queue use non-DB drivers; there are no
  migrations

## Architecture

The pipeline is a set of isolated, individually testable units wired together
by an orchestrator. Order matters: extract → validate → score → explain, with
every step recorded to the audit log.

```
FileIntake → ExtractionService → ValidationService → ConfidenceScorer → ExplanationService
                  (Claude vision)   (4 deterministic       (avg field conf,    (Claude text)
                                      validators)           penalised per fail)
                  └──────────────────────── AuditLog records every step ────────────────────┘
```

| Component | Responsibility |
|---|---|
| `Services/Invoice/FileIntake` | File type/size guard; downscale/recompress images before the vision call (phone photos exceed Claude's image limits) |
| `Services/Invoice/ExtractionService` | Single Claude vision call with a forced JSON schema (tool use); returns the `ExtractedInvoice` DTO with per-field confidence + `ServiceCategory` |
| `Services/Validation/ValidationService` | Runs the four validators, aggregates `ValidationError{field, severity, message}` |
| ├ `RequiredFieldsValidator` | Flags missing mandatory fields |
| ├ `AbnValidator` | 11 digits + official ATO modulus-89 weighted checksum |
| ├ `ArithmeticValidator` | Line items → subtotal, GST ≈ 10% of subtotal, subtotal + GST = total (within a few-cents tolerance) |
| └ `DateValidator` | Parses AU `DD/MM` dates; not future-dated; due date ≥ invoice date |
| `Services/Invoice/ConfidenceScorer` | Overall confidence = average of field confidences, penalised per hard validation failure |
| `Services/Invoice/ExplanationService` | Second Claude (text) call; operator-facing supplier explanation reflecting the deterministic checks |
| `Services/Invoice/AuditLog` | Timestamped entries per step (status, duration, model + token counts for LLM steps, error count) |
| `Services/Claude/ClaudeClient` | The single seam both LLM calls sit on; `AnthropicClaudeClient` is the live impl, faked in tests so the suite never hits the network |

Typed DTOs (`ExtractedInvoice`, `LineItem`, `ValidationError`, `ConfidenceResult`,
`FieldConfidence`, `AuditEntry`) carry data across layers and mirror the
TypeScript props shared by the Vue components.

### Design decisions worth knowing

- **Australian-only.** ABN checksum, 10% GST, and `DD/MM` dates are hard
  validators. Demo invoices are assumed standard-rated — a fully GST-free
  invoice would be flagged.
- **Confidence is model-estimated.** Per-field numbers come from Claude;
  treat them as estimates, not calibrated probabilities. The overall score
  combines them with the deterministic failures.
- **Mobile upload is standalone.** The QR encodes the public `APP_URL`; the
  phone opens the same upload page over HTTPS and sees its own result. No
  session pairing, no shared state.
- **Graceful failure.** A Claude/API error renders a friendly error result,
  never a stack trace; a non-invoice image yields low confidence and a
  "couldn't extract a valid invoice" flag.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
```

Set your Anthropic key in `.env`:

```
ANTHROPIC_API_KEY=sk-ant-...
```

Run the app (server + queue + logs + Vite, all at once):

```bash
composer dev
```

Then open http://localhost:8000.

## Testing

```bash
composer test          # or: ./vendor/bin/pest
./vendor/bin/pint      # code style
```

The four deterministic validators have unit tests (known valid/invalid ABNs,
arithmetic, dates). The pipeline has a feature test with the `ClaudeClient`
faked, so tests run offline.

## Deployment

The app is deploy-ready for a domain over HTTPS (e.g. Laravel Forge); no
provisioning is included here.

- Set `APP_URL` to the public HTTPS URL — the mobile-upload QR encodes this, so
  it must be correct for the phone to reach the app.
- Set `APP_ENV=production`, `APP_DEBUG=false`, and a real `APP_KEY`.
- Provide `ANTHROPIC_API_KEY`.
- Build assets: `npm run build`.
- No database to migrate (`DB_CONNECTION=null`); `QUEUE_CONNECTION=sync`.
