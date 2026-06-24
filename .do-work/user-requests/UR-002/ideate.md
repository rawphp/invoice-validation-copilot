# Ideate — UR-002

**Reviewed:** 2026-06-24

## Explorer — Assumptions & Perspectives

- The validator assumes line-item amounts are **GST-exclusive** (check (a): `lineSum == subtotal`, `ArithmeticValidator.php:63`). The brief's invoice proves the opposite case exists: a GST-inclusive invoice where `lineSum == total` and `gst == total ÷ 11`. Real AU sole traders (a lawn-care supplier quoting "$55 a mow") routinely quote GST-inclusive prices, so the exclusive-only assumption is the root cause, not an edge case.
- The supplier perspective is harmed today: the explanation tells Vista Lawn Care to "correct" a perfectly reconciling invoice. Any fix must also stop the user-facing explanation from chasing valid invoices — the bug has a downstream blast radius beyond the boolean pass/fail.
- The brief is implicitly asking "should this invoice pass?" — the answer is yes. But the deeper undefined question is *how the validator decides inclusive vs exclusive* when the document never states it. The fix needs an explicit rule, not a guess.

## Challenger — Risks & Edge Cases

- **Don't break the exclusive case.** `ArithmeticValidatorTest` exists and surely asserts the current `lineSum == subtotal` pass. Any fix that flips to "lineSum == total" instead would break genuine GST-exclusive invoices. The check must pass when **either** `lineSum ≈ subtotal` (exclusive) **or** `lineSum ≈ total` (inclusive), and only error when neither holds.
- **Masking real errors.** If we accept `lineSum ≈ total` as valid, a genuinely-wrong invoice whose line items happen to equal the total would now pass. Mitigation: only treat it as inclusive when the GST-inclusive identity also holds (`subtotal + gst ≈ total` AND `gst ≈ total ÷ 11`), which checks (b) and (c) already verify — so the inclusive branch is safe precisely because the other two checks corroborate it.
- **Tolerance consistency.** The existing `TOLERANCE_AUD = 0.02` must apply to the new `lineSum vs total` comparison too; the inclusive arithmetic (80 ÷ 11 = 7.2727) rounds, so the same cent-rounding absorption is needed or the fix will false-flag at the boundary.
- **$0 / GST-free line items** (Green Waste Removal $0.00) must not skew either interpretation — they sum to 0 and are harmless, but a future mixed invoice (some GST-free, some standard-rated lines) would defeat a single inclusive/exclusive flag. Scope this fix to the uniform-rate case and note the mixed-rate case as out of scope.
- **GST check (b) is defined against subtotal, not total.** It currently passes for the brief's invoice (10% of 72.73 = 7.27). Confirm the fix doesn't need to touch (b) — it shouldn't, because the inclusive subtotal is already the ex-GST figure.

## Connector — Links & Reuse

- `ExplanationService` (REQ-014) consumes `ValidationError[]` to produce the supplier-facing message. Removing the false subtotal error upstream automatically fixes the wrong explanation — no separate explanation change needed, but the run should verify the explanation no longer mentions a subtotal mismatch for this invoice.
- `ConfidenceScorer` / `InvoiceResult` and the result page (REQ-017) consume the same error list; fewer false errors flows through cleanly. No schema change to `ExtractedInvoice` is required — the inclusive/exclusive decision is derivable from the three figures already present, so this stays a single-validator change.
- Decisions memory (REQ-008): use `ExtractedInvoice` camelCase value props (`subtotal`, `gst`, `total`, `lineItems[].amount`) — consistent with the validator's existing access pattern. No new decision is contradicted.
- No `gstInclusive` flag exists anywhere in `app/`. Deriving inclusivity arithmetically (rather than adding an extraction field) is the lower-risk path and keeps the extraction prompt/tool schema untouched.

## Summary

This is a single-file bug in `ArithmeticValidator`'s check (a): it only accepts GST-exclusive line items, so a valid GST-inclusive invoice (line items sum to the total, GST = total ÷ 11) is wrongly flagged. The fix is to pass the subtotal check when line items reconcile against **either** the subtotal (exclusive) **or** the total (inclusive), reusing the existing tolerance — and it is safe because checks (b) and (c) corroborate the inclusive interpretation. Keep the existing exclusive-case test green, add an inclusive-case test mirroring the brief, and confirm the explanation no longer chases the supplier.
