# REQ-007: File intake + image preprocessing

**UR:** UR-001
**Status:** done
**Created:** 2026-06-24
**Layer:** backend
**Entry point:**
**Terminal state:**
**Parent:** REQ-003
**Closure proof:** checkpoint_log:passed commit:e462c20
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

- [x] A FormRequest rejects unsupported types and oversized uploads with HTTP 422 and field-level errors.
- [x] An image larger than Claude's dimension/byte limit is downscaled/recompressed below the limit while remaining legible (assert resulting bytes/dimensions are under the cap).
- [x] A PDF is passed through unchanged as a document-kind payload.
- [x] Returned payload exposes mime type, encoded content, and kind (`image` or `pdf`).

## Verification Steps

1. **test** `./vendor/bin/pest --filter=FileIntake` — Expected: green; oversized image is downscaled under the cap, PDF passes through, bad type rejected.

## Integration

**Reachability:** Invoked by the pipeline orchestrator (REQ-016) inside the POST `/validate` handler before extraction.

**Data dependencies:** Reads the uploaded file from the request; writes nothing persistent (in-memory only).

**Service dependencies:** Uses an image library (e.g. Intervention Image / GD) for downscaling; consumed by ExtractionService (REQ-008).

## Assets

- (none)

## Outputs

- app/Services/Invoice/FileIntake.php — normalises uploaded invoice to {mime, content, kind}; downscales oversized images via GD
- app/Http/Requests/ValidateInvoiceRequest.php — FormRequest guarding file type (pdf/png/jpeg) + size (≤20MB) with 422 field errors
- tests/Unit/Invoice/FileIntakeTest.php — 10 Pest unit tests incl. dimension/byte cap assertions
