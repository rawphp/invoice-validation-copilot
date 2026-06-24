<?php

declare(strict_types=1);

use App\DTO\ExtractedInvoice;
use App\DTO\FieldConfidence;
use App\DTO\LineItem;
use App\DTO\ValidationError;
use App\Enums\ServiceCategory;
use App\Services\Validation\RequiredFieldsValidator;
use App\Services\Validation\ValidationService;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

function completeInvoice(): ExtractedInvoice
{
    $confidence = [];
    foreach (ExtractedInvoice::SCALAR_FIELDS as $field) {
        $confidence[$field] = new FieldConfidence($field, 0.9);
    }

    return new ExtractedInvoice(
        supplier: 'Acme Pty Ltd',
        abn: '12 345 678 901',
        invoiceDate: '2024-07-15',
        dueDate: '2024-08-15',
        lineItems: [
            new LineItem(description: 'Consulting', qty: 2.0, rate: 100.0, amount: 200.0),
        ],
        subtotal: 200.0,
        gst: 20.0,
        total: 220.0,
        serviceCategory: ServiceCategory::ProfessionalServices,
        confidence: $confidence,
    );
}

function invoiceMissingAbn(): ExtractedInvoice
{
    $confidence = [];
    foreach (ExtractedInvoice::SCALAR_FIELDS as $field) {
        $confidence[$field] = new FieldConfidence($field, 0.9);
    }

    return new ExtractedInvoice(
        supplier: 'Acme Pty Ltd',
        abn: null,
        invoiceDate: '2024-07-15',
        dueDate: '2024-08-15',
        lineItems: [
            new LineItem(description: 'Consulting', qty: 2.0, rate: 100.0, amount: 200.0),
        ],
        subtotal: 200.0,
        gst: 20.0,
        total: 220.0,
        serviceCategory: ServiceCategory::ProfessionalServices,
        confidence: $confidence,
    );
}

function emptyInvoice(): ExtractedInvoice
{
    return ExtractedInvoice::empty();
}

// ---------------------------------------------------------------------------
// ValidationError DTO
// ---------------------------------------------------------------------------

it('ValidationError carries field, severity and message', function () {
    $error = new ValidationError(
        field: 'abn',
        severity: 'error',
        message: 'ABN is missing',
    );

    expect($error->field)->toBe('abn')
        ->and($error->severity)->toBe('error')
        ->and($error->message)->toBe('ABN is missing');
});

it('ValidationError is JSON-serializable', function () {
    $error = new ValidationError(field: 'total', severity: 'warning', message: 'Total is missing');
    $json = json_encode($error);

    expect($json)->toBeString()
        ->and(json_decode($json, true))->toMatchArray([
            'field'    => 'total',
            'severity' => 'warning',
            'message'  => 'Total is missing',
        ]);
});

// ---------------------------------------------------------------------------
// ValidationService
// ---------------------------------------------------------------------------

it('ValidationService merges errors from multiple validators', function () {
    $validator1 = new class implements \App\Services\Validation\Validator {
        public function validate(ExtractedInvoice $invoice): array
        {
            return [new ValidationError('field_a', 'error', 'Missing A')];
        }
    };

    $validator2 = new class implements \App\Services\Validation\Validator {
        public function validate(ExtractedInvoice $invoice): array
        {
            return [new ValidationError('field_b', 'warning', 'Missing B')];
        }
    };

    $service = new ValidationService([$validator1, $validator2]);
    $errors = $service->run(completeInvoice());

    expect($errors)->toHaveCount(2)
        ->and($errors[0]->field)->toBe('field_a')
        ->and($errors[1]->field)->toBe('field_b');
});

it('ValidationService returns empty array when no validators produce errors', function () {
    $validator = new class implements \App\Services\Validation\Validator {
        public function validate(ExtractedInvoice $invoice): array
        {
            return [];
        }
    };

    $service = new ValidationService([$validator]);
    $errors = $service->run(completeInvoice());

    expect($errors)->toBeEmpty();
});

it('ValidationService works with an empty validator list', function () {
    $service = new ValidationService([]);
    $errors = $service->run(completeInvoice());

    expect($errors)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// RequiredFieldsValidator — complete invoice
// ---------------------------------------------------------------------------

it('RequiredFieldsValidator returns empty array for a complete invoice', function () {
    $validator = new RequiredFieldsValidator();
    $errors = $validator->validate(completeInvoice());

    expect($errors)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// RequiredFieldsValidator — missing ABN
// ---------------------------------------------------------------------------

it('RequiredFieldsValidator returns exactly one error when ABN is missing', function () {
    $validator = new RequiredFieldsValidator();
    $errors = $validator->validate(invoiceMissingAbn());

    expect($errors)->toHaveCount(1)
        ->and($errors[0]->field)->toBe('abn');
});

it('RequiredFieldsValidator sets severity to error for missing ABN', function () {
    $validator = new RequiredFieldsValidator();
    $errors = $validator->validate(invoiceMissingAbn());

    expect($errors[0]->severity)->toBe('error');
});

// ---------------------------------------------------------------------------
// RequiredFieldsValidator — all mandatory fields
// ---------------------------------------------------------------------------

it('RequiredFieldsValidator detects all seven mandatory fields missing on empty invoice', function () {
    $validator = new RequiredFieldsValidator();
    $errors = $validator->validate(emptyInvoice());

    $fields = array_map(fn (ValidationError $e) => $e->field, $errors);

    // All seven mandatory fields must be flagged
    expect($fields)->toContain('supplier')
        ->toContain('abn')
        ->toContain('invoiceDate')
        ->toContain('lineItems')
        ->toContain('subtotal')
        ->toContain('gst')
        ->toContain('total')
        ->and(count($errors))->toBe(7);
});

it('RequiredFieldsValidator returns ValidationError instances', function () {
    $validator = new RequiredFieldsValidator();
    $errors = $validator->validate(invoiceMissingAbn());

    expect($errors[0])->toBeInstanceOf(ValidationError::class);
});

// ---------------------------------------------------------------------------
// RequiredFieldsValidator — via ValidationService (integration)
// ---------------------------------------------------------------------------

it('ValidationService with RequiredFieldsValidator returns one error for missing-ABN invoice', function () {
    $service = new ValidationService([new RequiredFieldsValidator()]);
    $errors = $service->run(invoiceMissingAbn());

    expect($errors)->toHaveCount(1)
        ->and($errors[0]->field)->toBe('abn')
        ->and($errors[0]->severity)->toBe('error');
});

it('ValidationService with RequiredFieldsValidator returns no errors for complete invoice', function () {
    $service = new ValidationService([new RequiredFieldsValidator()]);
    $errors = $service->run(completeInvoice());

    expect($errors)->toBeEmpty();
});
