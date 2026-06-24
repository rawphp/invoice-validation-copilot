# REQ-015: AuditLog accumulator

**UR:** UR-001
**Status:** done
**Created:** 2026-06-24
**Layer:** backend
**Entry point:**
**Terminal state:**
**Parent:** REQ-003
**Closure proof:** checkpoint_log:passed commit:4ac8216
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** S
**Files:** app/Services/Invoice/AuditLog.php, app/DTO/AuditEntry.php, tests/Unit/Invoice/AuditLogTest.php
**Depends on:** REQ-001

## Task

Implement an in-memory `AuditLog` that accumulates timestamped `AuditEntry` records as the pipeline runs: each entry captures step name, status (ok/failed), duration, and for LLM steps the model id + token usage, plus an error count. Exposes the ordered entries for inclusion in the result payload (rendered as a timeline). No persistence.

## Context

Brief output "audit log". Scope clarification: in-memory per-request only — the audit log is part of the response payload, not a stored cross-record history (screens 3 & 4 are out of scope).

## Acceptance Criteria

- [x] `AuditLog` records entries in order with step name, status, duration, and optional model/token fields.
- [x] LLM-step entries carry model id and token usage; deterministic-step entries carry an error count.
- [x] Entries are retrievable as an ordered list suitable for JSON serialization.
- [x] A failed step is recorded with `failed` status rather than throwing.

## Verification Steps

1. **test** `./vendor/bin/pest --filter=AuditLog` — Expected: green; appended entries return in order with correct fields; an LLM entry includes token usage.

## Integration

**Reachability:** Instantiated per request by the pipeline orchestrator (REQ-016); each pipeline step appends an entry.

**Data dependencies:** Holds in-memory `AuditEntry` records; serialized into the Inertia result payload (REQ-016 → REQ-017).

**Service dependencies:** Standalone; consumed by the orchestrator (REQ-016).

## Assets

- (none)

## Outputs

- app/Services/Invoice/AuditLog.php — in-memory accumulator; recordDeterministic()/recordLlm(); entries() ordered JSON-friendly array
- app/DTO/AuditEntry.php — readonly value object (step/status/duration + optional model_id/tokens + error_count); JsonSerializable
- tests/Unit/Invoice/AuditLogTest.php — 12 Pest tests: ordering, LLM/deterministic fields, JSON serialization, failed-step no-throw
