# REQ-001: Scaffold Laravel 13 + Inertia + Vue 3 (TS) + Tailwind + Pest

**UR:** UR-001
**Status:** done
**Created:** 2026-06-24
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:** checkpoint_log:passed commit:306b0f8
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** L
**Files:** composer.json, package.json, vite.config.ts, tsconfig.json, resources/js/app.ts, resources/views/app.blade.php, tests/Pest.php, .env.example
**Depends on:**

## Task

Scaffold a fresh Laravel 13 application wired with Inertia.js, Vue 3 using TypeScript (`<script setup lang="ts">`), Tailwind CSS, Vite, and Pest as the test runner. No database is used — remove/disable default DB migrations and session-DB assumptions so the app boots without a configured database. This is the project skeleton every other REQ builds on.

## Context

From the brief: "Laravel 13 + Vue 3 (TypeScript) + Inertia + Tailwind CSS. No database — each upload runs the pipeline in-memory." This is greenfield (empty repo). Establishes the foundation; later REQs add the design system, pipeline, and UI.

## Acceptance Criteria

- [x] `composer install` and `npm install` succeed; `npm run build` compiles with zero errors.
- [x] A Vite/Inertia Vue 3 app boots and serves a placeholder Inertia page at `/` returning HTTP 200.
- [x] TypeScript is configured (`tsconfig.json`) and a `.vue` SFC using `<script setup lang="ts">` type-checks without error.
- [x] Tailwind is wired (directives compile, a utility class renders) via `vite.config.ts`.
- [x] `./vendor/bin/pest` runs and a trivial example test passes.
- [x] App boots with no database configured (no migration/DB-session errors on `/`).

## Verification Steps

1. **build** `npm run build` — Expected: builds with zero errors, emits hashed assets.
2. **test** `./vendor/bin/pest` — Expected: green, example test passes.
3. **runtime** `php artisan serve` then `curl -I http://localhost:8000/` — Expected: HTTP 200, no DB connection error in logs.

## Outputs

- composer.json — Laravel ^13 app manifest (Inertia + Ziggy + Pest 4; DB/migrate scaffold scripts removed)
- package.json — Vue 3 + @inertiajs/vue3 + Tailwind v4 + Vite 8 + vue-tsc; build type-checks then vite build
- vite.config.ts — Vite config wiring Laravel + Vue + Tailwind plugins (input resources/js/app.ts)
- tsconfig.json — strict TypeScript config for resources/js with @/* alias
- resources/js/app.ts — Inertia createApp entry resolving Pages/**/*.vue
- resources/js/Pages/Welcome.vue — placeholder Inertia page, `<script setup lang="ts">`, Tailwind-styled
- resources/views/app.blade.php — Inertia root view (@vite + @inertia + @inertiaHead)
- app/Http/Middleware/HandleInertiaRequests.php — shares appUrl to the frontend
- routes/web.php — GET / renders Inertia Welcome
- tests/Pest.php, tests/Feature/HomePageTest.php, tests/Unit/ExampleTest.php — Pest scaffolding + GET / feature test + trivial example
- .env.example — no-DB env (null/file/file/sync) + ANTHROPIC_API_KEY + APP_URL documented

## Assets

- (none)
