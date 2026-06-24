# Refactor — invoice-validation-copilot

Tracking doc for an architecture refactor pass. Each step: change → live-test → autoreview → commit.

## Baseline (2026-06-25)

- Branch cut from `main` @ `810e992`.
- `php artisan test --compact` → **131 passed**, 435 assertions, ~0.8s.
- Anthropic key present → full E2E live-testing possible via Herd (`https://invoice-validation-copilot.test`).

## Architecture assessment

The codebase is already well-architected and does **not** need broad rework:

- Clean immutable DTO layer (`app/DTO`).
- `Validator` interface + injected validator set (open/closed) wired in `AppServiceProvider`.
- `ClaudeClient` seam with a live `AnthropicClaudeClient` + fakes so the suite never hits the network.
- Pipeline orchestration with a per-step audit trail.
- Frontend: properly extracted Vue components (`ConfidenceChip`, `ValidationPanel`, `JsonViewer`, `AuditTimeline`, `MobileQr`).

**Deliberately NOT touching** (defensible as-is; changing would be churn):
- `InvoicePipeline` constructing `ExtractionService`/`ExplanationService` with `new` — justified by the per-run token recorder.
- Frontend `passingChecks` hardcoded label map — inventing a backend contract for it is over-engineering for a prototype.

## Findings → work

| # | Finding | Severity | Action |
|---|---------|----------|--------|
| 1 | `InvoicePipeline::run()` — 5 near-identical `timer/try/catch/record/early-return` blocks | Structural (DRY) | Extract a private step-runner; centralize timing + failure recording + short-circuit |
| 2 | Pipeline exceptions swallowed with zero logging; `friendlyMessage(Throwable $e)` ignores `$e` | Observability | `report($e)` inside the step-runner's catch; drop the unused `$e` param |

Both fold into a single cohesive change to the orchestrator.

## Progress log

### Step 1 — InvoicePipeline step-runner + observability ✅
- Collapsed 5 duplicated `timer/try/catch/record/early-return` blocks into a private `step()` helper.
- Added `PipelineStepFailed` as a single internal short-circuit caught once in `run()`.
- Added `report($e)` so swallowed pipeline failures are now diagnosable; dropped the unused `friendlyMessage(Throwable $e)` param.
- TDD: added a feature test asserting the underlying throwable is reported (`Exceptions::assertReported`).
- **Tests:** 132 passed (was 131 + 1 new). Pint clean.
- **Live-test:** real E2E via Herd — uploaded `failing-invoice.png`, full Claude pipeline ran, Result page rendered extraction + validation (1 info finding) + 98% pass + explanation + 5-step audit timeline in order. Behaviour identical to pre-refactor.
- **Autoreview:** two parallel reviewers (correctness + cleanup/conventions) → no findings.

## Follow-ups (out of scope for this PR)
- `AuditTimeline.vue` mislabels step durations: `6824.9` ms renders as `6824.90s` (and `19.64` ms as `19.64s`). Pre-existing display bug, unrelated to this refactor — surfaced during live-test.

## Conclusion
Architecture is sound. One genuine structural smell (pipeline duplication) and one observability gap fixed. No further refactoring is warranted — the DTO layer, validator abstraction, Claude seam, and Vue component split are all at the right altitude. Stopping here rather than manufacturing churn.
