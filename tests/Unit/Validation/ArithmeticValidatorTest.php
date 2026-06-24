<?php

declare(strict_types=1);

use App\DTO\ExtractedInvoice;
use App\DTO\FieldConfidence;
use App\DTO\LineItem;
use App\DTO\ValidationError;
use App\Enums\ServiceCategory;
use App\Services\Validation\ArithmeticValidator;

// ---------------------------------------------------------------------------
// Fixture helpers
// ---------------------------------------------------------------------------

/**
 * Build a minimal FieldConfidence map for all scalar fields.
 *
 * @return array<string, FieldConfidence>
 */
function arithmeticConfidence(): array
{
    $confidence = [];
    foreach (ExtractedInvoice::SCALAR_FIELDS as $field) {
        $confidence[$field] = new FieldConfidence($field, 0.95);
    }

    return $confidence;
}

/**
 * A perfectly consistent standard-rated invoice:
 *   lines: 100 + 200 = 300 subtotal
 *   GST:   30  (10% of 300)
 *   total: 330 (300 + 30)
 */
function consistentInvoice(): ExtractedInvoice
{
    return new ExtractedInvoice(
        supplier: 'Acme Pty Ltd',
        abn: '12 345 678 901',
        invoiceDate: '2024-07-15',
        dueDate: '2024-08-15',
        lineItems: [
            new LineItem(description: 'Service A', qty: 1.0, rate: 100.0, amount: 100.0),
            new LineItem(description: 'Service B', qty: 2.0, rate: 100.0, amount: 200.0),
        ],
        subtotal: 300.0,
        gst: 30.0,
        total: 330.0,
        serviceCategory: ServiceCategory::ProfessionalServices,
        confidence: arithmeticConfidence(),
    );
}

/**
 * Invoice where GST is wrong by $5 (35.0 instead of 30.0) — beyond tolerance.
 * Subtotal and total are still internally consistent (subtotal + gst = total, 300+35=335).
 * So only the GST check should fail (gst ≠ 10% of subtotal).
 * Note: subtotal + gst = total still holds (335), so 'total' check should pass.
 */
function invoiceWithWrongGst(): ExtractedInvoice
{
    return new ExtractedInvoice(
        supplier: 'Acme Pty Ltd',
        abn: '12 345 678 901',
        invoiceDate: '2024-07-15',
        dueDate: '2024-08-15',
        lineItems: [
            new LineItem(description: 'Service A', qty: 1.0, rate: 100.0, amount: 100.0),
            new LineItem(description: 'Service B', qty: 2.0, rate: 100.0, amount: 200.0),
        ],
        subtotal: 300.0,
        gst: 35.0,   // should be 30.0 — $5 off
        total: 335.0, // consistent with wrong gst: 300 + 35 = 335
        serviceCategory: ServiceCategory::ProfessionalServices,
        confidence: arithmeticConfidence(),
    );
}

/**
 * Invoice where subtotal + GST ≠ total (beyond tolerance).
 * GST is correct (10% of subtotal = 30), but total is wrong (350 instead of 330).
 */
function invoiceWithWrongTotal(): ExtractedInvoice
{
    return new ExtractedInvoice(
        supplier: 'Acme Pty Ltd',
        abn: '12 345 678 901',
        invoiceDate: '2024-07-15',
        dueDate: '2024-08-15',
        lineItems: [
            new LineItem(description: 'Service A', qty: 1.0, rate: 100.0, amount: 100.0),
            new LineItem(description: 'Service B', qty: 2.0, rate: 100.0, amount: 200.0),
        ],
        subtotal: 300.0,
        gst: 30.0,    // correct: 10% of 300
        total: 350.0, // wrong: should be 330 — $20 off
        serviceCategory: ServiceCategory::ProfessionalServices,
        confidence: arithmeticConfidence(),
    );
}

/**
 * Invoice with ±$0.02 rounding on line item sums — within tolerance, no errors expected.
 * Lines sum to 299.99, subtotal declared as 300.00 (1c discrepancy — within $0.02 tolerance).
 */
function invoiceWithTinyRounding(): ExtractedInvoice
{
    return new ExtractedInvoice(
        supplier: 'Acme Pty Ltd',
        abn: '12 345 678 901',
        invoiceDate: '2024-07-15',
        dueDate: '2024-08-15',
        lineItems: [
            new LineItem(description: 'Service A', qty: 1.0, rate: 99.99, amount: 99.99),
            new LineItem(description: 'Service B', qty: 2.0, rate: 100.0, amount: 200.00),
        ],
        subtotal: 300.00,   // lines sum to 299.99 — 1c off (within $0.02 tolerance)
        gst: 30.00,         // 10% of 300.00 = 30.00 (within tolerance of actual 10% of 299.99=29.999)
        total: 330.00,      // 300.00 + 30.00 = 330.00 (exact)
        serviceCategory: ServiceCategory::ProfessionalServices,
        confidence: arithmeticConfidence(),
    );
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('ArithmeticValidator returns no errors for an internally consistent standard-rated invoice', function () {
    $validator = new ArithmeticValidator();
    $errors = $validator->validate(consistentInvoice());

    expect($errors)->toBeEmpty();
});

it('ArithmeticValidator returns one error on gst field when GST is off by $5 beyond tolerance', function () {
    $validator = new ArithmeticValidator();
    $errors = $validator->validate(invoiceWithWrongGst());

    // Only the GST field should be flagged
    $fields = array_map(fn (ValidationError $e) => $e->field, $errors);

    expect($fields)->toContain('gst');
    expect(count(array_filter($fields, fn ($f) => $f === 'gst')))->toBe(1);
});

it('ArithmeticValidator flags gst error with error severity', function () {
    $validator = new ArithmeticValidator();
    $errors = $validator->validate(invoiceWithWrongGst());

    $gstError = array_values(array_filter($errors, fn (ValidationError $e) => $e->field === 'gst'));

    expect($gstError)->toHaveCount(1)
        ->and($gstError[0]->severity)->toBe('error');
});

it('ArithmeticValidator returns one error on total field when subtotal+GST does not equal total', function () {
    $validator = new ArithmeticValidator();
    $errors = $validator->validate(invoiceWithWrongTotal());

    $fields = array_map(fn (ValidationError $e) => $e->field, $errors);

    expect($fields)->toContain('total');
    expect(count(array_filter($fields, fn ($f) => $f === 'total')))->toBe(1);
});

it('ArithmeticValidator flags total error with error severity', function () {
    $validator = new ArithmeticValidator();
    $errors = $validator->validate(invoiceWithWrongTotal());

    $totalError = array_values(array_filter($errors, fn (ValidationError $e) => $e->field === 'total'));

    expect($totalError)->toHaveCount(1)
        ->and($totalError[0]->severity)->toBe('error');
});

it('ArithmeticValidator returns no errors when discrepancy is within cent tolerance', function () {
    $validator = new ArithmeticValidator();
    $errors = $validator->validate(invoiceWithTinyRounding());

    expect($errors)->toBeEmpty();
});

it('ArithmeticValidator returns ValidationError instances', function () {
    $validator = new ArithmeticValidator();
    $errors = $validator->validate(invoiceWithWrongGst());

    foreach ($errors as $error) {
        expect($error)->toBeInstanceOf(ValidationError::class);
    }
});

it('ArithmeticValidator implements Validator contract', function () {
    $validator = new ArithmeticValidator();

    expect($validator)->toBeInstanceOf(\App\Services\Validation\Validator::class);
});
