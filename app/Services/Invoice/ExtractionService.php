<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\DTO\ExtractedInvoice;
use App\DTO\FieldConfidence;
use App\DTO\LineItem;
use App\Enums\ServiceCategory;
use App\Services\Claude\ClaudeClient;
use App\Services\Claude\DocumentBlock;

/**
 * Extracts a structured, AU-locale invoice from a preprocessed file in ONE
 * Claude vision call.
 *
 * The file (a {@see FileIntake} payload: {mime, content, kind}) is wrapped as a
 * {@see DocumentBlock} and sent with a FORCED tool schema (`tool_choice` set to
 * the tool name) so Claude must emit a single structured `tool_use` block. That
 * tool output is mapped into a typed {@see ExtractedInvoice} carrying per-field
 * confidence and the {@see ServiceCategory} classified in the same call.
 *
 * Per decisions.md (REQ-006): the ClaudeClient takes the tool as an untyped
 * array, so THIS caller owns composing the correct Anthropic tool shape
 * (name / description / input_schema).
 *
 * A non-invoice (or unreadable) document degrades to an empty / low-confidence
 * ExtractedInvoice — it never throws.
 */
final class ExtractionService
{
    private const TOOL_NAME = 'record_invoice';

    /** Tokens budget for the structured tool output. */
    private const MAX_TOKENS = 4096;

    public function __construct(
        private readonly ClaudeClient $claude,
    ) {}

    /**
     * @param  array{mime: string, content: string, kind: 'image'|'pdf'}  $file
     *                                                                           The normalised payload from {@see FileIntake::process()}.
     */
    public function extract(array $file): ExtractedInvoice
    {
        $document = $this->toDocumentBlock($file);

        $response = $this->claude->message(
            prompt: $this->prompt(),
            documents: [$document],
            tool: $this->tool(),
            toolChoice: self::TOOL_NAME,
            system: $this->system(),
            maxTokens: self::MAX_TOKENS,
        );

        $input = $response->toolInput();

        // No tool call (e.g. a non-invoice document the model declined to fill)
        // → empty, zero-confidence result rather than an exception.
        if ($input === null) {
            return ExtractedInvoice::empty();
        }

        return $this->mapToInvoice($input);
    }

    /**
     * Wrap the FileIntake payload as the correct image/pdf document block.
     *
     * @param  array{mime: string, content: string, kind: 'image'|'pdf'}  $file
     */
    private function toDocumentBlock(array $file): DocumentBlock
    {
        if (($file['kind'] ?? null) === 'pdf') {
            return DocumentBlock::pdf($file['content']);
        }

        return DocumentBlock::image($file['content'], $file['mime']);
    }

    /**
     * Map the raw forced tool-call input into a typed ExtractedInvoice.
     *
     * @param  array<string, mixed>  $input
     */
    private function mapToInvoice(array $input): ExtractedInvoice
    {
        $lineItems = [];
        if (isset($input['line_items']) && is_array($input['line_items'])) {
            foreach ($input['line_items'] as $rawItem) {
                if (is_array($rawItem)) {
                    $lineItems[] = LineItem::fromArray($rawItem);
                }
            }
        }

        $rawConfidence = (isset($input['confidence']) && is_array($input['confidence']))
            ? $input['confidence']
            : [];

        $confidence = [];
        foreach (ExtractedInvoice::SCALAR_FIELDS as $field) {
            $confidence[$field] = FieldConfidence::make($field, $rawConfidence[$field] ?? null);
        }

        return new ExtractedInvoice(
            supplier: $this->str($input['supplier'] ?? null),
            abn: $this->str($input['abn'] ?? null),
            invoiceDate: $this->str($input['invoice_date'] ?? null),
            dueDate: $this->str($input['due_date'] ?? null),
            lineItems: $lineItems,
            subtotal: $this->num($input['subtotal'] ?? null),
            gst: $this->num($input['gst'] ?? null),
            total: $this->num($input['total'] ?? null),
            serviceCategory: ServiceCategory::fromModel($input['service_category'] ?? null),
            confidence: $confidence,
        );
    }

    private function str(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function num(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function system(): string
    {
        return 'You are an expert at reading Australian supplier invoices. '
            .'Interpret every date in Australian locale: DD/MM/YYYY (day first, NOT month first). '
            .'All monetary amounts are in Australian Dollars (AUD). '
            .'GST is the 10% Australian Goods and Services Tax.';
    }

    private function prompt(): string
    {
        return 'Read the attached document and extract the invoice using the '
            .'record_invoice tool. Australian locale rules apply:'."\n"
            .'- Dates are DD/MM/YYYY (e.g. 03/04/2026 is 3 April 2026). '
            .'Return invoice_date and due_date as ISO 8601 YYYY-MM-DD.'."\n"
            .'- Amounts are AUD; return numbers only, without currency symbols.'."\n"
            .'- Classify the supplier into one service_category.'."\n"
            .'- For each scalar field give a confidence between 0 and 1.'."\n"
            .'If the document is NOT an invoice, still call the tool but leave '
            .'fields blank/empty and set every confidence to 0.';
    }

    /**
     * Compose the Anthropic tool: name + description + input_schema. The caller
     * owns this shape (per REQ-006 decisions note).
     *
     * @return array{name: string, description: string, input_schema: array<string, mixed>}
     */
    private function tool(): array
    {
        $confidenceProps = [];
        foreach (ExtractedInvoice::SCALAR_FIELDS as $field) {
            $confidenceProps[$field] = [
                'type' => 'number',
                'minimum' => 0,
                'maximum' => 1,
                'description' => "Confidence (0-1) that the {$field} field was read correctly.",
            ];
        }

        return [
            'name' => self::TOOL_NAME,
            'description' => 'Record the structured fields of an Australian supplier invoice, '
                .'including a service-category classification and per-field confidence. '
                .'Call this even for non-invoice documents, with blank fields and zero confidence.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'supplier' => ['type' => 'string', 'description' => 'Supplier / vendor business name.'],
                    'abn' => ['type' => 'string', 'description' => 'Australian Business Number (ABN), digits only or formatted.'],
                    'invoice_date' => ['type' => 'string', 'description' => 'Invoice issue date as ISO 8601 YYYY-MM-DD (source is DD/MM/YYYY).'],
                    'due_date' => ['type' => 'string', 'description' => 'Payment due date as ISO 8601 YYYY-MM-DD (source is DD/MM/YYYY).'],
                    'line_items' => [
                        'type' => 'array',
                        'description' => 'Invoice line items.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'description' => ['type' => 'string'],
                                'qty' => ['type' => 'number'],
                                'rate' => ['type' => 'number', 'description' => 'Unit rate in AUD.'],
                                'amount' => ['type' => 'number', 'description' => 'Line total in AUD.'],
                            ],
                            'required' => ['description'],
                        ],
                    ],
                    'subtotal' => ['type' => 'number', 'description' => 'Subtotal before GST, in AUD.'],
                    'gst' => ['type' => 'number', 'description' => 'GST amount (10% AU tax), in AUD.'],
                    'total' => ['type' => 'number', 'description' => 'Grand total including GST, in AUD.'],
                    'service_category' => [
                        'type' => 'string',
                        'enum' => array_map(
                            static fn (ServiceCategory $c): string => $c->value,
                            ServiceCategory::cases(),
                        ),
                        'description' => 'The single best service category for this supplier.',
                    ],
                    'confidence' => [
                        'type' => 'object',
                        'description' => 'Per-field confidence (0-1) for each scalar field.',
                        'properties' => $confidenceProps,
                    ],
                ],
                'required' => ['service_category', 'confidence'],
            ],
        ];
    }
}
