# Ideate — UR-006

**Reviewed:** 2026-06-25

> **The user is correct — this is a false-positive validation error, not a bad invoice.** The screenshot shows a "SUBTOTAL" critical error ("Line items sum to 139.50 but subtotal is 257.72"). But the charge lines (Garden Maintenance $270.00 + Fuel Surcharge $13.50 = $283.50) reconcile exactly to the Total ($283.50), and subtotal $257.72 + GST $25.78 = $283.50 also holds (that's why "Arithmetic total match" and "GST calculation" both passed). The only reason check (a) fires is that `ArithmeticValidator` sums the two **adjustment rows** — "Payment received" (−$150.00) and "Late Fee" (+$6.00) — into the line-item total, pulling it down to $139.50, which then reconciles to neither subtotal nor total.

## Explorer — Assumptions & Perspectives

- **The brief is a screenshot, not a spec — the user's intent is "stop flagging this as broken," not "rewrite the validator."** Concrete scenario: capture could over-scope this into a line-item-typing redesign when the user just wants the FAILED status gone for legitimately-consistent invoices. Trigger: the verbatim brief is only "this analysis seems to be wrong" + image; the desired terminal state (no critical error for this invoice class) must be inferred, so it should be stated explicitly before decomposition.
- **"Adjustment row" is the missing concept the whole fix hinges on.** A line is an adjustment (excluded from charge reconciliation) if it's a payment, credit, refund, or post-subtotal fee. Concrete scenario: "Payment received" is negative (easy to spot by sign), but "Late Fee +$6.00" is a *positive* adjustment — so a sign-only rule excludes the payment but keeps the late fee, leaving `$lineSum` at $289.50, still not reconciling. Trigger: the screenshot's two adjustment rows have *opposite signs*, proving sign alone is insufficient.
- **Two invoice orientations now coexist and must both pass.** REQ-021's fixture has Total = *net* balance ($18.00, line sum reconciles to net Total). This screenshot has Total = *gross* charge total ($283.50, charge lines reconcile to Total, adjustments sit on top). Concrete scenario: a fix that assumes one orientation breaks the other. Trigger: `decisions.md` REQ-021 only covered check (c) for net-Total invoices; check (a) was never given adjustment-awareness.

## Challenger — Risks & Edge Cases

- **Silently passing loses the helpful explanation about the $139.50 balance due.** REQ-022 established that info-severity findings render in a Notes section and don't block. Concrete scenario: if we just exclude adjustments and emit nothing, the operator no longer sees "charges $283.50, balance after payment $139.50" — useful context disappears. Trigger: check (c) already emits an info note for the net-Total case; check (a) needs the symmetric note when charge lines reconcile but the full sum doesn't.
- **A classifier that excludes adjustment rows must not let real typos slip through.** Concrete scenario: an invoice where a genuine charge line was mis-keyed could be "rescued" by misclassifying it as an adjustment, suppressing a real error. Trigger: AC3 of REQ-019 (`gstInclusiveInvoiceWithWrongLineItems`) and REQ-021 AC3 (`paymentAllocatedInvoiceWithGenuineTypo`) both assert genuine discrepancies still error — any new classification logic must keep those green.
- **Keyword-based classification is locale/wording-fragile.** Concrete scenario: "Amount paid", "Balance b/f", "Less deposit", "Credit note" all denote adjustments but won't match a naive `payment|credit` keyword list, re-introducing the false positive on the next invoice. Trigger: the screenshot's adjustment rows use free-text descriptions ("Payment received (15/05/2026)", "Late Fee - Late Payment Fee (12/05/2026)") that the extraction model produces non-deterministically.

## Connector — Links & Reuse

- **This is the same root cause class as REQ-019 and REQ-021 — `$lineSum` blindly sums every `LineItem`.** Reuse: extend the existing `ArithmeticValidator` check (a) rather than add a new validator; the "reconciles to subtotal OR total" branch already exists. Trigger: lines 58–80 of `ArithmeticValidator.php` are the exact site, and `tests/Unit/Validation/ArithmeticValidatorTest.php` already has the fixture pattern to copy.
- **Info-severity plumbing is already end-to-end (REQ-022).** Reuse: `ValidationError` info severity, `ExplanationService` all-clear framing, `ValidationPanel` Notes section, and `ConfidenceScorer` ignoring info findings all exist — a new check-(a) info note needs zero frontend/DTO work. Trigger: `decisions.md` REQ-022 documents the contract; the result page in the screenshot already renders a Notes-style passing-checks area.
- **If line-item typing is chosen, it touches the extraction layer (Claude tool schema + `LineItem` DTO).** Reuse vs. cost: REQ-021 deliberately recorded "no new extraction field," so adding a `kind`/`category` to `LineItem` reverses a standing lean and pulls in the extraction prompt, `ExtractionService`, and its tests (frontend layer untouched, backend + extraction touched). Trigger: `LineItem` currently has only `description/qty/rate/amount`; classification by description must live either in the prompt or in a PHP helper.

## Open decision (pivotal — shapes the whole decomposition)

How should the validator distinguish **charge** lines from **adjustment** lines (payments/credits/fees) so check (a) reconciles charge lines only?

- **Option A — PHP-side classifier (backend only).** A helper classifies each `LineItem` as charge vs adjustment using description keywords + sign, used only inside `ArithmeticValidator`. No extraction/DTO/prompt change; respects REQ-021's "no new extraction field." Risk: keyword fragility.
- **Option B — Extraction tags line kind (backend + extraction).** Add a `kind` field to `LineItem`, populated by the Claude tool schema. More robust/semantic, but reverses the "no new extraction field" lean and touches the extraction prompt + its tests.
- **Option C — Subset/charge-prefix reconciliation (backend only, no classification).** Check whether the largest sign-consistent prefix or any subset of lines reconciles to subtotal/total. Rejected-leaning: combinatorial, unexplainable, brittle.

## Summary

The user is right: it's a false-positive, isolated to `ArithmeticValidator` check (a), which sums adjustment rows (payment −$150, late fee +$6) into the charge reconciliation. The fix must exclude adjustment lines so the charge lines ($283.50) reconcile to Total, then emit an info note (reusing REQ-022 plumbing) explaining the $139.50 balance — while keeping the REQ-019/REQ-021 "genuine typo still errors" tests green. The one decision to settle before capture is **how to classify adjustment lines (Option A PHP-side vs Option B extraction-tagged)**, because it determines whether this is a backend-only fix or a backend+extraction change.
