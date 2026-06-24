# REQ-018: Deploy-readiness config + checklist

**UR:** UR-001
**Status:** backlog
**Created:** 2026-06-24
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 1
**Size:** S
**Files:** .env.example, config/services.php, README.md
**Depends on:** REQ-016 REQ-017

## Task

Make the app deploy-ready (no provisioning). Ensure `.env.example` documents `ANTHROPIC_API_KEY` and `APP_URL`; confirm production asset URLs derive from `APP_URL` (QR + Vite assets resolve on the public domain); and write a short README deploy checklist (env vars, `composer install --no-dev`, `npm run build`, `php artisan config:cache`, point web root at `public/`). Tom provisions/deploys himself (e.g. Forge).

## Context

Clarification: "Just make it deploy-ready — works behind a domain/HTTPS, QR uses APP_URL, build step, env vars, deploy checklist; no actual provisioning."

## Acceptance Criteria

- [ ] `.env.example` lists `ANTHROPIC_API_KEY` and a production-style `APP_URL` with explanatory comments.
- [ ] README contains a deploy checklist covering env, build (`npm run build`), and config caching.
- [ ] A production build (`APP_URL` set to an https domain) emits asset URLs under that domain (no `localhost` references in built output).

## Verification Steps

1. **build** `APP_URL=https://example.test npm run build` then grep built manifest/output — Expected: no `localhost` asset URLs; references resolve under `APP_URL`.
2. **test** `./vendor/bin/pest` — Expected: full suite green after config changes.

## Assets

- (none)
