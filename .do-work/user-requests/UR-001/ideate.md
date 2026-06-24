# Ideate — UR-001

**Reviewed:** 2026-06-24

## Explorer — Assumptions & Perspectives

- **The brief assumes every demo invoice is Australian, but the example screens are American.** The validation layer hard-codes Australian rules (ABN 11-digit checksum, GST = 10%). Yet `assets/validation_review_error_handling/screen.png` shows "101 Silicon Way, CA 94105", "Net 30", and the dashboard mixes vendors with no ABN. Scenario: you demo with one of those US-style invoices and the ABN + GST validators throw confident false errors, undercutting the "precision" pitch. Triggered by: the Validation section vs. the inspiration screens. **Confirm the demo invoice set is genuinely Australian before building the validators.**

- **"Supplier-friendly explanation" implies an audience that never receives it.** The phrase frames the output as something sent to the supplier, but the in-memory single-page scope has no send/email path — the explanation is shown only to the operator running the demo. Scenario: a client asks "so how does the supplier get this?" and the honest answer is "they don't, yet." Triggered by: output list item 5. Decide whether to reframe it as an operator-facing "what to tell the supplier" card, or note send-to-supplier as explicit future scope.

- **Per-field confidence "from Claude" assumes the model's self-reported numbers mean something.** LLM-emitted confidence is uncalibrated — 0.87 is not a probability. Scenario: a technical interviewer asks "what does 87% actually represent?" and there's no defensible answer. Triggered by: the Confidence section. Define confidence concretely (e.g. derived from field presence + cross-checks, not just asked-for), or label it "model-estimated" so it isn't oversold.

## Challenger — Risks & Edge Cases

- **`php artisan serve` binds to 127.0.0.1 — the phone cannot reach it, so the QR feature dies on demo day.** The QR encodes a LAN IP, but the default dev server isn't listening on the LAN interface, and Vite/Inertia asset URLs default to `localhost` (assets 404 on the phone). Scenario: you scan the QR live, the page either won't load or loads unstyled with broken JS. Triggered by: the mobile-QR requirement. Capture must specify `serve --host=0.0.0.0`, a Vite `host` setting, LAN-IP-aware asset URLs, and a same-network/firewall pre-flight check — this is the single most likely thing to fail in front of a client.

- **GST is not always 10%, and strict arithmetic equality false-positives on rounding.** Australian invoices legitimately contain GST-free lines (food, exports, some services) and per-line cent rounding, so `GST = 10% of subtotal` and `subtotal + GST = total` will flag correct invoices as broken. Scenario: a real invoice with one GST-free line shows a "critical error" that isn't one. Triggered by: ArithmeticValidator. Needs a tolerance (±a few cents) and a notion of taxable-vs-GST-free amount, or the validator must be scoped to standard-rated invoices only and say so.

- **Phone-camera photos exceed Claude's image limits.** A modern phone photo is often 4–12 MB and beyond the API's per-image size/dimension caps, so the vision call rejects exactly the inputs the QR feature is designed to capture. Scenario: the slickest demo path (snap invoice on phone → validate) is the one that errors. Triggered by: mobile-QR + ExtractionService. Capture must add server-side downscale/recompress before the API call.

- **DD/MM vs MM/DD ambiguity will silently corrupt dates.** "10/06/2023" is June 10 in AU and October 6 in the US; the DateValidator's "not future-dated" check depends on getting this right. Scenario: a valid invoice date is misread as future and flagged, or an invalid one passes. Triggered by: date extraction + DateValidator. Pin the expected locale (AU = DD/MM) in the extraction prompt and validator.

- **No mock fallback means any network/API hiccup ends the live demo.** Re-flagging the decision made earlier: for a *live client* showcase this is the highest-impact single point of failure, and it's external to your code. Triggered by: the "live call only" decision. At minimum, capture should ensure the API-failure path renders a graceful, non-crashing error rather than a stack trace.

## Connector — Links & Reuse

- **The design system should be one source of truth, not per-component hex.** `assets/precision_ledger/DESIGN.md` defines a full token set; if those colors/type scales are pasted into individual Vue components they'll drift. Reuse: translate DESIGN.md into Tailwind theme tokens / CSS variables once, consume everywhere. Capture should make this a discrete task feeding all UI REQs.

- **Both LLM calls share one client seam — and that seam is the test mock.** ExtractionService (vision) and ExplanationService (text) both call Anthropic; a single injectable client interface serves both and is the exact boundary the Pest feature test mocks. Reuse: design one `ClaudeClient` contract up front so extraction, explanation, and the test fake all sit on it.

- **ABN checksum is a fixed, well-specified algorithm with known test vectors.** The ATO modulus-89 weighted checksum (weights 10,1,3,5,7,9,11,13,15,17,19; subtract 1 from the first digit) is deterministic and has published valid/invalid ABNs. Reuse: implement against the canonical spec and unit-test with known-good (e.g. 51 824 753 556) and known-bad values — cheap, high-credibility win.

- **Confidence depends on validation, so ordering couples two units.** "Overall confidence penalized per validation failure" means confidence can't be computed until ValidationService has run. Cross-cutting: capture must sequence the pipeline as extract → validate → score → explain, and not let confidence be assembled inside extraction.

## Summary

The brief is sound but its credibility rests on three things capture must pin down: (1) confirm the demo invoices are actually Australian, or the AU-specific validators (ABN, GST 10%) will confidently mis-flag the US-style invoices shown in the asset screens; (2) the mobile-QR path has two concrete failure modes — LAN binding/asset URLs and phone-photo image-size limits — that will break the most impressive demo moment unless designed in; (3) the arithmetic/date validators need tolerances and a locale to avoid false positives on legitimate invoices. None of these block decomposition, but each should land as an explicit constraint on the relevant REQ.
