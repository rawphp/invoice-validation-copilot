# REQ-006: ClaudeClient contract + Anthropic implementation

**UR:** UR-001
**Status:** backlog
**Created:** 2026-06-24
**Layer:** backend
**Entry point:**
**Terminal state:**
**Parent:** REQ-003
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** S
**Files:** app/Services/Claude/ClaudeClient.php, app/Services/Claude/AnthropicClaudeClient.php, app/Providers/ClaudeServiceProvider.php, config/services.php, tests/Unit/Claude/ClaudeClientTest.php
**Depends on:** REQ-001

## Task

Define a single `ClaudeClient` interface (the shared seam used by both ExtractionService and ExplanationService) and an Anthropic-backed implementation that calls `claude-opus-4-8` with messages, supports image/PDF document content blocks and tool-use (forced structured output), and returns the response plus token usage. Bind the interface in a service provider; read `ANTHROPIC_API_KEY` from config. The interface is the exact boundary mocked in feature tests.

## Context

Ideate Connector: "Both LLM calls share one client seam — and that seam is the test mock." Designing one contract up front lets extraction, explanation, and the test fake all sit on it.

## Acceptance Criteria

- [ ] A `ClaudeClient` interface declares a method to send a message (with optional document/image blocks and optional tool schema) and return content + token usage.
- [ ] An Anthropic implementation targets model id `claude-opus-4-8` and reads the key from `config('services.anthropic.key')`.
- [ ] The interface is bound in the container so it can be swapped for a fake in tests.
- [ ] A unit test binds a fake `ClaudeClient` and asserts the binding resolves to the fake.

## Verification Steps

1. **test** `./vendor/bin/pest --filter=ClaudeClient` — Expected: green; binding resolves, fake is injectable.

## Integration

**Reachability:** Resolved from the container by `ExtractionService` (REQ-008) and `ExplanationService` (REQ-014) via constructor injection.

**Data dependencies:** Reads `config/services.php` → `services.anthropic.key` (from `ANTHROPIC_API_KEY` env). Writes nothing.

**Service dependencies:** New service; depends only on the Laravel HTTP client / Anthropic PHP SDK and the app container from REQ-001.

## Assets

- (none)
