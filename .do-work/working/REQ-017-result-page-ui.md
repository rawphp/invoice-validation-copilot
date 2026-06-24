# REQ-017: Result page UI

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.dowork-ur001
**Claimed at:** 2026-06-24T10:01:36Z
**Heartbeat:** 2026-06-24T10:01:36Z
<!-- claimed-end -->

**UR:** UR-001
**Status:** in-progress
**Created:** 2026-06-24
**Layer:** frontend
**Entry point:**
**Terminal state:**
**Parent:** REQ-003
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** L
**Files:** resources/js/Pages/Result.vue, resources/js/Components/ConfidenceChip.vue, resources/js/Components/ValidationPanel.vue, resources/js/Components/JsonViewer.vue, resources/js/Components/AuditTimeline.vue
**Depends on:** REQ-002, REQ-016

## Task

Build the result page (`Result.vue`, `<script setup lang="ts">`) that renders the full `InvoiceResult` payload on one page using Precision Ledger components: overall confidence + pass/fail badge; extracted fields with per-field `ConfidenceChip`s; a line-items table (description, qty, rate, amount); a `ValidationPanel` grouping errors/warnings + passing checks; the operator-facing supplier explanation card; a collapsible, copyable dark `JsonViewer` for the structured JSON; and an `AuditTimeline`. Also render the friendly error state when the pipeline returns an error result.

## Context

Brief output set: structured JSON, confidence score, validation errors, supplier-friendly explanation, audit log. Single-page layout (not the workbench split). Borrows component ideas from example screens 1 & 2 (confidence chips, passing-checks list, critical-error card, explanation card, status pills, dark JSON block).

## Acceptance Criteria

- [ ] The page renders all sections: overall confidence + pass/fail badge, per-field confidence chips, line-items table, validation panel (errors + passing checks), explanation card, JSON viewer, audit timeline.
- [ ] The structured-JSON block uses the dark `data-mono` Precision Ledger styling and has a copy action.
- [ ] Validation errors display with severity styling (error vs warning) per the design system.
- [ ] A friendly error result (Claude failure / non-invoice) renders an error message instead of the full sections.
- [ ] All components type-check under `lang="ts"` and `npm run build` compiles cleanly.

## Verification Steps

1. **build** `npm run build` — Expected: zero errors.
2. **ui** Submit a fixture invoice (or visit the result page with seeded props), take a snapshot — Expected: confidence badge, fields with chips, line-items table, validation panel, explanation card, JSON viewer, and audit timeline all visible.

## Integration

**Reachability:** Inertia-rendered by `InvoiceController@validate` (REQ-016) after POST `/validate`; wrapped in `AppLayout.vue` (REQ-002).

**Data dependencies:** Consumes the `InvoiceResult` payload (REQ-016): extracted invoice (REQ-008), validation errors (REQ-009–012), confidence (REQ-013), explanation (REQ-014), audit entries (REQ-015).

**Service dependencies:** Consumes design tokens (REQ-002); no backend services called directly.

## Assets

- .do-work/user-requests/UR-001/assets/validation_review_error_handling/screen.png — validation/result visual reference
