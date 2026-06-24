<?php

declare(strict_types=1);

use App\Services\Claude\ClaudeClient;
use App\Services\Claude\ClaudeResponse;
use Illuminate\Http\UploadedFile;

/**
 * A fake ClaudeClient that never touches the network.
 *
 * It distinguishes the two pipeline calls by whether a tool schema is passed:
 *  - tool present  → the VISION extraction call → return a canned tool_use block.
 *  - tool null     → the explanation TEXT call → return canned text.
 *
 * Construct with $extraction to control the extracted fields, or set
 * $throwOnVision to simulate a Claude/API outage on the first call.
 */
final class PipelineFakeClaudeClient implements ClaudeClient
{
    public int $visionCalls = 0;

    public int $textCalls = 0;

    /**
     * @param  array<string, mixed>  $extraction  The tool input returned by the vision call.
     */
    public function __construct(
        private array $extraction,
        private readonly bool $throwOnVision = false,
        private readonly bool $throwOnText = false,
    ) {}

    public function message(
        string $prompt,
        array $documents = [],
        ?array $tool = null,
        ?string $toolChoice = null,
        ?string $system = null,
        int $maxTokens = 4096,
    ): ClaudeResponse {
        if ($tool !== null) {
            $this->visionCalls++;

            if ($this->throwOnVision) {
                throw new RuntimeException('Claude vision API is unreachable.');
            }

            return new ClaudeResponse(
                content: [[
                    'type' => 'tool_use',
                    'id' => 'toolu_fake',
                    'name' => $tool['name'],
                    'input' => $this->extraction,
                ]],
                inputTokens: 1200,
                outputTokens: 340,
                stopReason: 'tool_use',
                model: 'claude-fake-vision',
            );
        }

        $this->textCalls++;

        if ($this->throwOnText) {
            throw new RuntimeException('Claude text API is unreachable.');
        }

        return new ClaudeResponse(
            content: [[
                'type' => 'text',
                'text' => 'The invoice from Acme Pty Ltd looks complete and all checks passed.',
            ]],
            inputTokens: 210,
            outputTokens: 90,
            stopReason: 'end_turn',
            model: 'claude-fake-text',
        );
    }
}

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
