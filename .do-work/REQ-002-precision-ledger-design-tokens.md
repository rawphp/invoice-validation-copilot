# REQ-002: Precision Ledger design tokens + base layout shell

**UR:** UR-001
**Status:** backlog
**Created:** 2026-06-24
**Layer:** frontend
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** M
**Files:** tailwind.config.ts, resources/css/app.css, resources/js/Layouts/AppLayout.vue, resources/js/types/design.ts
**Depends on:** REQ-001

## Task

Translate the Precision Ledger design system (`.do-work/user-requests/UR-001/assets/precision_ledger/DESIGN.md`) into a single source of truth: Tailwind theme tokens (colors, font families Inter + JetBrains Mono, radius scale, spacing) plus any CSS variables. Build a base `AppLayout.vue` shell (header, content container, Precision Ledger surface background) that result/upload pages render inside. No per-component hardcoded hex — components consume theme tokens.

## Context

From the brief's Visual design clarification: "Adopt the Precision Ledger design system ... tokens wired into the Tailwind theme config (single source of truth), not hardcoded per component." Ideate Connector flagged token drift if hex is pasted per component.

## Acceptance Criteria

- [ ] `tailwind.config.ts` defines the Precision Ledger palette (surface, primary Deep Slate, secondary Indigo, error, success/emerald, amber) and font families (Inter, JetBrains Mono) from DESIGN.md.
- [ ] Inter and JetBrains Mono are loaded (self-hosted or CDN) and applied via the theme.
- [ ] `AppLayout.vue` renders a Precision Ledger styled shell (surface background `#f7f9fb`, header) and type-checks under `lang="ts"`.
- [ ] A sample element using a token utility (e.g. `bg-primary`, `font-mono`) renders with the correct color/font in the browser.
- [ ] `npm run build` compiles with zero errors.

## Verification Steps

1. **build** `npm run build` — Expected: zero errors.
2. **ui** Navigate to a page wrapped in `AppLayout.vue`, take a snapshot — Expected: Precision Ledger surface background and header visible; Inter font applied.

## Integration

**Reachability:** Consumed by every page component (upload page REQ-004, result page REQ-017) which wrap their content in `resources/js/Layouts/AppLayout.vue`.

**Data dependencies:** None — static design tokens only. Reads no runtime data.

**Service dependencies:** Extends the Vite/Tailwind build configured in REQ-001 (`vite.config.ts`, `tailwind.config.ts`).

## Assets

- .do-work/user-requests/UR-001/assets/precision_ledger/DESIGN.md — design tokens source of truth
