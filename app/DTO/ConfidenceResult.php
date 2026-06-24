<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * The typed result of the {@see \App\Services\Invoice\ConfidenceScorer}.
 *
 * Immutable value object. {@see self::$overall} is the aggregate confidence
 * score in [0, 1]. {@see self::$passed} reflects the final verdict: the
 * invoice passes validation when the score meets the configured threshold AND
 * no hard validation errors exist.
 */
final readonly class ConfidenceResult
{
    /**
     * @param  float  $overall  Aggregate confidence score, clamped to [0, 1].
     * @param  bool   $passed   True when the invoice passes all quality gates.
     */
    public function __construct(
        public float $overall,
        public bool $passed,
    ) {}
}
