# REQ-005: Mobile QR on upload page

**UR:** UR-001
**Status:** pending-validation
**Created:** 2026-06-24
**Layer:** frontend
**Entry point:**
**Terminal state:**
**Parent:** REQ-003
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** S
**Files:** resources/js/Components/MobileQr.vue, resources/js/Pages/Upload.vue, package.json, package-lock.json
**Depends on:** REQ-002, REQ-004

## Task

Add a QR code to the upload page that encodes the app's public upload URL (`APP_URL`) so a mobile device can scan it and open the same upload page on the phone (standalone — phone uploads via camera and sees its own result). Share `APP_URL` to the frontend via Inertia shared props; render the QR client-side from that value. No LAN-IP detection — the app is served from a public HTTPS domain.

## Context

Brief: "show a QR code that a mobile device can scan to open the same page, to use the device instead of the desktop." Clarification: deployed to a public HTTPS domain, so the QR encodes `APP_URL`; standalone phone flow, no pairing, no LAN handling.

## Acceptance Criteria

- [x] The upload page displays a scannable QR code encoding the public upload URL from `APP_URL`.
- [x] `APP_URL` is exposed to the frontend via Inertia shared props (not hardcoded in the component).
- [x] The QR renders client-side and the component type-checks under `lang="ts"`.
- [x] `npm run build` compiles cleanly.

## Verification Steps

1. **build** `npm run build` — Expected: zero errors.
2. **ui** Navigate to `/` with `APP_URL` set, take a snapshot — Expected: a QR code is visible on the upload page.

## Post-merge validation

- [ ] On the deployed HTTPS domain, scan the QR with a phone — Observable outcome: the phone opens the upload page and the camera file input works (secure context).
- [ ] (deferred ui) Navigate to / on the served app with APP_URL set and confirm the QR code is visible on the upload page (environment: needs a browser/served app — could not run in worktree)

## Integration

**Reachability:** Rendered inside `Upload.vue` (REQ-004); `APP_URL` injected via `HandleInertiaRequests` shared props.

**Data dependencies:** Reads `APP_URL` (config/env) shared to the page; writes nothing.

**Service dependencies:** Uses a client-side QR generation library; consumes design tokens from REQ-002.

## Assets

- (none)

## Outputs

- resources/js/Components/MobileQr.vue — client-side QR encoding the public upload URL from the `appUrl` Inertia shared prop; Precision Ledger styled
- resources/js/Pages/Upload.vue — mounts the MobileQr panel below the format hint
- package.json / package-lock.json — added `qrcode` runtime dep + `@types/qrcode`
