# REQ-007: File intake + image preprocessing

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
**Size:** M
**Files:** app/Services/Invoice/FileIntake.php, app/Http/Requests/ValidateInvoiceRequest.php, tests/Unit/Invoice/FileIntakeTest.php
**Depends on:** REQ-001

## Task

Accept an uploaded file (PDF, PNG, JPG), validate its type and size via a FormRequest, and preprocess it for the Claude vision call: downscale/recompress images that exceed Claude's image size/dimension limits, and pass PDFs through as document blocks. Return a normalized payload (mime type, base64/bytes, kind: image|pdf) the ExtractionService can hand to ClaudeClient.

## Context

Ideate Challenger: "Phone-camera photos exceed Claude's image limits" — a 4–12 MB phone photo is the exact input the QR feature captures, so downscaling before the API call is required, independent of the LAN/deploy change.

## Acceptance Criteria

- [ ] A FormRequest rejects unsupported types and oversized uploads with HTTP 422 and field-level errors.
- [ ] An image larger than Claude's dimension/byte limit is downscaled/recompressed below the limit while remaining legible (assert resulting bytes/dimensions are under the cap).
- [ ] A PDF is passed through unchanged as a document-kind payload.
- [ ] Returned payload exposes mime type, encoded content, and kind (`image` or `pdf`).

## Verification Steps

1. **test** `./vendor/bin/pest --filter=FileIntake` — Expected: green; oversized image is downscaled under the cap, PDF passes through, bad type rejected.

## Integration

**Reachability:** Invoked by the pipeline orchestrator (REQ-016) inside the POST `/validate` handler before extraction.

**Data dependencies:** Reads the uploaded file from the request; writes nothing persistent (in-memory only).

**Service dependencies:** Uses an image library (e.g. Intervention Image / GD) for downscaling; consumed by ExtractionService (REQ-008).

## Assets

- (none)
