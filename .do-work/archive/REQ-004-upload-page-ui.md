# REQ-004: Upload page UI

**UR:** UR-001
**Status:** done
**Created:** 2026-06-24
**Layer:** frontend
**Entry point:**
**Terminal state:**
**Parent:** REQ-003
**Closure proof:** checkpoint_log:passed commit:72f43b7
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** resources/js/Pages/Upload.vue, resources/js/types/invoice.ts
**Depends on:** REQ-002, REQ-016

## Task

Build the upload page (`Upload.vue`, `<script setup lang="ts">`): a Precision Ledger styled drag-and-drop zone accepting PDF/PNG/JPG, a file picker fallback, client-side type/size feedback, a primary "Validate" button, and a loading state while POST `/validate` runs. Submits via Inertia to the route from REQ-016.

## Context

Brief step 1 "Uploads an invoice PDF/image." Frontend entry for the validate path. Styled with the Precision Ledger tokens (REQ-002); borrows the upload affordance from example screen 1.

## Acceptance Criteria

- [x] `/` renders a drag-and-drop upload zone plus a file-picker fallback, styled with Precision Ledger tokens.
- [x] Selecting an unsupported type or oversized file shows an inline client-side message before submit.
- [x] Clicking "Validate" submits the file to POST `/validate` and shows a loading state until the response.
- [x] The component type-checks under `lang="ts"` and `npm run build` compiles cleanly.

## Verification Steps

1. **build** `npm run build` — Expected: zero errors.
2. **ui** Navigate to `/`, take a snapshot — Expected: upload dropzone, "Validate" button, Precision Ledger styling visible.

## Integration

**Reachability:** Served at GET `/` by `InvoiceController` (REQ-016); wrapped in `AppLayout.vue` (REQ-002).

**Data dependencies:** Posts a file to POST `/validate` (REQ-016). Receives no data on load beyond shared props (e.g. `APP_URL` for the QR, REQ-005).

**Service dependencies:** Uses Inertia form submission; consumes design tokens from REQ-002.

## Assets

- .do-work/user-requests/UR-001/assets/invoice_extraction_classification/screen.png — upload/extraction visual reference

## Outputs

- resources/js/Pages/Upload.vue — Precision Ledger drag-and-drop upload page; client-side type/size validation; Inertia form.post('/validate', forceFormData) with loading state
- resources/js/types/invoice.ts — shared TS types mirroring the PHP DTOs + upload constants
