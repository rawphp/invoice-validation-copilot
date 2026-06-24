<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Per-field extraction confidence: the model's self-reported certainty (0–1)
 * that a single scalar field was read correctly.
 *
 * A non-invoice document (or a field the model could not find) yields a low
 * score — the confidence scorer (REQ-013) and explanation step (REQ-014)
 * consume these to flag uncertain fields to the user.
 */
final readonly class FieldConfidence
{
    /**
     * @param  string  $field  The scalar field name (e.g. `supplier`, `total`).
     * @param  float  $score  Clamped to the [0, 1] range.
     */
    public function __construct(
        public string $field,
        public float $score,
    ) {}

    /**
     * Build a clamped FieldConfidence, defaulting to 0.0 when the model omitted
     * a score for the field.
     */
    public static function make(string $field, mixed $score): self
    {
        $value = is_numeric($score) ? (float) $score : 0.0;
        $clamped = max(0.0, min(1.0, $value));

        return new self($field, $clamped);
    }
}
