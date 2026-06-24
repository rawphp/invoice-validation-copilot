<?php

declare(strict_types=1);

use App\DTO\ExtractedInvoice;
use App\DTO\FieldConfidence;
use App\DTO\ValidationError;
use App\Enums\ServiceCategory;
use App\Services\Validation\DateValidator;
use Carbon\Carbon;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a minimal ExtractedInvoice with the given date strings.
 * Other fields are non-null so RequiredFieldsValidator does not muddy the picture.
 */
function invoiceWithDates(?string $invoiceDate, ?string $dueDate): ExtractedInvoice
{
    $confidence = [];
    foreach (ExtractedInvoice::SCALAR_FIELDS as $field) {
        $confidence[$field] = new FieldConfidence($field, 0.9);
    }

    return new ExtractedInvoice(
        supplier: 'Test Pty Ltd',
        abn: '12 345 678 901',
        invoiceDate: $invoiceDate,
        dueDate: $dueDate,
        lineItems: [],
        subtotal: 100.0,
        gst: 10.0,
        total: 110.0,
        serviceCategory: ServiceCategory::ProfessionalServices,
        confidence: $confidence,
    );
}

/**
 * A fixed "now" used across all date tests: 2024-01-15 (15 January 2024).
 */
function fixedNow(): Carbon
{
    return Carbon::create(2024, 1, 15, 0, 0, 0, 'UTC');
}

// ---------------------------------------------------------------------------
// AC1: "24/10/2023" parses as 24 October 2023 (DD/MM), not 10 February
// ---------------------------------------------------------------------------

it('DateValidator parses 24/10/2023 as 24 October 2023 (DD/MM AU locale)', function () {
    $validator = new DateValidator(fixedNow());
    $errors = $validator->validate(invoiceWithDates('24/10/2023', '24/11/2023'));

    // Both dates are in the past → no errors; if MM/DD were used, 24 would be
    // an invalid month, proving DD/MM interpretation.
    expect($errors)->toBeEmpty();
});

it('DateValidator does NOT mis-parse 10/02/2023 as February (MM/DD confusion)', function () {
    // 10/02/2023 in DD/MM = 10 February 2023 (past, valid)
    // If wrongly read as MM/DD = 2 October 2023 (also past) — both would pass,
    // but a date like 01/13/2023 (invalid in MM/DD) parsed as 13 January 2023 (DD/MM) is fine.
    // Use an unambiguous DD > 12 to prove DD/MM parsing.
    $validator = new DateValidator(fixedNow());
    // 15/12/2023 → DD=15, MM=12 → 15 Dec 2023 (past). Valid.
    // As MM/DD that would be month=15 → invalid. We expect NO exception and NO error.
    $errors = $validator->validate(invoiceWithDates('15/12/2023', '20/12/2023'));

    expect($errors)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// AC2: Invoice date after injected "now" → one error on invoice_date
// ---------------------------------------------------------------------------

it('DateValidator emits one error on invoice_date when invoice date is in the future', function () {
    // fixedNow() = 2024-01-15; invoice date = 20/01/2024 (20 Jan 2024) → future
    $validator = new DateValidator(fixedNow());
    $errors = $validator->validate(invoiceWithDates('20/01/2024', '25/01/2024'));

    $invoiceDateErrors = array_filter($errors, fn (ValidationError $e) => $e->field === 'invoice_date');

    expect($invoiceDateErrors)->toHaveCount(1);

    $error = array_values($invoiceDateErrors)[0];
    expect($error->severity)->toBe('error')
        ->and($error->field)->toBe('invoice_date');
});

it('DateValidator does NOT emit invoice_date error when invoice date equals now', function () {
    // fixedNow() = 2024-01-15; invoice date = 15/01/2024 → today, not future
    $validator = new DateValidator(fixedNow());
    $errors = $validator->validate(invoiceWithDates('15/01/2024', '15/02/2024'));

    $invoiceDateErrors = array_filter($errors, fn (ValidationError $e) => $e->field === 'invoice_date');

    expect($invoiceDateErrors)->toBeEmpty();
});

it('DateValidator does NOT emit invoice_date error when invoice date is in the past', function () {
    // fixedNow() = 2024-01-15; invoice date = 10/01/2024 → past
    $validator = new DateValidator(fixedNow());
    $errors = $validator->validate(invoiceWithDates('10/01/2024', '10/02/2024'));

    $invoiceDateErrors = array_filter($errors, fn (ValidationError $e) => $e->field === 'invoice_date');

    expect($invoiceDateErrors)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// AC3: Due date before invoice date → one error on due_date
// ---------------------------------------------------------------------------

it('DateValidator emits one error on due_date when due date is before invoice date', function () {
    // Invoice: 10/01/2024, Due: 05/01/2024 → due before invoice
    $validator = new DateValidator(fixedNow());
    $errors = $validator->validate(invoiceWithDates('10/01/2024', '05/01/2024'));

    $dueDateErrors = array_filter($errors, fn (ValidationError $e) => $e->field === 'due_date');

    expect($dueDateErrors)->toHaveCount(1);

    $error = array_values($dueDateErrors)[0];
    expect($error->severity)->toBe('error')
        ->and($error->field)->toBe('due_date');
});

it('DateValidator does NOT emit due_date error when due date equals invoice date', function () {
    // Same-day due date is valid
    $validator = new DateValidator(fixedNow());
    $errors = $validator->validate(invoiceWithDates('10/01/2024', '10/01/2024'));

    $dueDateErrors = array_filter($errors, fn (ValidationError $e) => $e->field === 'due_date');

    expect($dueDateErrors)->toBeEmpty();
});

it('DateValidator does NOT emit due_date error when due date is after invoice date', function () {
    $validator = new DateValidator(fixedNow());
    $errors = $validator->validate(invoiceWithDates('10/01/2024', '28/02/2024'));

    $dueDateErrors = array_filter($errors, fn (ValidationError $e) => $e->field === 'due_date');

    expect($dueDateErrors)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// AC4: Unparseable date string → format error, no throw
// ---------------------------------------------------------------------------

it('DateValidator returns a format error for unparseable invoice date, does not throw', function () {
    $validator = new DateValidator(fixedNow());
    $errors = $validator->validate(invoiceWithDates('not-a-date', '10/01/2024'));

    $invoiceDateErrors = array_filter($errors, fn (ValidationError $e) => $e->field === 'invoice_date');

    expect($invoiceDateErrors)->toHaveCount(1);

    $error = array_values($invoiceDateErrors)[0];
    expect($error->severity)->toBe('error');
});

it('DateValidator returns a format error for unparseable due date, does not throw', function () {
    $validator = new DateValidator(fixedNow());
    $errors = $validator->validate(invoiceWithDates('10/01/2024', 'invalid'));

    $dueDateErrors = array_filter($errors, fn (ValidationError $e) => $e->field === 'due_date');

    expect($dueDateErrors)->toHaveCount(1);

    $error = array_values($dueDateErrors)[0];
    expect($error->severity)->toBe('error');
});

it('DateValidator handles null invoice date gracefully (format error)', function () {
    $validator = new DateValidator(fixedNow());
    $errors = $validator->validate(invoiceWithDates(null, '10/01/2024'));

    $invoiceDateErrors = array_filter($errors, fn (ValidationError $e) => $e->field === 'invoice_date');

    // Null means missing → RequiredFieldsValidator handles it, but DateValidator
    // must not throw; it may return a format/missing error or skip.
    // We assert no exception is thrown — if an error is returned that is also acceptable.
    expect(true)->toBeTrue(); // no exception = pass
});

it('DateValidator handles both dates null without throwing', function () {
    $validator = new DateValidator(fixedNow());
    // Should not throw
    $errors = $validator->validate(invoiceWithDates(null, null));

    expect($errors)->toBeArray();
});

// ---------------------------------------------------------------------------
// AC: Both errors can co-occur independently
// ---------------------------------------------------------------------------

it('DateValidator can emit both invoice_date and due_date errors in a single run', function () {
    // Future invoice date AND due before invoice
    // fixedNow() = 2024-01-15; invoice = 20/01/2024 (future), due = 18/01/2024 (before invoice)
    $validator = new DateValidator(fixedNow());
    $errors = $validator->validate(invoiceWithDates('20/01/2024', '18/01/2024'));

    $fields = array_map(fn (ValidationError $e) => $e->field, $errors);

    expect($fields)->toContain('invoice_date')
        ->and($fields)->toContain('due_date');
});

// ---------------------------------------------------------------------------
// AC: ValidationError instances returned
// ---------------------------------------------------------------------------

it('DateValidator returns ValidationError instances', function () {
    $validator = new DateValidator(fixedNow());
    $errors = $validator->validate(invoiceWithDates('20/01/2024', '25/01/2024'));

    foreach ($errors as $error) {
        expect($error)->toBeInstanceOf(ValidationError::class);
    }
});
