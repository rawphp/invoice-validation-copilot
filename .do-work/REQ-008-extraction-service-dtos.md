# REQ-008: ExtractionService + invoice DTOs

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
**Size:** L
**Files:** app/Services/Invoice/ExtractionService.php, app/DTO/ExtractedInvoice.php, app/DTO/LineItem.php, app/DTO/FieldConfidence.php, app/Enums/ServiceCategory.php, tests/Unit/Invoice/ExtractionServiceTest.php
**Depends on:** REQ-006, REQ-007

## Task

Build the ExtractionService: send the preprocessed file to Claude via `ClaudeClient` with a **forced JSON schema (tool use)** that returns supplier, ABN, invoice date, due date, line items (description, qty, rate, amount), subtotal, GST, total, **per-field confidence (0–1)**, and **service category** (classified in the same call). Map the tool-call result into typed DTOs (`ExtractedInvoice`, `LineItem`, `FieldConfidence`) and a `ServiceCategory` enum. Pin the extraction prompt to AU locale (DD/MM dates, AUD).

## Context

Brief: extraction + classification in one vision call returning structured output and per-field confidence. Clarification: Australian only → DD/MM dates. Taxonomy enum: Construction & Trades, Professional Services, IT & Software, Cleaning & Maintenance, Logistics & Freight, Utilities, Marketing & Media, Equipment & Supplies, Other.

## Acceptance Criteria

- [ ] ExtractionService calls `ClaudeClient` with a tool schema forcing the documented field set and returns an `ExtractedInvoice` DTO.
- [ ] Each scalar field carries a `FieldConfidence` (0–1); line items are typed `LineItem` objects.
- [ ] Service category is one of the `ServiceCategory` enum cases.
- [ ] The extraction prompt instructs AU-locale date interpretation (DD/MM/YYYY) and AUD amounts.
- [ ] A non-invoice document maps to an empty/low-confidence `ExtractedInvoice` rather than throwing.
- [ ] Test uses a fake `ClaudeClient` returning a canned tool-call payload and asserts correct DTO mapping (no network).

## Verification Steps

1. **test** `./vendor/bin/pest --filter=ExtractionService` — Expected: green; canned tool-call payload maps to fully typed `ExtractedInvoice` with per-field confidence and a valid category.

## Integration

**Reachability:** Called by the pipeline orchestrator (REQ-016) after FileIntake (REQ-007), before validation.

**Data dependencies:** Reads the normalized payload from `FileIntake` (REQ-007); produces `ExtractedInvoice` consumed by validators (REQ-009–012), confidence scoring (REQ-013), explanation (REQ-014), and the result page (REQ-017).

**Service dependencies:** Depends on `ClaudeClient` (REQ-006).

## Assets

- (none)
