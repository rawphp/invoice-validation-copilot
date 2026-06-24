# REQ-022: Frame info-only findings as context, not supplier errors, in the explanation

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.77194
**Claimed at:** 2026-06-24T11:54:35Z
**Heartbeat:** 2026-06-24T11:54:35Z
<!-- claimed-end -->

**UR:** UR-004
**Status:** in-progress
**Created:** 2026-06-24
**Layer:** backend
**Entry point:**
**Terminal state:**
**Parent:** REQ-024
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** app/Services/Invoice/ExplanationService.php, tests/Unit/Invoice/ExplanationServiceTest.php
**Depends on:**

## Task

Update `ExplanationService` so that `info`-severity findings are treated as contextual notes, not as problems the supplier must fix. Currently `explain()` branches solely on `$errors === []` (`ExplanationService.php:62`): any non-empty findings list — including a lone `info` note — falls into the "what the supplier needs to fix before the invoice can be approved" prompt path. For a payment-allocated invoice whose only finding is an `info` note (from REQ-021), this would tell the operator to chase a non-existent supplier error.

Change the prompt selection to partition findings by severity:
1. **Blocking findings** = severity `error` or `warning`. **Info findings** = severity `info`.
2. If there are **no blocking findings**, use the positive/all-clear message path — but when info findings are present, include them in the prompt as **context** so the explanation can mention them neutrally (e.g. note that a payment/credit has been applied and state the balance due). The wording must not tell the operator to ask the supplier to correct anything.
3. If there **are** blocking findings, keep the existing "what the supplier must fix" path for the blocking findings, and pass any info findings as non-blocking context (do not list them under "must fix").

## Context

From UR-004 clarifications: the info-note decision supersedes the earlier "explanation auto-corrects, verify only" inference. Because REQ-021 now emits an `info` finding for payment-allocated invoices, `ExplanationService` must distinguish info from blocking findings, or it regenerates the same wrong "send a corrected invoice where the total matches subtotal plus GST" advice that the brief is reporting.

`ExplanationService` is the second Claude call in the pipeline (text only). Tests use the recording/fake Claude client (`tests/Fakes/`, `App\Services\Invoice\RecordingClaudeClient`) so the prompt content can be asserted without a live API call — assert on the prompt built for each branch rather than on model output.

## Acceptance Criteria

- [ ] When `explain()` receives only `info`-severity findings (no `error`/`warning`), the prompt sent to the Claude client uses the positive/all-clear framing (no "supplier must fix" / "corrected invoice" language) and includes the info finding text as context.
- [ ] When `explain()` receives a mix of `error` and `info` findings, the prompt lists the `error` finding(s) under the "must fix" framing and the `info` finding(s) as non-blocking context, not as fix items.
- [ ] When `explain()` receives an empty findings array, behaviour is unchanged (existing all-clear prompt).
- [ ] When `explain()` receives only `error`/`warning` findings (no info), behaviour is unchanged (existing "must fix" prompt) — no regression.

## Verification Steps

> Execute these after implementation to confirm the fix works. Each must pass before committing.

1. **test** Add and run a Pest case asserting the info-only branch builds the all-clear prompt with the info context and no "corrected invoice"/"must fix" language — `./vendor/bin/pest --filter=ExplanationService`
   - Expected: the info-only assertion passes; the prompt contains the info context and omits supplier-fix framing.
2. **test** Run the full `ExplanationService` suite to confirm the empty-findings, error-only, and mixed error+info branches all behave per the criteria — `./vendor/bin/pest --filter=ExplanationService`
   - Expected: all `ExplanationServiceTest` cases pass, including the pre-existing empty and error-only cases (no regression) and the new info-only and mixed cases.

## Integration

**Reachability:** `ExplanationService::explain()` is called by `App\Services\Invoice\InvoicePipeline` after validation (`InvoicePipeline.php:97`), and its returned string is surfaced as the `explanation` field of the `InvoiceResult` payload rendered on the result page.

**Data dependencies:** Reads the `App\DTO\ValidationError[]` produced by `ValidationService` (specifically each finding's `severity`) and the `ExtractedInvoice` summary. Writes nothing; returns a string.

**Service dependencies:** Extends the existing `App\Services\Invoice\ExplanationService`; uses the injected `App\Services\Claude\ClaudeClient` (faked in tests via `tests/Fakes/`). No new services.
