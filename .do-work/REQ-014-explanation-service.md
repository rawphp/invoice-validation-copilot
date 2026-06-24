# REQ-014: ExplanationService (operator-facing supplier explanation)

**UR:** UR-001
**Status:** backlog
**Created:** 2026-06-24
**Layer:** backend
**Entry point:**
**Terminal state:**
**Parent:** REQ-003
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** app/Services/Invoice/ExplanationService.php, tests/Unit/Invoice/ExplanationServiceTest.php
**Depends on:** REQ-006, REQ-009

## Task

Implement `ExplanationService`: a **second Claude call** (text only, via `ClaudeClient`) that takes the extracted invoice summary and the deterministic `ValidationError[]` and writes a warm, plain-English, operator-facing "what to tell the supplier" explanation of what's wrong and what to fix. Runs AFTER validation so it can reference the deterministic checks (e.g. ABN checksum) the model cannot compute. When there are no errors, produce a brief "all checks passed" message.

## Context

Clarification: the supplier-friendly explanation is an operator-facing card (no send). Ideate: explanation must reflect validation results, hence a separate post-validation call (two LLM calls total).

## Acceptance Criteria

- [ ] Given validation errors, returns a non-empty plain-English explanation that references the failed checks.
- [ ] Given zero errors, returns a concise positive "all checks passed" message.
- [ ] The call is made through `ClaudeClient` (mockable); the test uses a fake returning canned text (no network).
- [ ] The prompt is operator-facing ("what to tell the supplier"), not addressed directly to the supplier.

## Verification Steps

1. **test** `./vendor/bin/pest --filter=ExplanationService` — Expected: green; with a fake client, an errors fixture yields the canned explanation; an empty-errors fixture yields the positive message.

## Integration

**Reachability:** Called by the pipeline orchestrator (REQ-016) after confidence scoring.

**Data dependencies:** Reads `ExtractedInvoice` (REQ-008) summary + `ValidationError[]` (REQ-009); output rendered as the explanation card (REQ-017).

**Service dependencies:** Depends on `ClaudeClient` (REQ-006).

## Assets

- (none)
