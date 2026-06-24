<?php

declare(strict_types=1);

use App\DTO\ExtractedInvoice;
use App\DTO\FieldConfidence;
use App\DTO\LineItem;
use App\Enums\ServiceCategory;
use App\Services\Claude\DocumentBlock;
use App\Services\Invoice\ExtractionService;
use Tests\Fakes\CannedClaudeClient;

/** A realistic AU invoice tool-call payload. */
function cannedInvoicePayload(): array
{
    return [
        'supplier' => 'Acme Plumbing Pty Ltd',
        'abn' => '51 824 753 556',
        // Source on the invoice read 03/04/2026 (3 April), returned as ISO.
        'invoice_date' => '2026-04-03',
        'due_date' => '2026-05-03',
        'line_items' => [
            ['description' => 'Hot water unit replacement', 'qty' => 1, 'rate' => 1200.00, 'amount' => 1200.00],
            ['description' => 'Labour (3 hrs)', 'qty' => 3, 'rate' => 110.00, 'amount' => 330.00],
        ],
        'subtotal' => 1530.00,
        'gst' => 153.00,
        'total' => 1683.00,
        'service_category' => 'construction_trades',
        'confidence' => [
            'supplier' => 0.98,
            'abn' => 0.91,
            'invoice_date' => 0.95,
            'due_date' => 0.88,
            'subtotal' => 0.99,
            'gst' => 0.97,
            'total' => 0.99,
        ],
    ];
}

it('forces the record_invoice tool and wraps the image payload as a document block', function () {
    $fake = new CannedClaudeClient(cannedInvoicePayload());
    $service = new ExtractionService($fake);

    $service->extract(['mime' => 'image/jpeg', 'content' => 'YmFzZTY0', 'kind' => 'image']);

    // Tool is composed by the caller (per REQ-006 decisions note) and forced.
    expect($fake->lastTool)->not->toBeNull();
    expect($fake->lastTool['name'])->toBe('record_invoice');
    expect($fake->lastTool)->toHaveKeys(['name', 'description', 'input_schema']);
    expect($fake->lastToolChoice)->toBe('record_invoice');

    // FileIntake payload became a single image DocumentBlock.
    expect($fake->lastDocuments)->toHaveCount(1);
    expect($fake->lastDocuments[0])->toBeInstanceOf(DocumentBlock::class);
    expect($fake->lastDocuments[0]->kind)->toBe('image');
    expect($fake->lastDocuments[0]->mediaType)->toBe('image/jpeg');
    expect($fake->lastDocuments[0]->data)->toBe('YmFzZTY0');
});

it('wraps a pdf payload as a pdf document block', function () {
    $fake = new CannedClaudeClient(cannedInvoicePayload());
    $service = new ExtractionService($fake);

    $service->extract(['mime' => 'application/pdf', 'content' => 'cGRm', 'kind' => 'pdf']);

    expect($fake->lastDocuments[0]->kind)->toBe('pdf');
    expect($fake->lastDocuments[0]->mediaType)->toBe('application/pdf');
});

it('pins the prompt to AU locale (DD/MM dates and AUD amounts)', function () {
    $fake = new CannedClaudeClient(cannedInvoicePayload());
    $service = new ExtractionService($fake);

    $service->extract(['mime' => 'image/jpeg', 'content' => 'x', 'kind' => 'image']);

    expect($fake->lastPrompt)->toContain('DD/MM/YYYY');
    expect($fake->lastPrompt)->toContain('AUD');
    expect($fake->lastSystem)->toContain('DD/MM/YYYY');
    expect($fake->lastSystem)->toContain('AUD');
});

it('maps the tool-call payload into a typed ExtractedInvoice', function () {
    $fake = new CannedClaudeClient(cannedInvoicePayload());
    $service = new ExtractionService($fake);

    $invoice = $service->extract(['mime' => 'image/jpeg', 'content' => 'x', 'kind' => 'image']);

    expect($invoice)->toBeInstanceOf(ExtractedInvoice::class);
    expect($invoice->supplier)->toBe('Acme Plumbing Pty Ltd');
    expect($invoice->abn)->toBe('51 824 753 556');
    // AU date interpretation preserved as ISO: 3 April, not 4 March.
    expect($invoice->invoiceDate)->toBe('2026-04-03');
    expect($invoice->dueDate)->toBe('2026-05-03');
    expect($invoice->subtotal)->toBe(1530.00);
    expect($invoice->gst)->toBe(153.00);
    expect($invoice->total)->toBe(1683.00);
});

it('types line items as LineItem objects with numeric fields', function () {
    $fake = new CannedClaudeClient(cannedInvoicePayload());
    $service = new ExtractionService($fake);

    $invoice = $service->extract(['mime' => 'image/jpeg', 'content' => 'x', 'kind' => 'image']);

    expect($invoice->lineItems)->toHaveCount(2);
    expect($invoice->lineItems[0])->toBeInstanceOf(LineItem::class);
    expect($invoice->lineItems[0]->description)->toBe('Hot water unit replacement');
    expect($invoice->lineItems[0]->qty)->toBe(1.0);
    expect($invoice->lineItems[0]->rate)->toBe(1200.00);
    expect($invoice->lineItems[0]->amount)->toBe(1200.00);
    expect($invoice->lineItems[1]->amount)->toBe(330.00);
});

it('carries a FieldConfidence per scalar field in 0..1', function () {
    $fake = new CannedClaudeClient(cannedInvoicePayload());
    $service = new ExtractionService($fake);

    $invoice = $service->extract(['mime' => 'image/jpeg', 'content' => 'x', 'kind' => 'image']);

    foreach (ExtractedInvoice::SCALAR_FIELDS as $field) {
        $fc = $invoice->confidenceFor($field);
        expect($fc)->toBeInstanceOf(FieldConfidence::class);
        expect($fc->field)->toBe($field);
        expect($fc->score)->toBeGreaterThanOrEqual(0.0);
        expect($fc->score)->toBeLessThanOrEqual(1.0);
    }

    expect($invoice->confidenceFor('supplier')->score)->toBe(0.98);
    expect($invoice->confidenceFor('total')->score)->toBe(0.99);
});

it('classifies into a valid ServiceCategory case', function () {
    $fake = new CannedClaudeClient(cannedInvoicePayload());
    $service = new ExtractionService($fake);

    $invoice = $service->extract(['mime' => 'image/jpeg', 'content' => 'x', 'kind' => 'image']);

    expect($invoice->serviceCategory)->toBeInstanceOf(ServiceCategory::class);
    expect($invoice->serviceCategory)->toBe(ServiceCategory::ConstructionTrades);
    expect($invoice->serviceCategory->label())->toBe('Construction & Trades');
});

it('maps a non-invoice document (no tool call) to an empty low-confidence invoice without throwing', function () {
    $fake = new CannedClaudeClient(null); // model returns text, no tool_use
    $service = new ExtractionService($fake);

    $invoice = $service->extract(['mime' => 'image/jpeg', 'content' => 'x', 'kind' => 'image']);

    expect($invoice)->toBeInstanceOf(ExtractedInvoice::class);
    expect($invoice->supplier)->toBeNull();
    expect($invoice->total)->toBeNull();
    expect($invoice->lineItems)->toBe([]);
    expect($invoice->serviceCategory)->toBe(ServiceCategory::Other);
    expect($invoice->isLowConfidence())->toBeTrue();
    foreach (ExtractedInvoice::SCALAR_FIELDS as $field) {
        expect($invoice->confidenceFor($field)->score)->toBe(0.0);
    }
});

it('degrades a non-invoice tool call (blank fields, zero confidence) without throwing', function () {
    $fake = new CannedClaudeClient([
        'supplier' => '',
        'service_category' => 'other',
        'confidence' => [
            'supplier' => 0, 'abn' => 0, 'invoice_date' => 0, 'due_date' => 0,
            'subtotal' => 0, 'gst' => 0, 'total' => 0,
        ],
    ]);
    $service = new ExtractionService($fake);

    $invoice = $service->extract(['mime' => 'application/pdf', 'content' => 'x', 'kind' => 'pdf']);

    expect($invoice->supplier)->toBeNull();
    expect($invoice->lineItems)->toBe([]);
    expect($invoice->serviceCategory)->toBe(ServiceCategory::Other);
    expect($invoice->isLowConfidence())->toBeTrue();
});

it('falls back to Other for an unknown service_category value', function () {
    $payload = cannedInvoicePayload();
    $payload['service_category'] = 'time_travel';
    $fake = new CannedClaudeClient($payload);
    $service = new ExtractionService($fake);

    $invoice = $service->extract(['mime' => 'image/jpeg', 'content' => 'x', 'kind' => 'image']);

    expect($invoice->serviceCategory)->toBe(ServiceCategory::Other);
});
