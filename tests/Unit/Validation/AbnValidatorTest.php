<?php

declare(strict_types=1);

use App\DTO\ExtractedInvoice;
use App\DTO\FieldConfidence;
use App\DTO\LineItem;
use App\DTO\ValidationError;
use App\Enums\ServiceCategory;
use App\Services\Validation\AbnValidator;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

function invoiceWithAbn(?string $abn): ExtractedInvoice
{
    $confidence = [];
    foreach (ExtractedInvoice::SCALAR_FIELDS as $field) {
        $confidence[$field] = new FieldConfidence($field, 0.9);
    }

    return new ExtractedInvoice(
        supplier: 'Test Pty Ltd',
        abn: $abn,
        invoiceDate: '2024-07-15',
        dueDate: '2024-08-15',
        lineItems: [
            new LineItem(description: 'Consulting', qty: 1.0, rate: 100.0, amount: 100.0),
        ],
        subtotal: 100.0,
        gst: 10.0,
        total: 110.0,
        serviceCategory: ServiceCategory::ProfessionalServices,
        confidence: $confidence,
    );
}

// ---------------------------------------------------------------------------
// AbnValidator — valid ABN
// ---------------------------------------------------------------------------

it('AbnValidator returns no errors for a known-valid ABN (51 824 753 556)', function () {
    $validator = new AbnValidator();
    $errors = $validator->validate(invoiceWithAbn('51 824 753 556'));

    expect($errors)->toBeEmpty();
});

it('AbnValidator accepts an ABN without spaces (51824753556)', function () {
    $validator = new AbnValidator();
    $errors = $validator->validate(invoiceWithAbn('51824753556'));

    expect($errors)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// AbnValidator — whitespace normalisation
// ---------------------------------------------------------------------------

it('AbnValidator tolerates leading and trailing whitespace', function () {
    $validator = new AbnValidator();
    $errors = $validator->validate(invoiceWithAbn('  51 824 753 556  '));

    expect($errors)->toBeEmpty();
});

it('AbnValidator tolerates extra internal spaces', function () {
    $validator = new AbnValidator();
    // Same digits, different spacing
    $errors = $validator->validate(invoiceWithAbn('5 1 8 2 4 7 5 3 5 5 6'));

    expect($errors)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// AbnValidator — checksum failure
// ---------------------------------------------------------------------------

it('AbnValidator returns exactly one error for a checksum-failing 11-digit number', function () {
    $validator = new AbnValidator();
    // Mutate the last digit of the valid ABN (51 824 753 556 → 51 824 753 557)
    $errors = $validator->validate(invoiceWithAbn('51 824 753 557'));

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toBeInstanceOf(ValidationError::class)
        ->and($errors[0]->field)->toBe('abn');
});

it('AbnValidator sets severity to error for checksum failure', function () {
    $validator = new AbnValidator();
    $errors = $validator->validate(invoiceWithAbn('51 824 753 557'));

    expect($errors[0]->severity)->toBe('error');
});

it('AbnValidator checksum error message is human-readable', function () {
    $validator = new AbnValidator();
    $errors = $validator->validate(invoiceWithAbn('51 824 753 557'));

    expect($errors[0]->message)->toBeString()->not->toBeEmpty();
});

// ---------------------------------------------------------------------------
// AbnValidator — format errors (wrong digit count)
// ---------------------------------------------------------------------------

it('AbnValidator returns a format error for a 10-digit value', function () {
    $validator = new AbnValidator();
    $errors = $validator->validate(invoiceWithAbn('5182475355'));

    expect($errors)->toHaveCount(1)
        ->and($errors[0]->field)->toBe('abn')
        ->and($errors[0]->severity)->toBe('error');
});

it('AbnValidator returns a format error for a 12-digit value', function () {
    $validator = new AbnValidator();
    $errors = $validator->validate(invoiceWithAbn('518247535560'));

    expect($errors)->toHaveCount(1)
        ->and($errors[0]->field)->toBe('abn');
});

it('AbnValidator returns a format error when ABN contains non-digit characters', function () {
    $validator = new AbnValidator();
    $errors = $validator->validate(invoiceWithAbn('5182X753556'));

    expect($errors)->toHaveCount(1)
        ->and($errors[0]->field)->toBe('abn');
});

// ---------------------------------------------------------------------------
// AbnValidator — null / missing ABN (should not double-error; RequiredFieldsValidator owns this)
// ---------------------------------------------------------------------------

it('AbnValidator returns no errors when ABN is null (RequiredFieldsValidator owns missing-ABN)', function () {
    $validator = new AbnValidator();
    $errors = $validator->validate(invoiceWithAbn(null));

    expect($errors)->toBeEmpty();
});
