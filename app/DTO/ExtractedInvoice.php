<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\ServiceCategory;

/**
 * The structured result of a single Claude vision + tool-use extraction call.
 *
 * Every scalar field is paired with a {@see FieldConfidence} (keyed by field
 * name in {@see self::$confidence}) so downstream steps can reason about which
 * values to trust. Dates are AU-locale ISO strings (YYYY-MM-DD) once normalised
 * from the model's DD/MM/YYYY reading; amounts are AUD.
 *
 * This shape is STABLE — validators (REQ-009–012), the confidence scorer
 * (REQ-013), explanation (REQ-014), and the result page (REQ-017) all consume it.
 *
 * A non-invoice document maps to {@see self::empty()} (blank fields, zero
 * confidence, category Other) rather than throwing.
 */
final readonly class ExtractedInvoice
{
    /**
     * @param  list<LineItem>  $lineItems
     * @param  array<string, FieldConfidence>  $confidence  Keyed by scalar field name.
     */
    public function __construct(
        public ?string $supplier,
        public ?string $abn,
        public ?string $invoiceDate,
        public ?string $dueDate,
        public array $lineItems,
        public ?float $subtotal,
        public ?float $gst,
        public ?float $total,
        public ServiceCategory $serviceCategory,
        public array $confidence,
    ) {}

    /**
     * The scalar field names that carry a per-field confidence score.
     *
     * @var list<string>
     */
    public const SCALAR_FIELDS = [
        'supplier',
        'abn',
        'invoice_date',
        'due_date',
        'subtotal',
        'gst',
        'total',
    ];

    /**
     * An empty, zero-confidence invoice — the result for a non-invoice document.
     */
    public static function empty(): self
    {
        return new self(
            supplier: null,
            abn: null,
            invoiceDate: null,
            dueDate: null,
            lineItems: [],
            subtotal: null,
            gst: null,
            total: null,
            serviceCategory: ServiceCategory::Other,
            confidence: self::zeroConfidence(),
        );
    }

    /**
     * Confidence lookup for a scalar field; defaults to a zero score when absent.
     */
    public function confidenceFor(string $field): FieldConfidence
    {
        return $this->confidence[$field] ?? new FieldConfidence($field, 0.0);
    }

    /**
     * Whether this looks like a non-invoice / unreadable document: no money
     * fields and every scalar confidence at or below the floor.
     */
    public function isLowConfidence(float $floor = 0.2): bool
    {
        foreach ($this->confidence as $fc) {
            if ($fc->score > $floor) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, FieldConfidence>
     */
    private static function zeroConfidence(): array
    {
        $map = [];

        foreach (self::SCALAR_FIELDS as $field) {
            $map[$field] = new FieldConfidence($field, 0.0);
        }

        return $map;
    }
}
