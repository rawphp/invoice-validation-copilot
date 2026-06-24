<?php

declare(strict_types=1);

use App\DTO\ConfidenceResult;
use App\DTO\ExtractedInvoice;
use App\DTO\FieldConfidence;
use App\DTO\ValidationError;
use App\Enums\ServiceCategory;
use App\Services\Invoice\ConfidenceScorer;

/**
 * Build a minimal ExtractedInvoice with configurable per-field confidence scores.
 *
 * @param  array<string, float>  $scores  Keyed by SCALAR_FIELDS names.
 */
function makeInvoice(array $scores = []): ExtractedInvoice
{
    $confidence = [];
    foreach (ExtractedInvoice::SCALAR_FIELDS as $field) {
        $confidence[$field] = new FieldConfidence($field, $scores[$field] ?? 1.0);
    }

    return new ExtractedInvoice(
        supplier: 'ACME Pty Ltd',
        abn: '12345678901',
        invoiceDate: '2024-01-15',
        dueDate: '2024-02-15',
        lineItems: [],
        subtotal: 100.0,
        gst: 10.0,
        total: 110.0,
        serviceCategory: ServiceCategory::Other,
        confidence: $confidence,
    );
}

// ---------------------------------------------------------------------------
// ConfidenceResult DTO
// ---------------------------------------------------------------------------

it('returns a ConfidenceResult with overall and passed properties', function (): void {
    $invoice = makeInvoice();
    $scorer = new ConfidenceScorer();

    $result = $scorer->score($invoice, []);

    expect($result)->toBeInstanceOf(ConfidenceResult::class);
    expect($result->overall)->toBeFloat();
    expect($result->passed)->toBeBool();
});

// ---------------------------------------------------------------------------
// Average of field confidence scores
// ---------------------------------------------------------------------------

it('computes overall as the average of all field confidence scores when no errors', function (): void {
    // 7 SCALAR_FIELDS all at 0.8 => average = 0.8
    $invoice = makeInvoice(array_fill_keys(ExtractedInvoice::SCALAR_FIELDS, 0.8));
    $scorer = new ConfidenceScorer();

    $result = $scorer->score($invoice, []);

    expect($result->overall)->toBeGreaterThanOrEqual(0.79999)
        ->and($result->overall)->toBeLessThanOrEqual(0.80001);
});

it('uses zero for fields missing from the confidence map', function (): void {
    // Supply an invoice with an empty confidence map — all fields default to 0.0
    $invoice = new ExtractedInvoice(
        supplier: null,
        abn: null,
        invoiceDate: null,
        dueDate: null,
        lineItems: [],
        subtotal: null,
        gst: null,
        total: null,
        serviceCategory: ServiceCategory::Other,
        confidence: [],
    );

    $scorer = new ConfidenceScorer();
    $result = $scorer->score($invoice, []);

    expect($result->overall)->toEqual(0.0);
});

// ---------------------------------------------------------------------------
// Hard-error penalties
// ---------------------------------------------------------------------------

it('penalises for each hard validation error', function (): void {
    $invoice = makeInvoice(array_fill_keys(ExtractedInvoice::SCALAR_FIELDS, 1.0));

    $errors = [
        new ValidationError('abn', 'error', 'ABN invalid'),
    ];

    $scorer = new ConfidenceScorer();
    $result = $scorer->score($invoice, $errors);

    // Perfect field scores but one hard error reduces score below 1.0
    expect($result->overall)->toBeLessThan(1.0);
});

it('applies a cumulative penalty for multiple hard errors', function (): void {
    $invoice = makeInvoice(array_fill_keys(ExtractedInvoice::SCALAR_FIELDS, 1.0));

    $errors = [
        new ValidationError('abn', 'error', 'ABN invalid'),
        new ValidationError('total', 'error', 'Total mismatch'),
    ];

    $scorer = new ConfidenceScorer();
    $resultOne = $scorer->score($invoice, [new ValidationError('abn', 'error', 'ABN invalid')]);
    $resultTwo = $scorer->score($invoice, $errors);

    expect($resultTwo->overall)->toBeLessThan($resultOne->overall);
});

it('does not penalise for warning-severity ValidationErrors', function (): void {
    $invoice = makeInvoice(array_fill_keys(ExtractedInvoice::SCALAR_FIELDS, 0.8));

    $withWarning = [new ValidationError('due_date', 'warning', 'Due date is in the past')];
    $withoutWarning = [];

    $scorer = new ConfidenceScorer();

    expect($scorer->score($invoice, $withWarning)->overall)
        ->toEqual($scorer->score($invoice, $withoutWarning)->overall);
});

// ---------------------------------------------------------------------------
// Clamping
// ---------------------------------------------------------------------------

it('clamps overall score to 0.0 even with many hard errors', function (): void {
    $invoice = makeInvoice(array_fill_keys(ExtractedInvoice::SCALAR_FIELDS, 0.0));

    $errors = array_fill(0, 20, new ValidationError('supplier', 'error', 'Missing'));

    $scorer = new ConfidenceScorer();
    $result = $scorer->score($invoice, $errors);

    expect($result->overall)->toBeGreaterThanOrEqual(0.0);
    expect($result->overall)->toBeLessThanOrEqual(1.0);
});

it('clamps overall score to 1.0', function (): void {
    $invoice = makeInvoice(array_fill_keys(ExtractedInvoice::SCALAR_FIELDS, 1.0));

    $scorer = new ConfidenceScorer();
    $result = $scorer->score($invoice, []);

    expect($result->overall)->toBeLessThanOrEqual(1.0);
});

// ---------------------------------------------------------------------------
// Verdict: passed
// ---------------------------------------------------------------------------

it('passes verdict when score is at or above threshold and no hard errors', function (): void {
    $invoice = makeInvoice(array_fill_keys(ExtractedInvoice::SCALAR_FIELDS, 1.0));

    $scorer = new ConfidenceScorer();
    $result = $scorer->score($invoice, []);

    expect($result->passed)->toBeTrue();
});

it('fails verdict when any hard error exists regardless of score', function (): void {
    $invoice = makeInvoice(array_fill_keys(ExtractedInvoice::SCALAR_FIELDS, 1.0));

    $errors = [new ValidationError('abn', 'error', 'ABN invalid')];

    $scorer = new ConfidenceScorer();
    $result = $scorer->score($invoice, $errors);

    expect($result->passed)->toBeFalse();
});

it('fails verdict when score is below threshold even with no hard errors', function (): void {
    // Very low confidence scores — average will be well below any reasonable threshold
    $invoice = makeInvoice(array_fill_keys(ExtractedInvoice::SCALAR_FIELDS, 0.1));

    $scorer = new ConfidenceScorer();
    $result = $scorer->score($invoice, []);

    expect($result->passed)->toBeFalse();
});

it('does not fail verdict for warnings alone', function (): void {
    $invoice = makeInvoice(array_fill_keys(ExtractedInvoice::SCALAR_FIELDS, 1.0));

    $warnings = [new ValidationError('due_date', 'warning', 'Advisory only')];

    $scorer = new ConfidenceScorer();
    $result = $scorer->score($invoice, $warnings);

    expect($result->passed)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Default threshold exposure
// ---------------------------------------------------------------------------

it('exposes a configurable pass threshold', function (): void {
    $invoice = makeInvoice(array_fill_keys(ExtractedInvoice::SCALAR_FIELDS, 0.6));

    // With a strict threshold the same invoice should fail
    $strictScorer = new ConfidenceScorer(threshold: 0.8);
    // With a lenient threshold it should pass
    $lenientScorer = new ConfidenceScorer(threshold: 0.5);

    expect($strictScorer->score($invoice, [])->passed)->toBeFalse();
    expect($lenientScorer->score($invoice, [])->passed)->toBeTrue();
});
