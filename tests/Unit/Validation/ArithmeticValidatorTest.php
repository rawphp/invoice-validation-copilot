<?php

declare(strict_types=1);

use App\DTO\ExtractedInvoice;
use App\DTO\FieldConfidence;
use App\DTO\LineItem;
use App\DTO\ValidationError;
use App\Enums\ServiceCategory;
use App\Services\Validation\ArithmeticValidator;
use App\Services\Validation\Validator;

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
    $validator = new ArithmeticValidator;
    $errors = $validator->validate(consistentInvoice());

    expect($errors)->toBeEmpty();
});

it('ArithmeticValidator returns one error on gst field when GST is off by $5 beyond tolerance', function () {
    $validator = new ArithmeticValidator;
    $errors = $validator->validate(invoiceWithWrongGst());

    // Only the GST field should be flagged
    $fields = array_map(fn (ValidationError $e) => $e->field, $errors);

    expect($fields)->toContain('gst');
    expect(count(array_filter($fields, fn ($f) => $f === 'gst')))->toBe(1);
});

it('ArithmeticValidator flags gst error with error severity', function () {
    $validator = new ArithmeticValidator;
    $errors = $validator->validate(invoiceWithWrongGst());

    $gstError = array_values(array_filter($errors, fn (ValidationError $e) => $e->field === 'gst'));

    expect($gstError)->toHaveCount(1)
        ->and($gstError[0]->severity)->toBe('error');
});

it('ArithmeticValidator returns one error on total field when subtotal+GST does not equal total', function () {
    $validator = new ArithmeticValidator;
    $errors = $validator->validate(invoiceWithWrongTotal());

    $fields = array_map(fn (ValidationError $e) => $e->field, $errors);

    expect($fields)->toContain('total');
    expect(count(array_filter($fields, fn ($f) => $f === 'total')))->toBe(1);
});

it('ArithmeticValidator flags total error with error severity', function () {
    $validator = new ArithmeticValidator;
    $errors = $validator->validate(invoiceWithWrongTotal());

    $totalError = array_values(array_filter($errors, fn (ValidationError $e) => $e->field === 'total'));

    expect($totalError)->toHaveCount(1)
        ->and($totalError[0]->severity)->toBe('error');
});

it('ArithmeticValidator returns no errors when discrepancy is within cent tolerance', function () {
    $validator = new ArithmeticValidator;
    $errors = $validator->validate(invoiceWithTinyRounding());

    expect($errors)->toBeEmpty();
});

it('ArithmeticValidator returns ValidationError instances', function () {
    $validator = new ArithmeticValidator;
    $errors = $validator->validate(invoiceWithWrongGst());

    foreach ($errors as $error) {
        expect($error)->toBeInstanceOf(ValidationError::class);
    }
});

it('ArithmeticValidator implements Validator contract', function () {
    $validator = new ArithmeticValidator;

    expect($validator)->toBeInstanceOf(Validator::class);
});

// ---------------------------------------------------------------------------
// REQ-019: GST-inclusive line items (check-a OR-logic)
// ---------------------------------------------------------------------------

/**
 * AC1: GST-inclusive invoice from the UR-002 brief.
 *   Line items: Mowing $55 + Edging $25 = $80.00 (GST-inclusive totals)
 *   Subtotal: $72.73 (ex-GST: 80 ÷ 1.1)
 *   GST:      $7.27  (80 ÷ 11)
 *   Total:    $80.00
 * Line items sum to total, not subtotal — validator must pass check (a).
 */
function gstInclusiveInvoice(): ExtractedInvoice
{
    return new ExtractedInvoice(
        supplier: 'Vista Lawn Care',
        abn: '77 651 564 117',
        invoiceDate: '2026-02-09',
        dueDate: '2026-02-09',
        lineItems: [
            new LineItem(description: 'Mowing', qty: 1.0, rate: 55.0, amount: 55.0),
            new LineItem(description: 'Edging', qty: 1.0, rate: 25.0, amount: 25.0),
        ],
        subtotal: 72.73,
        gst: 7.27,
        total: 80.00,
        serviceCategory: ServiceCategory::CleaningMaintenance,
        confidence: arithmeticConfidence(),
    );
}

/**
 * AC3: Line items reconcile against neither subtotal nor total — must error.
 *   lineSum 90.00 / subtotal 72.73 / total 80.00
 */
function gstInclusiveInvoiceWithWrongLineItems(): ExtractedInvoice
{
    return new ExtractedInvoice(
        supplier: 'Vista Lawn Care',
        abn: '77 651 564 117',
        invoiceDate: '2026-02-09',
        dueDate: '2026-02-09',
        lineItems: [
            new LineItem(description: 'Mowing', qty: 1.0, rate: 55.0, amount: 55.0),
            new LineItem(description: 'Edging', qty: 1.0, rate: 25.0, amount: 25.0),
            new LineItem(description: 'Extra work', qty: 1.0, rate: 10.0, amount: 10.0),
        ],
        subtotal: 72.73,
        gst: 7.27,
        total: 80.00,
        serviceCategory: ServiceCategory::CleaningMaintenance,
        confidence: arithmeticConfidence(),
    );
}

it('AC1: ArithmeticValidator returns zero subtotal errors for a GST-inclusive invoice (brief scenario)', function () {
    $validator = new ArithmeticValidator;
    $errors = $validator->validate(gstInclusiveInvoice());

    $subtotalErrors = array_values(array_filter($errors, fn (ValidationError $e) => $e->field === 'subtotal'));

    expect($subtotalErrors)->toBeEmpty();
});

it('AC2: ArithmeticValidator returns no subtotal error for a GST-exclusive invoice (no regression)', function () {
    $validator = new ArithmeticValidator;
    // consistentInvoice() is GST-exclusive: lineSum=300 == subtotal=300
    $errors = $validator->validate(consistentInvoice());

    $subtotalErrors = array_values(array_filter($errors, fn (ValidationError $e) => $e->field === 'subtotal'));

    expect($subtotalErrors)->toBeEmpty();
});

it('AC3: ArithmeticValidator emits exactly one subtotal error when line items reconcile against neither subtotal nor total', function () {
    $validator = new ArithmeticValidator;
    $errors = $validator->validate(gstInclusiveInvoiceWithWrongLineItems());

    $subtotalErrors = array_values(array_filter($errors, fn (ValidationError $e) => $e->field === 'subtotal'));

    expect($subtotalErrors)->toHaveCount(1)
        ->and($subtotalErrors[0]->severity)->toBe('error');
});

it('AC4: ArithmeticValidator accepts GST-inclusive line items within TOLERANCE_AUD of total', function () {
    // lineSum 80.01 vs total 80.00 — difference 0.01 is within TOLERANCE_AUD (0.02)
    $invoice = new ExtractedInvoice(
        supplier: 'Vista Lawn Care',
        abn: '77 651 564 117',
        invoiceDate: '2026-02-09',
        dueDate: '2026-02-09',
        lineItems: [
            new LineItem(description: 'Mowing', qty: 1.0, rate: 55.01, amount: 55.01),
            new LineItem(description: 'Edging', qty: 1.0, rate: 25.0, amount: 25.0),
        ],
        subtotal: 72.73,  // lineSum 80.01 ≠ subtotal 72.73 (diff 7.28 > tolerance)
        gst: 7.27,
        total: 80.00,     // lineSum 80.01 vs total 80.00 (diff 0.01 <= TOLERANCE_AUD 0.02)
        serviceCategory: ServiceCategory::CleaningMaintenance,
        confidence: arithmeticConfidence(),
    );

    $validator = new ArithmeticValidator;
    $errors = $validator->validate($invoice);

    $subtotalErrors = array_values(array_filter($errors, fn (ValidationError $e) => $e->field === 'subtotal'));

    expect($subtotalErrors)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// REQ-021: Payment-allocated invoice Total check (check-c OR-logic)
// ---------------------------------------------------------------------------

/**
 * The brief's payment-allocated invoice (UR-004):
 *   Line items: service charges + late fees + credits + payment = 18.00 net
 *   Subtotal: 357.96 (charges ex-GST)
 *   GST:      35.79  (10% of subtotal)
 *   Total:    18.00  (net balance due after payments/credits)
 *
 * Line items sum to Total (18.00), not subtotal+GST (393.75).
 * Check (c) must NOT emit an error; must emit one info finding instead.
 */
function paymentAllocatedInvoice(): ExtractedInvoice
{
    return new ExtractedInvoice(
        supplier: 'Vista Lawn Care',
        abn: '77 651 564 117',
        invoiceDate: '2026-04-18',
        dueDate: '2026-04-18',
        lineItems: [
            new LineItem(description: 'Ride-on Mowing', qty: 1.0, rate: 375.00, amount: 375.00),
            new LineItem(description: 'Fuel Surcharge', qty: 1.0, rate: 18.75, amount: 18.75),
            new LineItem(description: 'Late Payment Fee (25/04/2026)', qty: null, rate: null, amount: 6.00),
            new LineItem(description: 'Late Payment Fee (02/05/2026)', qty: null, rate: null, amount: 6.00),
            new LineItem(description: 'Late Payment Fee (09/05/2026)', qty: null, rate: null, amount: 6.00),
            new LineItem(description: 'Late Fee (16/05/2026)', qty: null, rate: null, amount: 6.00),
            new LineItem(description: 'Late Payment Credit (18/05/2026)', qty: null, rate: null, amount: -6.00),
            new LineItem(description: 'Payment received (15/05/2026)', qty: null, rate: null, amount: -393.75),
        ],
        subtotal: 357.96,
        gst: 35.79,
        total: 18.00,
        serviceCategory: ServiceCategory::CleaningMaintenance,
        confidence: arithmeticConfidence(),
    );
}

/**
 * Genuine total typo: line items reconcile to neither subtotal+GST nor the Total.
 *   lineSum: 50.00 / subtotal: 357.96 / GST: 35.79 / total: 18.00
 * Both subtotal+GST (393.75) and lineSum (50.00) differ from total (18.00).
 */
function paymentAllocatedInvoiceWithGenuineTypo(): ExtractedInvoice
{
    return new ExtractedInvoice(
        supplier: 'Vista Lawn Care',
        abn: '77 651 564 117',
        invoiceDate: '2026-04-18',
        dueDate: '2026-04-18',
        lineItems: [
            new LineItem(description: 'Some service', qty: 1.0, rate: 50.00, amount: 50.00),
        ],
        subtotal: 357.96,
        gst: 35.79,
        total: 18.00,  // total 18.00 ≠ lineSum 50.00 AND ≠ subtotal+GST 393.75 — genuine typo
        serviceCategory: ServiceCategory::CleaningMaintenance,
        confidence: arithmeticConfidence(),
    );
}

/**
 * Negative net total: credits/payments exceed charges.
 *   lineItems sum to -20.00 / total: -20.00 / subtotal+GST: 110.00
 * Line items corroborate total (abs matches within tolerance), so no error expected,
 * and one info finding should be emitted.
 */
function negativeNetTotalInvoice(): ExtractedInvoice
{
    return new ExtractedInvoice(
        supplier: 'Vista Lawn Care',
        abn: '77 651 564 117',
        invoiceDate: '2026-04-18',
        dueDate: '2026-04-18',
        lineItems: [
            new LineItem(description: 'Service', qty: 1.0, rate: 100.00, amount: 100.00),
            new LineItem(description: 'Overpayment refund', qty: null, rate: null, amount: -120.00),
        ],
        subtotal: 100.00,
        gst: 10.00,
        total: -20.00,
        serviceCategory: ServiceCategory::CleaningMaintenance,
        confidence: arithmeticConfidence(),
    );
}

it('REQ-021 AC1: payment-allocated invoice (UR-004 brief) produces zero error-severity total findings', function () {
    $validator = new ArithmeticValidator;
    $errors = $validator->validate(paymentAllocatedInvoice());

    $totalErrors = array_values(array_filter(
        $errors,
        fn (ValidationError $e) => $e->field === 'total' && $e->severity === 'error',
    ));

    expect($totalErrors)->toBeEmpty();
});

it('REQ-021 AC2: payment-allocated invoice produces exactly one info-severity total finding', function () {
    $validator = new ArithmeticValidator;
    $errors = $validator->validate(paymentAllocatedInvoice());

    $totalInfos = array_values(array_filter(
        $errors,
        fn (ValidationError $e) => $e->field === 'total' && $e->severity === 'info',
    ));

    expect($totalInfos)->toHaveCount(1);
    expect($totalInfos[0]->message)->toContain('payments');
});

it('REQ-021 AC3: genuine total typo still emits one error-severity total error and no info finding', function () {
    $validator = new ArithmeticValidator;
    $errors = $validator->validate(paymentAllocatedInvoiceWithGenuineTypo());

    $totalErrors = array_values(array_filter(
        $errors,
        fn (ValidationError $e) => $e->field === 'total' && $e->severity === 'error',
    ));
    $totalInfos = array_values(array_filter(
        $errors,
        fn (ValidationError $e) => $e->field === 'total' && $e->severity === 'info',
    ));

    expect($totalErrors)->toHaveCount(1);
    expect($totalInfos)->toBeEmpty();
});

it('REQ-021 AC4: charge-only invoice with subtotal+GST==total still passes check (c) with no total error and no info', function () {
    $validator = new ArithmeticValidator;
    // consistentInvoice(): subtotal=300, gst=30, total=330; lineSum=300=subtotal (charge-only)
    $errors = $validator->validate(consistentInvoice());

    $totalFindings = array_values(array_filter(
        $errors,
        fn (ValidationError $e) => $e->field === 'total',
    ));

    expect($totalFindings)->toBeEmpty();
});

it('REQ-021 AC5: invoice with no line items and subtotal+GST!=total still emits error-severity total error', function () {
    $invoice = new ExtractedInvoice(
        supplier: 'Acme Pty Ltd',
        abn: '12 345 678 901',
        invoiceDate: '2024-07-15',
        dueDate: '2024-08-15',
        lineItems: [],
        subtotal: 300.0,
        gst: 30.0,
        total: 999.0,  // wrong total, no line items to corroborate
        serviceCategory: ServiceCategory::ProfessionalServices,
        confidence: arithmeticConfidence(),
    );

    $validator = new ArithmeticValidator;
    $errors = $validator->validate($invoice);

    $totalErrors = array_values(array_filter(
        $errors,
        fn (ValidationError $e) => $e->field === 'total' && $e->severity === 'error',
    ));

    expect($totalErrors)->toHaveCount(1);
});

it('REQ-021 AC6: negative net total with line items corroborating produces no error and one info finding', function () {
    $validator = new ArithmeticValidator;
    $errors = $validator->validate(negativeNetTotalInvoice());

    $totalErrors = array_values(array_filter(
        $errors,
        fn (ValidationError $e) => $e->field === 'total' && $e->severity === 'error',
    ));
    $totalInfos = array_values(array_filter(
        $errors,
        fn (ValidationError $e) => $e->field === 'total' && $e->severity === 'info',
    ));

    expect($totalErrors)->toBeEmpty();
    expect($totalInfos)->toHaveCount(1);
});
