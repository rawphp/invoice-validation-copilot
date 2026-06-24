<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\DTO\ConfidenceResult;
use App\DTO\ExtractedInvoice;
use App\DTO\ValidationError;

/**
 * Computes an aggregate confidence score for an {@see ExtractedInvoice}.
 *
 * Algorithm:
 *  1. Average the per-field {@see \App\DTO\FieldConfidence} scores across all
 *     SCALAR_FIELDS (missing fields contribute 0.0).
 *  2. Apply a flat penalty for each 'error'-severity {@see ValidationError}.
 *  3. Clamp the result to [0, 1].
 *  4. Verdict passes when: no hard errors exist AND score >= threshold.
 */
final class ConfidenceScorer
{
    /**
     * Per-hard-error deduction applied to the averaged field score.
     */
    private const ERROR_PENALTY = 0.1;

    /**
     * @param  float  $threshold  Minimum overall score required to pass (0–1).
     */
    public function __construct(
        private readonly float $threshold = 0.6,
    ) {}

    /**
     * Score the invoice and return a typed {@see ConfidenceResult}.
     *
     * @param  list<ValidationError>  $errors
     */
    public function score(ExtractedInvoice $invoice, array $errors): ConfidenceResult
    {
        $average = $this->averageFieldScore($invoice);
        $hardErrors = $this->countHardErrors($errors);
        $penalised = $average - ($hardErrors * self::ERROR_PENALTY);
        $overall = max(0.0, min(1.0, $penalised));

        $passed = $hardErrors === 0 && $overall >= $this->threshold;

        return new ConfidenceResult(
            overall: $overall,
            passed: $passed,
        );
    }

    /**
     * Average the FieldConfidence scores across all SCALAR_FIELDS.
     * Fields absent from the invoice confidence map default to 0.0.
     */
    private function averageFieldScore(ExtractedInvoice $invoice): float
    {
        $fields = ExtractedInvoice::SCALAR_FIELDS;
        $count = count($fields);

        if ($count === 0) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($fields as $field) {
            $sum += $invoice->confidenceFor($field)->score;
        }

        return $sum / $count;
    }

    /**
     * Count the number of 'error'-severity validation errors (hard failures).
     *
     * @param  list<ValidationError>  $errors
     */
    private function countHardErrors(array $errors): int
    {
        return count(array_filter(
            $errors,
            static fn (ValidationError $e): bool => $e->severity === 'error',
        ));
    }
}
