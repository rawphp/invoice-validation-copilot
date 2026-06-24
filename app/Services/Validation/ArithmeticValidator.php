<?php

declare(strict_types=1);

namespace App\Services\Validation;

use App\DTO\ExtractedInvoice;
use App\DTO\LineItem;
use App\DTO\ValidationError;

/**
 * Validates arithmetic consistency for standard-rated (10% GST) AU invoices.
 *
 * Three checks are performed within a small money tolerance (TOLERANCE_AUD)
 * to absorb legitimate per-line cent rounding:
 *
 *   (a) Line item amounts sum to the declared subtotal.
 *   (b) GST = 10% of subtotal.
 *   (c) Subtotal + GST = total.
 *
 * A discrepancy within {@see self::TOLERANCE_AUD} is silently absorbed.
 * Each failed check emits one error-severity {@see ValidationError} naming
 * the offending figure ('subtotal', 'gst', or 'total').
 *
 * Skips checks for fields that are null (missing fields are handled by
 * {@see RequiredFieldsValidator}).
 */
final class ArithmeticValidator implements Validator
{
    /**
     * Maximum discrepancy (AUD) that is treated as acceptable rounding, not an error.
     *
     * Set to $0.02 — enough to absorb a 1c rounding on each of up to two
     * per-line cents, as commonly seen with AU GST calculations.
     */
    private const TOLERANCE_AUD = 0.02;

    /**
     * Standard AU GST rate.
     */
    private const GST_RATE = 0.10;

    /**
     * @return ValidationError[]
     */
    public function validate(ExtractedInvoice $invoice): array
    {
        $errors = [];

        // Guard: nothing to check if key amounts are missing.
        if ($invoice->subtotal === null || $invoice->gst === null || $invoice->total === null) {
            return $errors;
        }

        // (a) Line items sum to subtotal (GST-exclusive) OR total (GST-inclusive).
        // AU invoices may present line items either way; accept either reconciliation.
        // Only emit an error when line items reconcile against neither.
        if (! empty($invoice->lineItems)) {
            $lineSum = array_reduce(
                $invoice->lineItems,
                fn (float $carry, LineItem $item) => $carry + ($item->amount ?? 0.0),
                0.0,
            );

            $reconcilesToSubtotal = abs($lineSum - $invoice->subtotal) <= self::TOLERANCE_AUD;
            $reconcilesToTotal = abs($lineSum - $invoice->total) <= self::TOLERANCE_AUD;

            if (! $reconcilesToSubtotal && ! $reconcilesToTotal) {
                $errors[] = new ValidationError(
                    field: 'subtotal',
                    severity: 'error',
                    message: sprintf(
                        'Line items sum to %s but subtotal is %s (difference: %s).',
                        number_format($lineSum, 2),
                        number_format($invoice->subtotal, 2),
                        number_format(abs($lineSum - $invoice->subtotal), 2),
                    ),
                );
            }
        }

        // (b) GST = 10% of subtotal.
        $expectedGst = round($invoice->subtotal * self::GST_RATE, 2);
        if (abs($invoice->gst - $expectedGst) > self::TOLERANCE_AUD) {
            $errors[] = new ValidationError(
                field: 'gst',
                severity: 'error',
                message: sprintf(
                    'GST is %s but expected %s (10%% of subtotal %s).',
                    number_format($invoice->gst, 2),
                    number_format($expectedGst, 2),
                    number_format($invoice->subtotal, 2),
                ),
            );
        }

        // (c) Subtotal + GST = total.
        $expectedTotal = $invoice->subtotal + $invoice->gst;
        if (abs($invoice->total - $expectedTotal) > self::TOLERANCE_AUD) {
            $errors[] = new ValidationError(
                field: 'total',
                severity: 'error',
                message: sprintf(
                    'Total is %s but subtotal + GST = %s.',
                    number_format($invoice->total, 2),
                    number_format($expectedTotal, 2),
                ),
            );
        }

        return $errors;
    }
}
