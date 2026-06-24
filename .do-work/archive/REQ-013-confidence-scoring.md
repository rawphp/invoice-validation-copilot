# REQ-013: Confidence scoring

**UR:** UR-001
**Status:** done
**Created:** 2026-06-24
**Layer:** backend
**Entry point:**
**Terminal state:**
**Parent:** REQ-003
**Closure proof:** checkpoint_log:passed commit:66cc4b9
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** S
**Files:** app/Services/Invoice/ConfidenceScorer.php, tests/Unit/Invoice/ConfidenceScorerTest.php
**Depends on:** REQ-008, REQ-009

## Task

Implement `ConfidenceScorer`: compute an **overall** confidence from the per-field confidences of an `ExtractedInvoice` (average), then **penalize** for each hard validation failure (`error`-severity `ValidationError`) so the headline number reflects both extraction certainty and validation outcome. Return the overall score plus a derived pass/fail verdict.

## Context

Brief output "confidence score". Ideate: model self-reported confidence is uncalibrated, so label it model-estimated and make the overall number defensible by coupling it to deterministic validation. Must run AFTER validation (ordering coupling flagged in ideate).

## Acceptance Criteria

- [x] Overall confidence with no validation errors equals the average of per-field confidences (within rounding).
- [x] Each `error`-severity `ValidationError` lowers the overall score (assert a penalized result is strictly less than the unpenalized average).
- [x] The score is clamped to the 0–1 range.
- [x] A pass/fail verdict is derived (e.g. fail when any hard error exists or score below a threshold).

## Verification Steps

1. **test** `./vendor/bin/pest --filter=ConfidenceScorer` — Expected: green; clean invoice → average; invoice with 2 errors → lower, clamped score and fail verdict.

## Integration

**Reachability:** Called by the pipeline orchestrator (REQ-016) after `ValidationService`.

**Data dependencies:** Reads per-field `FieldConfidence` (REQ-008) and `ValidationError[]` (REQ-009); output rendered on the result page header (REQ-017).

**Service dependencies:** Depends on the DTOs from REQ-008 and REQ-009.

## Assets

- (none)

## Outputs

- app/Services/Invoice/ConfidenceScorer.php — avg per-field confidence penalised 0.1/hard-error, clamped 0..1, pass/fail verdict (threshold 0.6)
- app/DTO/ConfidenceResult.php — readonly {overall float, passed bool}
- tests/Unit/Invoice/ConfidenceScorerTest.php — 13 Pest tests (average, penalty, clamp, verdict, threshold)
