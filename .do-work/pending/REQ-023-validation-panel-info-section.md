# REQ-023: Render info-severity findings in the validation panel

**UR:** UR-004
**Status:** pending-validation
**Created:** 2026-06-24
**Layer:** frontend
**Entry point:**
**Terminal state:**
**Parent:** REQ-024
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** S
**Files:** resources/js/Components/ValidationPanel.vue
**Depends on:**

## Task

Add rendering for `info`-severity findings to `ValidationPanel.vue` and stop counting them as "issues". Currently the panel filters findings into `blockingErrors` (severity `error`) and `warnings` (severity `warning`) only (`ValidationPanel.vue:24-30`); an `info` finding (emitted by REQ-021) is invisible. Separately, the header badge renders `errors.length` (total findings) as the "issue" count (`ValidationPanel.vue:62`), so a green-passing invoice carrying a single info note would mislabel as "1 issue".

Changes:
1. Add a computed `infoNotes` filtering findings with `severity === 'info'`, and render them in their own section (mirroring the warnings block structure) using the existing `info` tone — `cardClasses('info')` / `labelClasses('info')` resolve via the existing `toneForSeverity`/`STATUS_TONE_CLASSES.info` tokens. Place the info section after warnings and before the passing-checks list. Use a distinct heading (e.g. "Notes").
2. Change the header badge so the **count** reflects blocking + warning findings only (exclude info), and the success/warning/error tone selection treats an invoice with only info notes as success tone. An invoice whose only finding is an info note must read as **0 issues** with success tone, with the info note shown in its Notes section.

No backend or payload change — `ResultValidationError.severity` is already typed `Severity` which includes `'info'` (`resources/js/types/result.ts`, `resources/js/types/design.ts`). Type-check and build must stay green.

## Context

From UR-004 clarifications: the info-note decision adds a non-blocking note that a payment/credit was applied. The `info` severity, its tone tokens (`STATUS_TONE_CLASSES.info`), and the `toneForSeverity('info')` mapping already exist in `resources/js/types/design.ts` — the panel simply does not render that severity yet. This is the "built but never wired in" gap the integration check guards: REQ-021 emits the finding, but without this REQ it never reaches the screen.

Challenger (ideate): the issue-count badge must not count info notes, or a correctly-passing payment-allocated invoice would still read as having an issue — contradicting the green/success tone.

## Acceptance Criteria

- [x] When `errors` contains an `info`-severity finding, the panel renders a distinct "Notes" section showing that finding's field and message, styled with the `info` tone.
- [x] When `errors` contains only an `info`-severity finding (no `error`/`warning`), the header badge reads "0 issues" and uses the success tone (not warning/error).
- [x] An invoice with a blocking `error` finding still renders the Critical errors section and the badge counts that error — info notes, if any, appear only in the Notes section and are excluded from the count.
- [x] `npm run build` (vue-tsc type-check + vite build) completes with no type errors.

## Verification Steps

> Execute these after implementation to confirm the feature actually works. Each must pass before committing.

1. **build** Run `npm run build`.
   - Expected: `vue-tsc --noEmit` reports no type errors and the vite build completes successfully (no missing-token or type errors from referencing the `info` tone).
2. **ui** With the app served by Herd, upload the payment-allocated invoice fixture (UR-004 `assets/failing-invoice.png` scenario), reach the result page, and take a Playwright snapshot of the validation panel.
   - Expected: no "Critical errors" card for the Total; a "Notes" section is visible containing the payment/credit info message; the header badge reads "0 issues" with success styling. This is the input → render handoff that proves the info finding reaches the screen.

## Post-merge validation

- [ ] Visually confirm against the original UR-004 screenshot scenario — Observable outcome: the validation panel shows no red TOTAL critical error and instead shows the payment/credit note, matching the corrected expectation for the reported Vista Lawn Care invoice.
- [ ] Run the deferred `ui` Playwright check on the Herd-served app: upload a payment-allocated invoice, reach the result page, snapshot the validation panel (environment: deferred by the worker — Herd serves the main project dir, not the worktree). Observable outcome: no Critical-errors card for the Total; a "Notes" section is visible with the payment/credit message; the header badge reads "0 issues" with success styling.

## Outputs

- resources/js/Components/ValidationPanel.vue — added an `infoNotes` computed (`severity === 'info'`) rendered in a new "Notes" section using the existing `info` tone (mirrors the warnings block); introduced an `issueCount` (`blockingErrors.length + warnings.length`) so info notes are excluded from the header "issues" count and an info-only invoice reads "0 issues" with success tone. `npm run build` (vue-tsc + vite) passed with 0 type errors on merged main.

## Integration

**Reachability:** `ValidationPanel` is rendered by `resources/js/Pages/Result.vue`, which receives the Inertia `InvoiceResultPayload` (`errors: ResultValidationError[]`). The user reaches it by uploading an invoice on `Upload.vue` → POST `/validate` → `Result.vue`.

**Data dependencies:** Reads `props.errors` (`ResultValidationError[]`, `resources/js/types/result.ts`) — specifically each finding's `severity` and `message`. No writes.

**Service dependencies:** Reuses `STATUS_TONE_CLASSES`, `toneForSeverity`, and the `Severity` type from `resources/js/types/design.ts` (the `info` tone is already defined there). No new modules.
