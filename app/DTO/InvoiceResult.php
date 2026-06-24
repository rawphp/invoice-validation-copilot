<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\ServiceCategory;
use JsonSerializable;

/**
 * The aggregate result of one end-to-end pipeline run over an uploaded invoice.
 *
 * Bundles everything the result page (REQ-017) renders: the structured
 * extraction, the deterministic validation findings, the overall + per-field
 * confidence, the classified service category, the plain-English explanation,
 * and the per-step audit trail.
 *
 * Immutable and JSON-serializable so Inertia can hand the whole payload to the
 * frontend and the result page can show structured JSON.
 *
 * An {@see self::error()} state carries a friendly, user-facing message instead
 * of a populated result — produced when the pipeline (e.g. a Claude/API call)
 * fails, so the user never sees a 500 / stack trace.
 */
final readonly class InvoiceResult implements JsonSerializable
{
    /**
     * @param  list<ValidationError>  $errors  Deterministic validation findings.
     * @param  array<string, FieldConfidence>  $fieldConfidence  Per-field confidence, keyed by scalar field name.
     * @param  array<int, array<string, mixed>>  $auditEntries  Per-step audit trail (insertion order).
     */
    public function __construct(
        public ?ExtractedInvoice $invoice,
        public array $errors,
        public ?ConfidenceResult $confidence,
        public array $fieldConfidence,
        public ?ServiceCategory $category,
        public ?string $explanation,
        public array $auditEntries,
        public bool $hasError = false,
        public ?string $errorMessage = null,
    ) {}

    /**
     * Build a populated, successful result from the pipeline outputs.
     *
     * @param  list<ValidationError>  $errors
     * @param  array<int, array<string, mixed>>  $auditEntries
     */
    public static function success(
        ExtractedInvoice $invoice,
        array $errors,
        ConfidenceResult $confidence,
        string $explanation,
        array $auditEntries,
    ): self {
        return new self(
            invoice: $invoice,
            errors: $errors,
            confidence: $confidence,
            fieldConfidence: $invoice->confidence,
            category: $invoice->serviceCategory,
            explanation: $explanation,
            auditEntries: $auditEntries,
            hasError: false,
            errorMessage: null,
        );
    }

    /**
     * Build a friendly error state — shown when the pipeline fails (e.g. the
     * Claude API is unreachable). Never surfaces a stack trace to the user.
     *
     * @param  array<int, array<string, mixed>>  $auditEntries
     */
    public static function error(string $message, array $auditEntries = []): self
    {
        return new self(
            invoice: null,
            errors: [],
            confidence: null,
            fieldConfidence: [],
            category: null,
            explanation: null,
            auditEntries: $auditEntries,
            hasError: true,
            errorMessage: $message,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'has_error' => $this->hasError,
            'error_message' => $this->errorMessage,
            'invoice' => $this->invoice === null ? null : $this->invoiceToArray($this->invoice),
            'errors' => array_map(
                static fn (ValidationError $e): array => $e->jsonSerialize(),
                $this->errors,
            ),
            'confidence' => $this->confidence === null ? null : [
                'overall' => $this->confidence->overall,
                'passed' => $this->confidence->passed,
            ],
            'field_confidence' => $this->fieldConfidenceToArray(),
            'category' => $this->category === null ? null : [
                'value' => $this->category->value,
                'label' => $this->category->label(),
            ],
            'explanation' => $this->explanation,
            'audit_entries' => $this->auditEntries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceToArray(ExtractedInvoice $invoice): array
    {
        return [
            'supplier' => $invoice->supplier,
            'abn' => $invoice->abn,
            'invoice_date' => $invoice->invoiceDate,
            'due_date' => $invoice->dueDate,
            'line_items' => array_map(
                static fn ($item): array => [
                    'description' => $item->description,
                    'qty' => $item->qty,
                    'rate' => $item->rate,
                    'amount' => $item->amount,
                ],
                $invoice->lineItems,
            ),
            'subtotal' => $invoice->subtotal,
            'gst' => $invoice->gst,
            'total' => $invoice->total,
            'service_category' => $invoice->serviceCategory->value,
        ];
    }

    /**
     * @return array<string, array{field: string, score: float}>
     */
    private function fieldConfidenceToArray(): array
    {
        $map = [];

        foreach ($this->fieldConfidence as $key => $fc) {
            $map[$key] = [
                'field' => $fc->field,
                'score' => $fc->score,
            ];
        }

        return $map;
    }
}
