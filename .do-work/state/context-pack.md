# Project Context Pack — invoice-validation-copilot

> Greenfield demo (job/client showcase). Built fresh under UR-001. This pack maps the
> **intended** architecture; early REQs create the files described here. Match these
> conventions; do not invent alternative structure.

## Purpose & scope

A throwaway-but-credible prototype: upload an Australian invoice (PDF/PNG/JPG) → extract
fields with Claude vision → run deterministic validators → score confidence → write an
operator-facing explanation → render one result page with structured JSON + audit timeline.
**No database** — every request runs the pipeline in-memory and renders an Inertia page.

## Stack

- **Backend:** Laravel 13, PHP 8.5. No DB (sessions/cache in file/array driver; DB migrations removed).
- **Frontend:** Vue 3 SFCs with `<script setup lang="ts">` + Inertia.js + Tailwind CSS, built by Vite.
- **LLM:** Anthropic `claude-opus-4-8` via a single injectable `ClaudeClient` seam (vision extraction + text explanation). Key from `ANTHROPIC_API_KEY`.
- **Tests:** Pest (`./vendor/bin/pest`). Claude is always mocked in tests behind the `ClaudeClient` interface — tests never hit the network.

## Directory roles (intended)

- `app/Services/Claude/` — `ClaudeClient` interface + `AnthropicClaudeClient` impl + service provider binding.
- `app/Services/Invoice/` — pipeline pieces: `FileIntake`, `ExtractionService`, `ConfidenceScorer`, `ExplanationService`, `AuditLog`, `InvoicePipeline`.
- `app/Services/Validation/` — `ValidationService` + `Validator` contract + `RequiredFieldsValidator`, `AbnValidator`, `ArithmeticValidator`, `DateValidator`.
- `app/DTO/` — typed value objects: `ExtractedInvoice`, `LineItem`, `FieldConfidence`, `ValidationError`, `AuditEntry`, `InvoiceResult`.
- `app/Enums/` — `ServiceCategory` enum.
- `app/Http/Controllers/` — `InvoiceController` (GET `/`, POST `/validate`).
- `app/Http/Requests/` — `ValidateInvoiceRequest` (type/size guard).
- `app/Http/Middleware/` — `HandleInertiaRequests` (shares `APP_URL` to the frontend).
- `resources/js/Pages/` — `Upload.vue`, `Result.vue`.
- `resources/js/Components/` — `MobileQr.vue`, `ConfidenceChip.vue`, `ValidationPanel.vue`, `JsonViewer.vue`, `AuditTimeline.vue`.
- `resources/js/Layouts/` — `AppLayout.vue` (Precision Ledger shell).
- `resources/js/types/` — shared TS types (`invoice.ts`, `design.ts`).
- `routes/web.php` — `GET /` upload page, `POST /validate` pipeline.
- `tests/Unit/`, `tests/Feature/` — Pest tests mirroring `app/` structure.

## Design system

Precision Ledger — tokens live in `.do-work/user-requests/UR-001/assets/precision_ledger/DESIGN.md`.
Translate ONCE into `tailwind.config.ts` theme tokens (Deep Slate + Indigo palette, Inter + JetBrains
Mono, 0.25rem radius, status pills, dark JSON block). **No per-component hardcoded hex.** Example
screens in `assets/` are vision reference only — build screens 1 & 2 ideas (confidence chips, passing-checks
list, critical-error card, explanation card), NOT screens 3 & 4 (dashboard/cross-record — out of scope).

## Pipeline order (must not change)

`FileIntake → ExtractionService (vision + classify, one call) → ValidationService (required + ABN +
arithmetic + date) → ConfidenceScorer (avg field confidence, penalised per hard error) →
ExplanationService (second Claude call, text)`. `AuditLog` accumulates an entry per step. Confidence
MUST run after validation; explanation MUST run after validation. Two LLM calls total.

## AU-specific rules

- Dates: AU locale **DD/MM/YYYY**; invoice date not future-dated; due ≥ invoice. Use an injectable clock.
- ABN: 11 digits + ATO modulus-89 weighted checksum (weights 10,1,3,5,7,9,11,13,15,17,19; subtract 1 from first digit; valid iff sum mod 89 == 0). Known-good vector: `51 824 753 556`.
- GST/arithmetic: assume standard-rated 10%; check lines→subtotal, GST=10%·subtotal, subtotal+GST=total, all within a few-cents tolerance.

## Conventions

- Vue SFCs: `<script setup lang="ts">`, typed props/DTOs shared from `resources/js/types/`.
- PHP: typed DTOs (readonly value objects), constructor injection, interface-bound services in a provider.
- Tests: Pest; unit tests under `tests/Unit/...` mirroring the `app/` path; feature tests under `tests/Feature/`. Claude faked via the `ClaudeClient` binding.
- Error handling: file type/size → 422; Claude API failure → friendly error result (no stack trace/500); non-invoice image → low confidence + "couldn't extract a valid invoice".

## Run the suite

```
./vendor/bin/pest        # PHP/Pest tests
npm run build            # Vite/TS build (frontend type-check + compile)
```
