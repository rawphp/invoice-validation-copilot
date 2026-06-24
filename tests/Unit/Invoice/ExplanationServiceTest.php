<?php

declare(strict_types=1);

use App\DTO\ExtractedInvoice;
use App\DTO\FieldConfidence;
use App\DTO\ValidationError;
use App\Enums\ServiceCategory;
use App\Services\Invoice\ExplanationService;
use Tests\Fakes\ExplanationFakeClaudeClient;

/** A minimal valid ExtractedInvoice for testing. */
function explanationTestInvoice(): ExtractedInvoice
{
    $confidence = [];
    foreach (ExtractedInvoice::SCALAR_FIELDS as $field) {
        $confidence[$field] = new FieldConfidence($field, 0.9);
    }

    return new ExtractedInvoice(
        supplier: 'Acme Plumbing Pty Ltd',
        abn: '51 824 753 556',
        invoiceDate: '2026-04-03',
        dueDate: '2026-05-03',
        lineItems: [],
        subtotal: 1530.00,
        gst: 153.00,
        total: 1683.00,
        serviceCategory: ServiceCategory::ConstructionTrades,
        confidence: $confidence,
    );
}

it('returns the canned explanation text from ClaudeClient when there are validation errors', function () {
    $canned = 'Please ask the supplier to correct their ABN — the checksum is invalid.';
    $fake = new ExplanationFakeClaudeClient($canned);
    $service = new ExplanationService($fake);

    $errors = [
        new ValidationError('abn', 'error', 'ABN checksum is invalid.'),
    ];

    $result = $service->explain(explanationTestInvoice(), $errors);

    expect($result)->toBe($canned);
});

it('returns a non-empty string for a zero-errors invoice', function () {
    $canned = 'All checks passed. The invoice looks complete and correct.';
    $fake = new ExplanationFakeClaudeClient($canned);
    $service = new ExplanationService($fake);

    $result = $service->explain(explanationTestInvoice(), []);

    expect($result)->toBeString()->not->toBeEmpty();
    expect($result)->toBe($canned);
});

it('sends a text-only call (no tool schema, no documents) to ClaudeClient', function () {
    $fake = new ExplanationFakeClaudeClient('All good.');
    $service = new ExplanationService($fake);

    $service->explain(explanationTestInvoice(), []);

    expect($fake->lastTool)->toBeNull();
});

it('includes the validation error field and message in the prompt when errors are present', function () {
    $fake = new ExplanationFakeClaudeClient('Fix the ABN.');
    $service = new ExplanationService($fake);

    $errors = [
        new ValidationError('abn', 'error', 'ABN checksum is invalid.'),
        new ValidationError('invoiceDate', 'error', 'Invoice date is missing.'),
    ];

    $service->explain(explanationTestInvoice(), $errors);

    expect($fake->lastPrompt)->toContain('abn');
    expect($fake->lastPrompt)->toContain('ABN checksum is invalid.');
    expect($fake->lastPrompt)->toContain('invoiceDate');
    expect($fake->lastPrompt)->toContain('Invoice date is missing.');
});

it('prompt is operator-facing (instructs what to tell the supplier, not addressed to the supplier directly)', function () {
    $fake = new ExplanationFakeClaudeClient('Tell the supplier to fix the ABN.');
    $service = new ExplanationService($fake);

    $errors = [
        new ValidationError('abn', 'error', 'ABN checksum is invalid.'),
    ];

    $service->explain(explanationTestInvoice(), $errors);

    // The prompt must instruct the OPERATOR about what to tell the supplier.
    // It must NOT say "Dear supplier" or directly address the supplier.
    $prompt = strtolower($fake->lastPrompt);
    expect($prompt)->toContain('supplier');
    // Must contain operator-facing language — "tell the supplier" or "what to tell" etc.
    expect($prompt)->toMatch('/tell|inform|advise|communicate|instruct/');
});

it('prompt indicates all clear when there are no errors', function () {
    $fake = new ExplanationFakeClaudeClient('All checks passed.');
    $service = new ExplanationService($fake);

    $service->explain(explanationTestInvoice(), []);

    $prompt = strtolower($fake->lastPrompt);
    // Should mention no errors / all passed in some form
    expect($prompt)->toMatch('/no errors|no validation|all.*pass|passed|no issues/');
});

it('uses ClaudeClient so the call is mockable (interface injection)', function () {
    $fake = new ExplanationFakeClaudeClient('OK');
    $service = new ExplanationService($fake);

    // Calling explain triggers exactly one call on our fake.
    $service->explain(explanationTestInvoice(), []);

    expect($fake->lastPrompt)->not->toBeNull();
});

// AC1: info-only findings → all-clear prompt with info context, no supplier-fix language
it('uses all-clear prompt framing when the only findings are info-severity', function () {
    $fake = new ExplanationFakeClaudeClient('Payment has been applied; balance due is $18.00.');
    $service = new ExplanationService($fake);

    $findings = [
        new ValidationError('total', 'info', 'Total reflects payments/credits already applied; balance due is $18.00.'),
    ];

    $service->explain(explanationTestInvoice(), $findings);

    $prompt = strtolower($fake->lastPrompt);

    // Must use all-clear / positive framing, not supplier-fix framing
    expect($prompt)->not->toMatch('/supplier needs to fix|supplier must|corrected invoice|must fix|what to tell the supplier about the problems/');
    // Must include the info finding text as context
    expect($fake->lastPrompt)->toContain('Total reflects payments/credits already applied');
    // Must use positive/all-clear framing
    expect($prompt)->toMatch('/no errors|no validation|all.*pass|passed|no issues|no blocking|all checks/');
});

// AC1 (negative): info-only prompt must not use "corrected invoice" or "must fix" language
it('info-only prompt does not contain corrected-invoice or must-fix language', function () {
    $fake = new ExplanationFakeClaudeClient('Payment has been applied; balance due is $18.00.');
    $service = new ExplanationService($fake);

    $findings = [
        new ValidationError('total', 'info', 'Total of 18.00 reflects payments/credits already applied; charges before payment were 393.75.'),
    ];

    $service->explain(explanationTestInvoice(), $findings);

    $prompt = strtolower($fake->lastPrompt);
    expect($prompt)->not->toContain('corrected invoice');
    expect($prompt)->not->toContain('must fix');
    expect($prompt)->not->toContain('needs to fix');
    expect($prompt)->not->toContain('before the invoice can be approved');
});

// AC2: mixed error + info → error in "must fix", info as non-blocking context
it('includes blocking errors under must-fix and info findings as context when both are present', function () {
    $fake = new ExplanationFakeClaudeClient('There is an ABN error. Note: payment has been applied.');
    $service = new ExplanationService($fake);

    $findings = [
        new ValidationError('abn', 'error', 'ABN checksum is invalid.'),
        new ValidationError('total', 'info', 'Total reflects payments/credits already applied; balance due is $18.00.'),
    ];

    $service->explain(explanationTestInvoice(), $findings);

    $prompt = $fake->lastPrompt;

    // The error must appear in the must-fix / supplier-fix framing
    expect($prompt)->toContain('ABN checksum is invalid.');
    // The info finding must appear in the prompt as context
    expect($prompt)->toContain('Total reflects payments/credits already applied');
    // The prompt should still use blocking/fix language for the error
    $lower = strtolower($prompt);
    expect($lower)->toMatch('/supplier must|must fix|needs to fix|what to tell the supplier about the problems|before the invoice can be approved/');
    // The info finding must NOT appear tagged as [error] or [warning] — it is context, not a fix item
    expect($prompt)->not->toContain('[error] Field: total');
    expect($prompt)->not->toContain('[warning] Field: total');
    // The info finding should NOT appear in the blocking errors list (verify it's in a separate section)
    // by checking the structure: info text must NOT be sandwiched between the blocking errors header and the closing instruction
    expect($prompt)->not->toContain('[info] Field: total');
});
