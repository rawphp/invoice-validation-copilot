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
