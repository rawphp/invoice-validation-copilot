# Ideate — UR-002

**Reviewed:** 2026-06-24 (refreshed post-capture — incorporates the two recorded clarifications and the written REQ-019)

## Explorer — Assumptions & Perspectives

- Root cause is settled: `ArithmeticValidator.php:63` check (a) assumes line items are GST-exclusive (`lineSum == subtotal`). The brief's invoice is GST-inclusive (`lineSum == total`, `gst == total ÷ 11`), so the check false-flags a reconciling invoice. The clarification locked the approach to arithmetic derivation — no new extraction field. Remaining assumption worth naming: that **the extraction layer will keep emitting GST-inclusive line-item amounts as-is** (i.e. it transcribes the printed line totals, not a re-derived ex-GST figure). If extraction ever normalises line items to ex-GST, the inclusive branch becomes dead code. Worth a one-line confirmation against `ExtractionService` during the run.
- Two perspectives are served by the fix but only one is in REQ-019's acceptance criteria: the **validator** (no false error) is covered; the **supplier-facing explanation** (which currently tells Vista Lawn Care to "correct" a valid invoice) is only a verification afterthought. The explanation correction is the part the human actually sees — it deserves an explicit assertion, not just "confirm it changed."
- The brief is a single reported case. The silent assumption is that this one invoice is representative of "GST-inclusive invoices" generally. It is — the AU GST-inclusive identity is universal — but the fix's correctness rests on checks (b) and (c) passing, which they do here. An invoice that is inclusive *and* has a (b)/(c) failure is a different bug, out of this UR's scope.

## Challenger — Risks & Edge Cases

- **Regression on the exclusive path is the top risk.** `ArithmeticValidatorTest` almost certainly asserts the current `lineSum == subtotal` pass. REQ-019's criterion #2 guards this, but the implementer must keep the exclusive branch *first/equal*, not replace it. The OR must be inclusive-of-both, never a swap.
- **False-accept widening.** Accepting `lineSum ≈ total` means an invoice whose line items coincidentally equal the total now passes check (a) even if it's genuinely wrong. Mitigation already noted in REQ-019: the inclusive interpretation is only meaningful because checks (b) and (c) independently corroborate `subtotal + gst == total` and `gst ≈ total ÷ 11`. Residual gap: REQ-019 does **not** assert the corroboration explicitly — it relies on (b)/(c) being separate errors. Consider an acceptance criterion that an inclusive invoice with a broken GST (e.g. gst = 5.00, total = 80, lineSum = 80) still surfaces a `gst` error. That proves the widening didn't swallow a real defect.
- **Tolerance boundary.** `80 ÷ 11 = 7.2727…`; the ex-GST subtotal rounds to 72.73. The `lineSum`-vs-`total` comparison must use `TOLERANCE_AUD` (criterion #4 covers this), but watch the *direction*: here lineSum (80.00) vs total (80.00) is exact, so this invoice won't stress the tolerance — a stronger test would use line items that sum to 79.99 or 80.01 against an 80.00 total to actually exercise the 0.02 band.
- **Empty / null guards unchanged.** The existing `subtotal/gst/total === null` guard and the `! empty($lineItems)` guard must remain — the fix sits inside the existing `! empty` block. Don't let the OR restructure drop a guard.
- **Mixed-rate is explicitly out of scope** (clarified). Flag for the record: an invoice with one GST-free line and standard-rated lines will still false-flag or false-pass under this fix — that's accepted debt, not a defect of REQ-019.

## Connector — Links & Reuse

- `ExplanationService` (REQ-014) consumes `ValidationError[]`; removing the false `subtotal` error upstream automatically corrects the wrong explanation. No second REQ needed — but the run should assert the explanation no longer names a subtotal mismatch for this invoice, closing the loop the user actually reported.
- `ConfidenceScorer`, `InvoiceResult`, and the result page (REQ-017) all read the same error list — fewer false errors propagate cleanly with zero schema change. This keeps REQ-019 a genuine single-file behavioural fix.
- Decisions memory (REQ-008): use `ExtractedInvoice` camelCase value props (`subtotal`, `gst`, `total`, `lineItems[].amount`) and snake_case confidence keys. REQ-019 touches values only, so it's consistent — no new decision contradicted, and no new decision line warranted (routine bug fix).
- No `gstInclusive` flag exists anywhere in `app/` (confirmed by grep) — the arithmetic-derivation choice keeps the extraction prompt/tool schema untouched, which is the lowest-blast-radius path and matches the recorded clarification.

## Summary

The decomposition is sound and tightly scoped: one single-file fix to `ArithmeticValidator` check (a), accepting line items that reconcile against either subtotal (exclusive) or total (inclusive), guarded by the existing tolerance and corroborated by checks (b)/(c). Two refinements would strengthen REQ-019 before running: (1) add an acceptance criterion proving the inclusive branch still surfaces a genuine `gst`/`total` defect (so the false-accept widening can't swallow a real error), and (2) make the supplier-facing explanation correction an explicit assertion rather than a passive verification — that's the symptom the user actually reported.
