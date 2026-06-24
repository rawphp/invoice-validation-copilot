# Ideate — UR-004

**Reviewed:** 2026-06-24

## Explorer — Assumptions & Perspectives

- The extracted **Total ($18.00) is a net balance-due, not a charge total.** The line items include payment/credit/late-fee rows (Payment received −$393.75, Late Payment Credit −$6.00, four late fees +$24.00), so Total = goods − payments + fees. Check (c) in `ArithmeticValidator` (`subtotal + GST = total`) assumes Total is the charge total, which is only true for invoices with no payments allocated. The brief's invoice is internally consistent (line items sum exactly to $18.00) yet is flagged critical.
- **The user-facing explanation gives actively wrong advice.** `ExplanationService` tells the AP clerk to ask Vista Lawn Care for "a corrected invoice where the total matches subtotal plus GST" — but the invoice is correct; it just has a payment allocated. A clerk acting on this would chase a non-existent supplier error. The harm is not just a false flag, it is a wrong next-action.
- The brief is narrow ("this invoice fails") but the underlying ask is "stop flagging payment-allocated invoices as arithmetic errors while still catching genuine total typos."

## Challenger — Risks & Edge Cases

- **Over-relaxation risk.** If check (c) is simply skipped whenever payment-like rows exist, a genuine Total typo on an invoice that also has a payment row would slip through. The relaxation must be gated on independent corroboration — the line items must *sum to the Total* — so a typo'd Total (which the line items would NOT reconcile to) still fires.
- **Inconsistent-checks smell, again.** This is the same failure shape REQ-019 (UR-002) fixed: check (a) was already taught that line items can reconcile to the Total, but check (c) still hard-assumes `subtotal + GST = total`. The two checks contradict each other on what "total" means. Whatever fix lands must leave (a), (b), (c) mutually consistent.
- **Negative / zero net totals.** Overpayment or a credit exceeding charges yields a negative Total. Confirm the reconciliation tolerance and number formatting behave for negative totals (the `abs()` comparison already handles sign, but worth a test).
- **No line items present.** If a payment-allocated invoice arrives with no extracted line items, there is no corroboration available and check (c) must still run on subtotal+GST — i.e., the relaxation only applies when line items exist and reconcile to the Total.

## Connector — Links & Reuse

- **Direct sibling of REQ-019** (archive: `REQ-019-gst-inclusive-subtotal-check.md`, UR-002). Same file (`ArithmeticValidator.php`), same DTOs, same root cause (checks disagree on the meaning of the figures), and the recorded decision was to distinguish cases **arithmetically — no new extraction field, no prompt/tool-schema change.** This fix should follow that precedent: reuse `TOLERANCE_AUD` and the already-computed `$reconcilesToTotal` flag rather than adding a payment-detection field.
- **`$reconcilesToTotal` is already computed** in check (a). Check (c) can reuse it: when the line items reconcile to the Total, the Total is independently corroborated and the `subtotal + GST = total` assertion is no longer the right consistency test.
- **ExplanationService consumes `ValidationError[]`** — removing the false `total` error upstream automatically corrects the wrong supplier-facing explanation, exactly as in REQ-019. No explanation change needed, only verification that the message disappears.
- Existing test home: `tests/Unit/Validation/ArithmeticValidatorTest.php`.

## Summary

This is the same class of bug as the already-fixed REQ-019, one check further along: `ArithmeticValidator` check (c) assumes the Total equals subtotal+GST, but on payment-allocated invoices the Total is a net balance and the line items (not subtotal+GST) corroborate it. The minimal, precedent-consistent fix is to let check (c) pass when the line items reconcile to the Total — gated on that corroboration so genuine Total typos still fire. No new extraction field; reuse `TOLERANCE_AUD` and the existing `$reconcilesToTotal` computation.
