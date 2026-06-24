<?php

declare(strict_types=1);

use App\Services\Claude\ClaudeClient;
use Illuminate\Http\UploadedFile;
use Tests\Fakes\PipelineFakeClaudeClient;

/** A valid AU invoice extraction (good ABN, correct arithmetic, past dates). */
function goodExtraction(): array
{
    return [
        'supplier' => 'Acme Pty Ltd',
        'abn' => '51824753556', // valid ATO checksum
        'invoice_date' => '2026-01-10',
        'due_date' => '2026-02-10',
        'line_items' => [
            ['description' => 'Consulting', 'qty' => 1, 'rate' => 1000.0, 'amount' => 1000.0],
        ],
        'subtotal' => 1000.0,
        'gst' => 100.0,
        'total' => 1100.0,
        'service_category' => 'professional_services',
        'confidence' => [
            'supplier' => 0.95, 'abn' => 0.9, 'invoice_date' => 0.92, 'due_date' => 0.9,
            'subtotal' => 0.97, 'gst' => 0.96, 'total' => 0.98,
        ],
    ];
}

/** A deliberately bad invoice: bad ABN, broken arithmetic, future invoice date. */
function badExtraction(): array
{
    return [
        'supplier' => 'Dodgy Co',
        'abn' => '11111111111', // fails ATO checksum
        'invoice_date' => '2099-12-31', // future
        'due_date' => '2099-11-01', // before invoice date
        'line_items' => [
            ['description' => 'Stuff', 'qty' => 1, 'rate' => 1000.0, 'amount' => 1000.0],
        ],
        'subtotal' => 1000.0,
        'gst' => 500.0,  // not 10%
        'total' => 9999.0, // != subtotal + gst
        'service_category' => 'other',
        'confidence' => [
            'supplier' => 0.8, 'abn' => 0.7, 'invoice_date' => 0.6, 'due_date' => 0.6,
            'subtotal' => 0.9, 'gst' => 0.5, 'total' => 0.4,
        ],
    ];
}

function fakeUpload(): UploadedFile
{
    return UploadedFile::fake()->image('invoice.jpg', 800, 1000);
}

function bindFakeClaude(PipelineFakeClaudeClient $fake): void
{
    app()->instance(ClaudeClient::class, $fake);
}

beforeEach(function () {
    // The real Upload.vue / Result.vue pages are delivered by REQ-004 / REQ-017.
    // Assert the Inertia component by NAME without requiring the .vue file yet.
    config()->set('inertia.testing.ensure_pages_exist', false);
});

it('renders the Upload page at / with a 200 response', function () {
    $this->get('/')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Upload'));
});

it('runs the pipeline and returns the full result payload', function () {
    bindFakeClaude(new PipelineFakeClaudeClient(goodExtraction()));

    $this->post('/validate', ['file' => fakeUpload()])
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Result')
            ->where('result.has_error', false)
            ->where('result.invoice.supplier', 'Acme Pty Ltd')
            ->where('result.invoice.abn', '51824753556')
            ->where('result.category.value', 'professional_services')
            ->has('result.field_confidence.supplier')
            ->has('result.field_confidence.total')
            ->where('result.confidence.overall', fn ($v) => $v > 0)
            ->has('result.confidence.passed')
            ->has('result.errors')
            ->where('result.explanation', fn ($v) => is_string($v) && $v !== '')
            ->has('result.audit_entries')
        );
});

it('appends an audit entry per step in pipeline order', function () {
    bindFakeClaude(new PipelineFakeClaudeClient(goodExtraction()));

    $this->post('/validate', ['file' => fakeUpload()])
        ->assertInertia(fn ($page) => $page
            ->where('result.audit_entries', function ($entries) {
                $steps = array_map(fn ($e) => $e['step'], $entries->all());

                expect($steps)->toBe(['intake', 'extract', 'validate', 'score', 'explain']);

                // The two LLM steps carry model id + token usage.
                $extract = $entries->firstWhere('step', 'extract');
                $explain = $entries->firstWhere('step', 'explain');

                expect($extract['model_id'])->toBe('claude-fake-vision');
                expect($extract['input_tokens'])->toBe(1200);
                expect($extract['output_tokens'])->toBe(340);
                expect($explain['model_id'])->toBe('claude-fake-text');

                return true;
            })
        );
});

it('surfaces ABN, arithmetic, and date errors together for a bad invoice', function () {
    bindFakeClaude(new PipelineFakeClaudeClient(badExtraction()));

    $this->post('/validate', ['file' => fakeUpload()])
        ->assertInertia(fn ($page) => $page
            ->where('result.has_error', false)
            ->where('result.errors', function ($errors) {
                $fields = array_map(fn ($e) => $e['field'], $errors->all());

                // ABN checksum failure.
                expect($fields)->toContain('abn');
                // Arithmetic failures (gst and/or total).
                expect(array_intersect(['gst', 'total'], $fields))->not->toBeEmpty();
                // Date failure (future invoice date or due-before-invoice).
                expect(array_intersect(['invoice_date', 'due_date'], $fields))->not->toBeEmpty();

                return true;
            })
        );
});

it('renders a friendly error (not a 500) when the Claude vision call throws', function () {
    bindFakeClaude(new PipelineFakeClaudeClient(goodExtraction(), throwOnVision: true));

    $this->post('/validate', ['file' => fakeUpload()])
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Result')
            ->where('result.has_error', true)
            ->where('result.error_message', fn ($v) => is_string($v) && $v !== '')
            ->where('result.invoice', null)
        );
});

it('redirects GET /validate to / with a 302 response', function () {
    $this->get('/validate')
        ->assertStatus(302)
        ->assertRedirect('/');
});

/**
 * The brief's payment-allocated invoice (UR-004):
 *   Supplier: Vista Lawn Care, ABN: 77651564117
 *   Subtotal: 357.96, GST: 35.79, Total: 18.00 (net balance after payment applied)
 *   Line items: service charges + late fees + credits + payment received = 18.00 net
 */
function paymentAllocatedExtraction(): array
{
    return [
        'supplier' => 'Vista Lawn Care',
        'abn' => '77651564117', // valid ATO checksum
        'invoice_date' => '2026-04-18',
        'due_date' => '2026-04-18',
        'line_items' => [
            ['description' => 'Ride-on Mowing - Professional ride-on mowing. Includes whipper snipping all boundaries', 'qty' => 1, 'rate' => 375.0, 'amount' => 375.0],
            ['description' => 'Fuel Surcharge - Temporary Fuel Fee', 'qty' => 1, 'rate' => 18.75, 'amount' => 18.75],
            ['description' => 'Late Payment Fee (25/04/2026)', 'qty' => null, 'rate' => null, 'amount' => 6.0],
            ['description' => 'Late Payment Fee (02/05/2026)', 'qty' => null, 'rate' => null, 'amount' => 6.0],
            ['description' => 'Late Payment Fee (09/05/2026)', 'qty' => null, 'rate' => null, 'amount' => 6.0],
            ['description' => 'Late Fee (16/05/2026)', 'qty' => null, 'rate' => null, 'amount' => 6.0],
            ['description' => 'Late Payment Credit (18/05/2026)', 'qty' => null, 'rate' => null, 'amount' => -6.0],
            ['description' => 'Payment received (15/05/2026)', 'qty' => null, 'rate' => null, 'amount' => -393.75],
        ],
        'subtotal' => 357.96,
        'gst' => 35.79,
        'total' => 18.00,
        'service_category' => 'landscaping',
        'confidence' => [
            'supplier' => 0.99, 'abn' => 0.98, 'invoice_date' => 0.97, 'due_date' => 0.97,
            'subtotal' => 0.98, 'gst' => 0.99, 'total' => 0.99,
        ],
    ];
}

it('REQ-024 AC1–AC4: payment-allocated invoice passes end-to-end with no critical total error and correct explanation framing', function () {
    bindFakeClaude(new PipelineFakeClaudeClient(paymentAllocatedExtraction()));

    $this->post('/validate', ['file' => fakeUpload()])
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Result')
            ->where('result.has_error', false)
            // AC1: no error-severity finding on the total field.
            ->where('result.errors', function ($errors) {
                $totalErrors = array_filter(
                    $errors->all(),
                    fn ($e) => $e['field'] === 'total' && $e['severity'] === 'error',
                );
                expect($totalErrors)->toBeEmpty('REQ-024 AC1: payment-allocated invoice must not produce an error-severity total finding');

                return true;
            })
            // AC2: exactly one info-severity finding referencing payments/credits applied.
            ->where('result.errors', function ($errors) {
                $totalInfos = array_values(array_filter(
                    $errors->all(),
                    fn ($e) => $e['field'] === 'total' && $e['severity'] === 'info',
                ));
                expect($totalInfos)->toHaveCount(1, 'REQ-024 AC2: exactly one info finding expected on the total field');
                expect($totalInfos[0]['message'])->toContain('payments');

                return true;
            })
            // AC3: the generated explanation does not contain supplier-fix framing.
            ->where('result.explanation', function ($explanation) {
                expect($explanation)->not->toContain('corrected invoice', 'REQ-024 AC3: explanation must not ask supplier for a corrected invoice');
                expect($explanation)->not->toContain('subtotal plus GST', 'REQ-024 AC3: explanation must not tell operator to ask supplier to fix subtotal+GST mismatch');

                return true;
            })
            // AC4: confidence is not failed on account of the info note (info is not a hard error).
            ->where('result.confidence.passed', true, 'REQ-024 AC4: confidence must pass because the info finding is not a hard error')
        );
});
